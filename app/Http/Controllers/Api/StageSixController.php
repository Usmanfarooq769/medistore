<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Medicine;
use App\Models\Sale;
use App\Services\BranchContext;
use App\Services\PartnerWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StageSixController extends Controller
{
    private function branch(): int { return BranchContext::id(); }

    private function logEvent(string $event, ?string $type = null, ?int $id = null, array $details = []): void
    {
        DB::table('stage_six_event_logs')->insert([
            'branch_id' => $this->branch(), 'user_id' => auth()->id(), 'event' => $event,
            'subject_type' => $type, 'subject_id' => $id, 'details' => json_encode($details),
            'ip_address' => request()->ip(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function overview()
    {
        $branch = $this->branch();
        return response()->json([
            'online_orders_pending' => DB::table('online_orders')->where('branch_id', $branch)->whereIn('status', ['pending','confirmed','ready'])->count(),
            'deliveries_active' => DB::table('delivery_jobs')->where('branch_id', $branch)->whereIn('status', ['assigned','picked_up'])->count(),
            'cold_chain_open_excursions' => DB::table('cold_chain_logs')->where('branch_id', $branch)->where('is_excursion', true)->whereIn('status', ['open','acknowledged'])->count(),
            'active_supplier_contracts' => DB::table('supplier_contracts')->where(fn($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch))->where('status','active')->whereDate('starts_on','<=',today())->whereDate('ends_on','>=',today())->count(),
        ]);
    }

    /* Online customer order requests. Stock is checked, but NOT deducted/reserved here.
       Staff must create the actual POS sale before dispatch. */
    public function publicBranches()
    {
        return Branch::where('is_active', true)->orderBy('name')->get(['id','name','code','address','phone']);
    }

    public function publicCatalog(Request $request)
    {
        $data = $request->validate(['branch_id'=>'required|integer|exists:branches,id','search'=>'nullable|string|max:80','page'=>'nullable|integer|min:1']);
        $branch = Branch::whereKey($data['branch_id'])->where('is_active', true)->firstOrFail();
        $rows = Medicine::query()->where('is_active', true)
            ->where('requires_prescription', false)->where('requires_controlled_tracking', false)
            ->search($data['search'] ?? null)
            ->select('medicines.id','medicines.name','medicines.generic_name','medicines.strength','medicines.unit','medicines.pack_size','medicines.sale_price','medicines.barcode')
            ->selectSub(function ($q) use ($branch) {
                $q->from('medicine_batches as mb')->whereColumn('mb.medicine_id','medicines.id')->where('mb.branch_id',$branch->id)
                    ->where('mb.quantity','>',0)->where('mb.is_quarantined',false)
                    ->where(fn($w)=>$w->whereNull('mb.expiry_date')->orWhereDate('mb.expiry_date','>=',today()))
                    ->selectRaw('COALESCE(SUM(mb.quantity - COALESCE((SELECT SUM(r.quantity) FROM online_order_reservations r WHERE r.medicine_batch_id = mb.id AND r.status = ? AND (r.expires_at IS NULL OR r.expires_at > ?)), 0)), 0)', ['active', now()]);
            }, 'available_stock')
            ->having('available_stock','>',0)->orderBy('name')->simplePaginate(30);
        return response()->json(['branch'=>$branch,'medicines'=>$rows]);
    }

    public function trackOnlineOrder(Request $request)
    {
        $data = $request->validate(['order_no' => 'required|string|max:50', 'phone' => 'required|string|max:40']);
        $order = DB::table('online_orders')->where('order_no', $data['order_no'])
            ->where('customer_phone', $data['phone'])->first();
        abort_unless($order, 404, 'Order not found. Check the order number and phone number.');
        return response()->json([
            'order_no' => $order->order_no, 'status' => $order->status,
            'total' => $order->total, 'created_at' => $order->created_at,
            'items' => DB::table('online_order_items as i')->join('medicines as m','m.id','=','i.medicine_id')
                ->where('i.online_order_id',$order->id)->get(['m.name','m.strength','i.quantity','i.unit_price','i.line_total']),
        ]);
    }

    public function placeOnlineOrder(Request $request, PartnerWebhookService $webhooks)
    {
        $data = $request->validate([
            'branch_id'=>'required|integer|exists:branches,id', 'customer_name'=>'required|string|max:150',
            'customer_phone'=>'required|string|max:40', 'delivery_address'=>'required|string|max:1000',
            'channel'=>['nullable', Rule::in(['web','phone','whatsapp'])], 'customer_note'=>'nullable|string|max:1500',
            'items'=>'required|array|min:1|max:30', 'items.*.medicine_id'=>'required|integer|distinct|exists:medicines,id',
            'items.*.quantity'=>'required|integer|min:1|max:500',
        ]);
        $branch = Branch::whereKey($data['branch_id'])->where('is_active',true)->firstOrFail();
        return DB::transaction(function () use ($data, $branch, $webhooks) {
            $subtotal = 0; $lines = [];
            foreach ($data['items'] as $line) {
                $medicine = Medicine::whereKey($line['medicine_id'])->where('is_active',true)->firstOrFail();
                if ($medicine->requires_prescription || $medicine->requires_controlled_tracking) {
                    throw ValidationException::withMessages(['items'=>'Prescription-only and controlled medicines cannot be ordered through the public storefront.']);
                }
                $price = (float) $medicine->sale_price;
                $total = round($price * (int)$line['quantity'], 2);
                $subtotal += $total;
                $lines[] = ['medicine_id'=>$medicine->id,'quantity'=>$line['quantity'],'unit_price'=>$price,'line_total'=>$total];
            }
            $customer = DB::table('customers')->where('phone',$data['customer_phone'])->first();
            $id = DB::table('online_orders')->insertGetId([
                'order_no'=>'ON-'.now()->format('ymd').'-'.Str::upper(Str::random(7)), 'branch_id'=>$branch->id,
                'customer_id'=>$customer?->id, 'customer_name'=>$data['customer_name'], 'customer_phone'=>$data['customer_phone'],
                'delivery_address'=>$data['delivery_address'], 'channel'=>$data['channel'] ?? 'web', 'status'=>'pending',
                'subtotal'=>$subtotal, 'discount'=>0, 'total'=>$subtotal, 'customer_note'=>$data['customer_note'] ?? null,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach ($lines as $line) DB::table('online_order_items')->insert($line + ['online_order_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
            app(\App\Services\OnlineOrderInventoryService::class)->reserve($id, $branch->id, $lines, 30);
            $order=DB::table('online_orders')->where('id',$id)->first();
            $webhooks->queue('order.created',['order_no'=>$order->order_no,'branch_id'=>$order->branch_id,'total'=>$order->total,'status'=>$order->status]);
            return response()->json(['message'=>'Order request received. The pharmacy will confirm availability before preparing it.','order_no'=>$order->order_no],201);
        });
    }

    public function onlineOrders(Request $request)
    {
        return DB::table('online_orders as o')->leftJoin('branches as b','b.id','=','o.branch_id')
            ->leftJoin('sales as s','s.id','=','o.sale_id')->where('o.branch_id',$this->branch())
            ->when($request->filled('status'),fn($q)=>$q->where('o.status',$request->string('status')))
            ->select('o.*','b.name as branch_name','s.invoice_no')->orderByDesc('o.id')->paginate(25)->withQueryString();
    }

    public function onlineOrderDetails(int $order)
    {
        $head = DB::table('online_orders')->where('id',$order)->where('branch_id',$this->branch())->first();
        abort_unless($head,404);
        $head->items = DB::table('online_order_items as i')->join('medicines as m','m.id','=','i.medicine_id')->where('i.online_order_id',$order)->get(['i.*','m.name as medicine_name','m.strength']);
        $head->delivery = DB::table('delivery_jobs')->where('online_order_id',$order)->first();
        return response()->json($head);
    }

    public function reviewOnlineOrder(Request $request, int $order, PartnerWebhookService $webhooks)
    {
        $data = $request->validate(['decision'=>['required',Rule::in(['confirm','cancel'])],'staff_note'=>'nullable|string|max:1500']);
        return DB::transaction(function () use ($data,$order,$webhooks) {
            $head = DB::table('online_orders')->where('id',$order)->where('branch_id',$this->branch())->lockForUpdate()->first();
            abort_unless($head,404);
            if ($head->status !== 'pending') throw ValidationException::withMessages(['order'=>'Only pending orders can be reviewed.']);
            if ($data['decision']==='confirm') {
                $items=DB::table('online_order_items')->where('online_order_id',$order)->get();
                foreach ($items as $item) {
                    $medicine = Medicine::whereKey($item->medicine_id)->where('is_active',true)->first();
                    if (!$medicine || $medicine->requires_prescription || $medicine->requires_controlled_tracking) {
                        throw ValidationException::withMessages(['order'=>'An item is no longer eligible for public ordering. Please contact the customer.']);
                    }
                }
                $activeReservations = DB::table('online_order_reservations')->where('online_order_id',$order)->where('status','active')->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->count();
                if ($activeReservations < 1) throw ValidationException::withMessages(['order'=>'The stock reservation expired. Cancel this order and ask the customer to place it again.']);
                app(\App\Services\OnlineOrderInventoryService::class)->extend($order);
                DB::table('online_orders')->where('id',$order)->update(['status'=>'confirmed','confirmed_by'=>auth()->id(),'confirmed_at'=>now(),'staff_note'=>$data['staff_note']??null,'updated_at'=>now()]);
            } else {
                app(\App\Services\OnlineOrderInventoryService::class)->release($order);
                DB::table('online_orders')->where('id',$order)->update(['status'=>'cancelled','staff_note'=>$data['staff_note']??null,'updated_at'=>now()]);
            }
            $this->logEvent('online_order_'.$data['decision'],'online_order',$order,$data);
            $webhooks->queue($data['decision']==='confirm'?'order.confirmed':'order.cancelled',['order_id'=>$order,'decision'=>$data['decision'],'branch_id'=>$this->branch()]);
            return response()->json(['message'=>'Online order '.$data['decision'].'ed.']);
        });
    }

    public function linkSale(Request $request, int $order, PartnerWebhookService $webhooks)
    {
        $data=$request->validate(['sale_id'=>'required|integer|exists:sales,id']);
        $head=DB::table('online_orders')->where('id',$order)->where('branch_id',$this->branch())->first();
        abort_unless($head,404);
        abort_unless($head->status==='confirmed',422,'Confirm the online order before linking its POS sale.');
        $sale=Sale::whereKey($data['sale_id'])->where('branch_id',$this->branch())->firstOrFail();
        if ($head->sale_id) throw ValidationException::withMessages(['sale_id'=>'A POS sale is already linked.']);
        if (DB::table('online_orders')->where('sale_id',$sale->id)->exists()) throw ValidationException::withMessages(['sale_id'=>'This POS sale is already linked to another online order.']);
        $requested=DB::table('online_order_items')->where('online_order_id',$order)->get()->keyBy('medicine_id');
        $sold=DB::table('sale_items')->where('sale_id',$sale->id)->select('medicine_id',DB::raw('SUM(quantity) as quantity'))->groupBy('medicine_id')->get()->keyBy('medicine_id');
        if ($requested->count() !== $sold->count()) throw ValidationException::withMessages(['sale_id'=>'The POS sale must contain exactly the same medicines as this online order.']);
        foreach ($requested as $medicineId => $line) {
            if ((int) ($sold[$medicineId]->quantity ?? 0) !== (int) $line->quantity) throw ValidationException::withMessages(['sale_id'=>'The POS sale quantities must exactly match this online order.']);
        }
        DB::table('online_order_reservations')->where('online_order_id',$order)->where('status','active')->update(['status'=>'released','updated_at'=>now()]);
        DB::table('online_orders')->where('id',$order)->update(['sale_id'=>$sale->id,'status'=>'ready','updated_at'=>now()]);
        $this->logEvent('online_order_linked_to_sale','online_order',$order,['sale_id'=>$sale->id]);
        $webhooks->queue('order.sale_linked',['order_id'=>$order,'sale_id'=>$sale->id,'branch_id'=>$this->branch()]);
        return response()->json(['message'=>'POS sale linked. Order is ready for delivery.']);
    }

    public function deliveries()
    {
        return DB::table('delivery_jobs as d')->join('online_orders as o','o.id','=','d.online_order_id')
            ->where('d.branch_id',$this->branch())->orderByDesc('d.id')->paginate(25,
                ['d.*','o.order_no','o.customer_name','o.customer_phone','o.delivery_address','o.total','o.sale_id']);
    }

    public function assignDelivery(Request $request, int $order)
    {
        $data=$request->validate(['driver_name'=>'required|string|max:120','driver_phone'=>'nullable|string|max:40','delivery_note'=>'nullable|string|max:1000']);
        return DB::transaction(function () use ($data,$order) {
            $head=DB::table('online_orders')->where('id',$order)->where('branch_id',$this->branch())->lockForUpdate()->first();
            abort_unless($head,404);
            if ($head->status!=='ready' || !$head->sale_id) throw ValidationException::withMessages(['order'=>'A confirmed POS sale must be linked before assigning delivery.']);
            if (DB::table('delivery_jobs')->where('online_order_id',$order)->exists()) throw ValidationException::withMessages(['order'=>'A delivery is already assigned.']);
            DB::table('delivery_jobs')->insert(['online_order_id'=>$order,'branch_id'=>$this->branch(),'driver_name'=>$data['driver_name'],'driver_phone'=>$data['driver_phone']??null,'delivery_note'=>$data['delivery_note']??null,'status'=>'assigned','assigned_by'=>auth()->id(),'assigned_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
            $this->logEvent('delivery_assigned','online_order',$order,['driver'=>$data['driver_name']]);
            return response()->json(['message'=>'Delivery assigned.'],201);
        });
    }

    public function updateDelivery(Request $request, int $delivery)
    {
        $data=$request->validate(['status'=>['required',Rule::in(['picked_up','delivered','failed'])],'delivery_note'=>'nullable|string|max:1000']);
        return DB::transaction(function () use ($data,$delivery) {
            $job=DB::table('delivery_jobs')->where('id',$delivery)->where('branch_id',$this->branch())->lockForUpdate()->first();
            abort_unless($job,404);
            $allowed=['assigned'=>['picked_up','failed'],'picked_up'=>['delivered','failed']];
            if (!in_array($data['status'],$allowed[$job->status]??[],true)) throw ValidationException::withMessages(['status'=>'Invalid delivery status transition.']);
            DB::table('delivery_jobs')->where('id',$delivery)->update(['status'=>$data['status'],'delivery_note'=>$data['delivery_note']??$job->delivery_note,'delivered_at'=>$data['status']==='delivered'?now():null,'updated_at'=>now()]);
            if ($data['status']==='delivered') DB::table('online_orders')->where('id',$job->online_order_id)->update(['status'=>'delivered','delivered_at'=>now(),'updated_at'=>now()]);
            $this->logEvent('delivery_'.$data['status'],'delivery_job',$delivery,$data);
            return response()->json(['message'=>'Delivery updated.']);
        });
    }

    /* Cold-chain readings. Out-of-range readings create an open excursion; staff must document the corrective action. */
    public function coldChainDevices()
    {
        return DB::table('cold_chain_devices')->where('branch_id',$this->branch())->orderBy('name')->get();
    }

    public function saveColdChainDevice(Request $request, ?int $device = null)
    {
        $data=$request->validate(['device_code'=>'required|string|max:60','name'=>'required|string|max:150','location'=>'nullable|string|max:180','minimum_temperature'=>'required|numeric|min:-80|max:30','maximum_temperature'=>'required|numeric|min:-70|max:50','is_active'=>'sometimes|boolean']);
        if ((float)$data['minimum_temperature'] >= (float)$data['maximum_temperature']) throw ValidationException::withMessages(['maximum_temperature'=>'Maximum temperature must be higher than minimum temperature.']);
        if ($device) {
            $exists=DB::table('cold_chain_devices')->where('id',$device)->where('branch_id',$this->branch())->exists(); abort_unless($exists,404);
            DB::table('cold_chain_devices')->where('id',$device)->update($data+['updated_at'=>now()]);
            return response()->json(['message'=>'Cold-chain device updated.']);
        }
        $id=DB::table('cold_chain_devices')->insertGetId($data+['branch_id'=>$this->branch(),'is_active'=>$data['is_active']??true,'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        $this->logEvent('cold_chain_device_created','cold_chain_device',$id,$data);
        return response()->json(['message'=>'Cold-chain device created.','id'=>$id],201);
    }

    public function coldChainLogs(Request $request)
    {
        return DB::table('cold_chain_logs as l')->join('cold_chain_devices as d','d.id','=','l.device_id')->where('l.branch_id',$this->branch())
            ->when($request->has('open_only') && $request->boolean('open_only'),fn($q)=>$q->where('l.is_excursion',true)->whereIn('l.status',['open','acknowledged']))
            ->select('l.*','d.device_code','d.name as device_name','d.location')->orderByDesc('l.measured_at')->paginate(30);
    }

    public function recordTemperature(Request $request, int $device)
    {
        $data=$request->validate(['temperature'=>'required|numeric|min:-100|max:100','measured_at'=>'nullable|date','action_taken'=>'nullable|string|max:1500']);
        $sensor=DB::table('cold_chain_devices')->where('id',$device)->where('branch_id',$this->branch())->where('is_active',true)->first();
        abort_unless($sensor,404);
        $temperature=(float)$data['temperature'];
        $excursion=$temperature < (float)$sensor->minimum_temperature || $temperature > (float)$sensor->maximum_temperature;
        $id=DB::table('cold_chain_logs')->insertGetId(['device_id'=>$device,'branch_id'=>$this->branch(),'temperature'=>$temperature,'measured_at'=>$data['measured_at']??now(),'is_excursion'=>$excursion,'status'=>$excursion?'open':'normal','action_taken'=>$data['action_taken']??null,'recorded_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        if ($excursion) $this->logEvent('cold_chain_excursion','cold_chain_log',$id,['temperature'=>$temperature,'device'=>$sensor->device_code]);
        return response()->json(['message'=>$excursion?'Temperature excursion recorded. Investigate affected stock and document corrective action.':'Temperature reading recorded.','is_excursion'=>$excursion,'id'=>$id],201);
    }

    public function acknowledgeExcursion(Request $request, int $log)
    {
        $data=$request->validate(['action_taken'=>'required|string|min:5|max:2000','status'=>['required',Rule::in(['acknowledged','resolved'])]]);
        $row=DB::table('cold_chain_logs')->where('id',$log)->where('branch_id',$this->branch())->where('is_excursion',true)->first(); abort_unless($row,404);
        if ($row->status==='resolved') throw ValidationException::withMessages(['log'=>'This excursion is already resolved.']);
        DB::table('cold_chain_logs')->where('id',$log)->update(['status'=>$data['status'],'action_taken'=>$data['action_taken'],'acknowledged_by'=>auth()->id(),'acknowledged_at'=>now(),'updated_at'=>now()]);
        $this->logEvent('cold_chain_'.$data['status'],'cold_chain_log',$log,$data);
        return response()->json(['message'=>'Temperature excursion updated.']);
    }

    /* Supplier contract register and performance summary. */
    public function supplierContracts()
    {
        return DB::table('supplier_contracts as c')->join('suppliers as s','s.id','=','c.supplier_id')->where(fn($q)=>$q->whereNull('c.branch_id')->orWhere('c.branch_id',$this->branch()))
            ->select('c.*','s.name as supplier_name')->orderByDesc('c.id')->paginate(30);
    }

    public function saveSupplierContract(Request $request, ?int $contract = null)
    {
        $data=$request->validate(['supplier_id'=>'required|exists:suppliers,id','contract_no'=>'required|string|max:70','starts_on'=>'required|date','ends_on'=>'required|date|after_or_equal:starts_on','lead_days'=>'required|integer|min:0|max:365','discount_percent'=>'required|numeric|min:0|max:100','payment_terms'=>'nullable|string|max:180','notes'=>'nullable|string|max:3000','status'=>['required',Rule::in(['draft','active','expired','terminated'])]]);
        $query=DB::table('supplier_contracts')->where('contract_no',$data['contract_no']); if ($contract) $query->where('id','!=',$contract);
        if ($query->exists()) throw ValidationException::withMessages(['contract_no'=>'Contract number already exists.']);
        $data['branch_id']=$this->branch();
        if ($contract) {
            $exists=DB::table('supplier_contracts')->where('id',$contract)->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$this->branch()))->exists(); abort_unless($exists,404);
            DB::table('supplier_contracts')->where('id',$contract)->update($data+['updated_at'=>now()]);
            $id=$contract;
        } else {
            $id=DB::table('supplier_contracts')->insertGetId($data+['created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        }
        $this->logEvent('supplier_contract_saved','supplier_contract',$id,$data);
        return response()->json(['message'=>'Supplier contract saved.','id'=>$id],$contract?200:201);
    }

    public function supplierPerformance(Request $request)
    {
        $from=$request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to=$request->date('to')?->toDateString() ?? today()->toDateString();
        $purchases=DB::table('purchases')->select('supplier_id',DB::raw('COUNT(*) as purchase_count'),DB::raw('SUM(total) as purchase_total'),DB::raw('SUM(due) as outstanding'))
            ->where('branch_id',$this->branch())->whereBetween('purchase_date',[$from,$to])->groupBy('supplier_id');
        $rows=DB::table('suppliers as s')->leftJoinSub($purchases,'p',fn($j)=>$j->on('p.supplier_id','=','s.id'))
            ->select('s.id','s.name','s.phone','s.email',DB::raw('COALESCE(p.purchase_count,0) as purchase_count'),DB::raw('COALESCE(p.purchase_total,0) as purchase_total'),DB::raw('COALESCE(p.outstanding,0) as outstanding'))
            ->orderByDesc('purchase_total')->get();
        return response()->json(['from'=>$from,'to'=>$to,'suppliers'=>$rows]);
    }

    public function eventLogs()
    {
        return DB::table('stage_six_event_logs')->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$this->branch()))->orderByDesc('id')->paginate(40);
    }
}
