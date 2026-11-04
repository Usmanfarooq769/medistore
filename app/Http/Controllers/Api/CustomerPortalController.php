<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PartnerWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerPortalController extends Controller
{
    public function invite(Request $request, int $customer)
    {
        $record = DB::table('customers')->where('id', $customer)->first();
        abort_unless($record, 404);
        if (! $record->phone) throw ValidationException::withMessages(['customer'=>'A customer phone number is required before creating a portal invitation.']);
        if (DB::table('customer_portal_accounts')->where('customer_id',$customer)->exists()) throw ValidationException::withMessages(['customer'=>'This customer already has a portal account.']);
        $plain = Str::random(64);
        DB::table('customer_portal_invitations')->where('customer_id',$customer)->whereNull('redeemed_at')->update(['expires_at'=>now()]);
        DB::table('customer_portal_invitations')->insert([
            'customer_id'=>$customer,'token_hash'=>hash('sha256',$plain),'created_by'=>$request->user()->id,
            'expires_at'=>now()->addHours(24),'created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->json(['message'=>'Invitation created. Share this link securely with the customer; it expires in 24 hours.','invitation_token'=>$plain],201);
    }

    public function redeem(Request $request)
    {
        $data=$request->validate(['invitation_token'=>'required|string|min:32|max:100','password'=>'required|string|min:10|confirmed']);
        return DB::transaction(function () use ($data) {
            $invite=DB::table('customer_portal_invitations')->where('token_hash',hash('sha256',$data['invitation_token']))->whereNull('redeemed_at')->where('expires_at','>',now())->lockForUpdate()->first();
            if (! $invite) throw ValidationException::withMessages(['invitation_token'=>'This invitation is invalid, expired or already used.']);
            if (DB::table('customer_portal_accounts')->where('customer_id',$invite->customer_id)->exists()) throw ValidationException::withMessages(['invitation_token'=>'A portal account already exists.']);
            $accountId=DB::table('customer_portal_accounts')->insertGetId(['customer_id'=>$invite->customer_id,'password'=>Hash::make($data['password']),'status'=>'active','created_at'=>now(),'updated_at'=>now()]);
            DB::table('customer_portal_invitations')->where('id',$invite->id)->update(['redeemed_at'=>now(),'updated_at'=>now()]);
            return response()->json(['message'=>'Customer portal account activated.','account_id'=>$accountId],201);
        });
    }

    public function login(Request $request)
    {
        $data=$request->validate(['phone'=>'required|string|max:40','password'=>'required|string']);
        $customer=DB::table('customers')->where('phone',$data['phone'])->first();
        $account=$customer ? DB::table('customer_portal_accounts')->where('customer_id',$customer->id)->where('status','active')->first() : null;
        if (! $account || ! Hash::check($data['password'],$account->password)) throw ValidationException::withMessages(['phone'=>'Invalid phone number or password.']);
        $plain=Str::random(72);
        DB::table('customer_portal_tokens')->insert(['account_id'=>$account->id,'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addDays(30),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('customer_portal_accounts')->where('id',$account->id)->update(['last_login_at'=>now(),'updated_at'=>now()]);
        return response()->json(['token'=>$plain,'token_type'=>'Bearer','expires_in_days'=>30,'customer'=>['id'=>$customer->id,'name'=>$customer->name,'phone'=>$customer->phone]]);
    }

    public function logout(Request $request)
    {
        $plain=$request->bearerToken();
        if ($plain) DB::table('customer_portal_tokens')->where('token_hash',hash('sha256',$plain))->delete();
        return response()->json(['message'=>'Customer portal session ended.']);
    }

    public function dashboard(Request $request)
    {
        $account=$request->attributes->get('customer_portal_account');
        $customer=DB::table('customers')->find($account->customer_id);
        return response()->json([
            'customer'=>['id'=>$customer->id,'name'=>$customer->name,'phone'=>$customer->phone,'balance'=>$customer->balance,'loyalty_points'=>$customer->loyalty_points ?? 0],
            'orders_count'=>DB::table('online_orders')->where('customer_id',$customer->id)->count(),
            'invoices_count'=>DB::table('sales')->where('customer_id',$customer->id)->count(),
            'recent_orders'=>DB::table('online_orders')->where('customer_id',$customer->id)->orderByDesc('id')->limit(5)->get(['order_no','status','total','created_at']),
        ]);
    }

    public function orders(Request $request)
    {
        $account=$request->attributes->get('customer_portal_account');
        return response()->json(DB::table('online_orders')->where('customer_id',$account->customer_id)->orderByDesc('id')->paginate(20));
    }

    public function invoices(Request $request)
    {
        $account=$request->attributes->get('customer_portal_account');
        return response()->json(DB::table('sales')->where('customer_id',$account->customer_id)->orderByDesc('sale_date')->paginate(20)->through(fn($sale)=>[
            'id'=>$sale->id,'invoice_no'=>$sale->invoice_no,'sale_date'=>$sale->sale_date,'total'=>$sale->total,'paid'=>$sale->paid,'due'=>$sale->due,'payment_status'=>$sale->payment_status,
        ]));
    }

    public function placeOrder(Request $request, PartnerWebhookService $webhooks)
    {
        $account=$request->attributes->get('customer_portal_account');
        $customer=DB::table('customers')->find($account->customer_id);
        $data=$request->validate(['branch_id'=>'required|integer|exists:branches,id','delivery_address'=>'required|string|max:1000','items'=>'required|array|min:1|max:30','items.*.medicine_id'=>'required|integer|distinct|exists:medicines,id','items.*.quantity'=>'required|integer|min:1|max:500']);
        $branch=DB::table('branches')->where('id',$data['branch_id'])->where('is_active',true)->first(); abort_unless($branch,404);
        $order=DB::transaction(function () use ($data,$customer,$branch) {
            $lines=[];$subtotal=0;
            foreach($data['items'] as $line){
                $medicine=DB::table('medicines')->where('id',$line['medicine_id'])->where('is_active',true)->first();
                if(!$medicine || ($medicine->requires_prescription ?? false) || ($medicine->requires_controlled_tracking ?? false)) throw ValidationException::withMessages(['items'=>'Prescription-only or controlled medicines must be handled by pharmacy staff.']);
                $total=round((float)$medicine->sale_price*(int)$line['quantity'],2);$subtotal+=$total;
                $lines[]=['medicine_id'=>$medicine->id,'quantity'=>$line['quantity'],'unit_price'=>$medicine->sale_price,'line_total'=>$total];
            }
            $id=DB::table('online_orders')->insertGetId(['order_no'=>'CP-'.now()->format('ymd').'-'.Str::upper(Str::random(7)),'branch_id'=>$branch->id,'customer_id'=>$customer->id,'customer_name'=>$customer->name,'customer_phone'=>$customer->phone,'delivery_address'=>$data['delivery_address'],'channel'=>'web','status'=>'pending','subtotal'=>$subtotal,'discount'=>0,'total'=>$subtotal,'customer_note'=>'Customer portal order','created_at'=>now(),'updated_at'=>now()]);
            foreach($lines as $line) DB::table('online_order_items')->insert($line+['online_order_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            app(\App\Services\OnlineOrderInventoryService::class)->reserve($id, $branch->id, $lines, 30);
            return DB::table('online_orders')->find($id);
        });
        $webhooks->queue('order.created',['order_no'=>$order->order_no,'branch_id'=>$order->branch_id,'customer_id'=>$customer->id,'total'=>$order->total]);
        return response()->json(['message'=>'Order request submitted. The pharmacy must confirm it before preparation.','order_no'=>$order->order_no],201);
    }
}
