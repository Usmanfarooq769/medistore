<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Medicine;
use App\Services\PartnerWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartnerIntegrationController extends Controller
{
    public function index()
    {
        return response()->json(DB::table('partner_api_clients')->orderByDesc('id')->get(['id','name','client_key','scopes','branch_ids','rate_limit_per_minute','status','last_used_at','created_at']));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150', 'scopes' => 'required|array|min:1',
            'scopes.*' => ['string', Rule::in(['catalog:read','orders:write','orders:read','*'])],
            'rate_limit_per_minute' => 'nullable|integer|min:1|max:600', 'branch_ids'=>'nullable|array', 'branch_ids.*'=>'integer|exists:branches,id',
        ]);
        $key = 'ms_'.Str::lower(Str::random(32));
        $secret = Str::random(64);
        $id = DB::table('partner_api_clients')->insertGetId([
            'name' => $data['name'], 'client_key' => $key, 'secret_hash' => Hash::make($secret),
            'scopes' => json_encode(array_values(array_unique($data['scopes']))), 'branch_ids' => isset($data['branch_ids']) ? json_encode(array_values(array_unique($data['branch_ids']))) : null,
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 60, 'status' => 'active',
            'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Partner created. Copy the secret now; it cannot be displayed again.', 'partner' => DB::table('partner_api_clients')->where('id',$id)->first(['id','name','client_key','scopes','rate_limit_per_minute','status','created_at']), 'client_secret' => $secret], 201);
    }

    public function update(Request $request, int $partner)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active','suspended','revoked'])], 'rate_limit_per_minute' => 'nullable|integer|min:1|max:600']);
        DB::table('partner_api_clients')->where('id', $partner)->update($data + ['updated_at' => now()]);
        return response()->json(['message' => 'Partner integration updated.']);
    }

    public function endpoints(Request $request, int $partner)
    {
        abort_unless(DB::table('partner_api_clients')->where('id', $partner)->exists(), 404);
        return response()->json(DB::table('partner_webhook_endpoints')->where('partner_id', $partner)->orderByDesc('id')->get(['id','partner_id','url','events','status','created_at']));
    }

    public function storeEndpoint(Request $request, int $partner)
    {
        $data = $request->validate(['url' => 'required|url|max:1000', 'events' => 'required|array|min:1', 'events.*' => 'string|max:100']);
        if (! str_starts_with(strtolower($data['url']), 'https://')) throw ValidationException::withMessages(['url' => 'Webhook endpoints must use HTTPS.']);
        $host = strtolower((string) parse_url($data['url'], PHP_URL_HOST));
        $ip = filter_var($host, FILTER_VALIDATE_IP);
        if ($host === 'localhost' || str_ends_with($host, '.local') || ($ip && ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            throw ValidationException::withMessages(['url' => 'Private or local webhook destinations are not allowed.']);
        }
        abort_unless(DB::table('partner_api_clients')->where('id', $partner)->where('status','active')->exists(), 404);
        $secret = Str::random(64);
        $id = DB::table('partner_webhook_endpoints')->insertGetId([
            'partner_id' => $partner, 'url' => $data['url'], 'events' => json_encode(array_values(array_unique($data['events']))),
            'signing_secret' => Crypt::encryptString($secret), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['message' => 'Webhook endpoint created. Store its signing secret securely.', 'endpoint' => DB::table('partner_webhook_endpoints')->where('id',$id)->first(['id','partner_id','url','events','status','created_at']), 'signing_secret' => $secret], 201);
    }

    public function updateEndpoint(Request $request, int $partner, int $endpoint)
    {
        $data=$request->validate(['status'=>['required',Rule::in(['active','disabled'])]]);
        $updated=DB::table('partner_webhook_endpoints')->where('id',$endpoint)->where('partner_id',$partner)->update($data+['updated_at'=>now()]);
        abort_unless($updated,404);
        return response()->json(['message'=>'Webhook endpoint updated.']);
    }

    public function deliveries(int $partner)
    {
        return response()->json(DB::table('partner_webhook_deliveries as d')->join('partner_webhook_endpoints as e','e.id','=','d.endpoint_id')->where('e.partner_id',$partner)->orderByDesc('d.id')->limit(100)->get(['d.*']));
    }

    public function partnerCatalog(Request $request)
    {
        $data = $request->validate(['branch_id'=>'required|integer|exists:branches,id','search'=>'nullable|string|max:80']);
        $partner=$request->attributes->get('partner_api_client');
        $allowed=json_decode($partner->branch_ids ?? '[]',true) ?: [];
        if ($allowed && ! in_array((int)$data['branch_id'], array_map('intval',$allowed), true)) throw ValidationException::withMessages(['branch_id'=>'This partner is not authorized for the requested branch.']);
        $branch = Branch::whereKey($data['branch_id'])->where('is_active',true)->firstOrFail();
        $items = Medicine::query()->where('is_active',true)->where('requires_prescription',false)->where('requires_controlled_tracking',false)
            ->search($data['search'] ?? null)
            ->withSum(['batches as available_stock'=>fn($q)=>$q->where('branch_id',$branch->id)->where('quantity','>',0)->where('is_quarantined',false)->where(fn($w)=>$w->whereNull('expiry_date')->orWhere('expiry_date','>=',today()))],'quantity')
            ->having('available_stock','>',0)->orderBy('name')->limit(100)
            ->get(['id','name','generic_name','strength','unit','sale_price','barcode']);
        return response()->json(['branch'=>['id'=>$branch->id,'name'=>$branch->name,'code'=>$branch->code],'items'=>$items]);
    }

    public function partnerOrder(Request $request, string $orderNo)
    {
        $partner=$request->attributes->get('partner_api_client');
        $order=DB::table('online_orders')->where('order_no',$orderNo)->where('partner_api_client_id',$partner->id)->first();
        abort_unless($order,404);
        $order->items=DB::table('online_order_items as i')->join('medicines as m','m.id','=','i.medicine_id')->where('i.online_order_id',$order->id)->get(['i.medicine_id','m.name','m.strength','i.quantity','i.unit_price','i.line_total']);
        return response()->json($order);
    }

    public function partnerCreateOrder(Request $request, PartnerWebhookService $webhooks)
    {
        $data = $request->validate([
            'branch_id'=>'required|integer|exists:branches,id','customer_name'=>'required|string|max:150',
            'customer_phone'=>'required|string|max:40','delivery_address'=>'nullable|string|max:1000',
            'items'=>'required|array|min:1|max:30','items.*.medicine_id'=>'required|integer|distinct|exists:medicines,id',
            'items.*.quantity'=>'required|integer|min:1|max:500',
        ]);
        $partner=$request->attributes->get('partner_api_client');
        $allowed=json_decode($partner->branch_ids ?? '[]',true) ?: [];
        if ($allowed && ! in_array((int)$data['branch_id'], array_map('intval',$allowed), true)) throw ValidationException::withMessages(['branch_id'=>'This partner is not authorized for the requested branch.']);
        $branch = Branch::whereKey($data['branch_id'])->where('is_active',true)->firstOrFail();
        $partnerId=(int)$partner->id;
        $order = DB::transaction(function () use ($data, $branch, $partnerId) {
            $lines=[]; $subtotal=0;
            foreach ($data['items'] as $line) {
                $medicine=Medicine::whereKey($line['medicine_id'])->where('is_active',true)->firstOrFail();
                if ($medicine->requires_prescription || $medicine->requires_controlled_tracking) throw ValidationException::withMessages(['items'=>'Prescription-only or controlled medicines cannot be ordered through partner storefront APIs.']);
                $stock=(int)DB::table('medicine_batches')->where('medicine_id',$medicine->id)->where('branch_id',$branch->id)->where('quantity','>',0)->where('is_quarantined',false)->where(fn($q)=>$q->whereNull('expiry_date')->orWhere('expiry_date','>=',today()))->sum('quantity');
                if ($stock < $line['quantity']) throw ValidationException::withMessages(['items'=>"Insufficient stock for {$medicine->name}."]);
                $total=round((float)$medicine->sale_price*(int)$line['quantity'],2); $subtotal += $total;
                $lines[]=['medicine_id'=>$medicine->id,'quantity'=>$line['quantity'],'unit_price'=>$medicine->sale_price,'line_total'=>$total];
            }
            $customer=DB::table('customers')->where('phone',$data['customer_phone'])->first();
            $id=DB::table('online_orders')->insertGetId([
                'order_no'=>'PT-'.now()->format('ymd').'-'.Str::upper(Str::random(7)), 'branch_id'=>$branch->id,'customer_id'=>$customer?->id,'partner_api_client_id'=>$partnerId,
                'customer_name'=>$data['customer_name'],'customer_phone'=>$data['customer_phone'],'delivery_address'=>$data['delivery_address']??null,
                'channel'=>'web','status'=>'pending','subtotal'=>$subtotal,'discount'=>0,'total'=>$subtotal,
                'customer_note'=>'Partner API order','created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach($lines as $line) DB::table('online_order_items')->insert($line+['online_order_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            return DB::table('online_orders')->find($id);
        });
        $webhooks->queue('order.created',['order_no'=>$order->order_no,'branch_id'=>$order->branch_id,'partner_id'=>$partnerId,'total'=>$order->total,'status'=>$order->status]);
        return response()->json(['message'=>'Order request accepted. Stock is not deducted until a POS sale is created.','order'=>$order],201);
    }
}
