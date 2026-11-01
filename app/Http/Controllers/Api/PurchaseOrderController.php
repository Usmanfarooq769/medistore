<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Medicine,Payment,Purchase,PurchaseOrder,PurchaseOrderItem,Supplier};
use App\Services\{BranchContext,JournalPostingService,StockService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function index(Request $r) { return PurchaseOrder::with(['supplier:id,name','branch:id,name','items.medicine:id,name,strength'])->where('branch_id',BranchContext::id())->when($r->status,fn($q,$v)=>$q->where('status',$v))->latest()->paginate(20); }
    public function store(Request $r)
    {
        $d=$r->validate(['supplier_id'=>'required|exists:suppliers,id','order_date'=>'required|date','expected_date'=>'nullable|date|after_or_equal:order_date','discount'=>'nullable|numeric|min:0','tax'=>'nullable|numeric|min:0','notes'=>'nullable|string|max:2000','items'=>'required|array|min:1','items.*.medicine_id'=>'required|exists:medicines,id','items.*.quantity'=>'required|integer|min:1','items.*.bonus_qty'=>'nullable|integer|min:0','items.*.purchase_price'=>'required|numeric|min:0','items.*.sale_price'=>'required|numeric|min:0','items.*.batch_no'=>'nullable|string|max:100','items.*.expiry_date'=>'nullable|date|after:today']);
        $order=DB::transaction(function()use($d){$sub=collect($d['items'])->sum(fn($i)=>$i['quantity']*$i['purchase_price']);$discount=min((float)($d['discount']??0),$sub);$tax=(float)($d['tax']??0);$o=PurchaseOrder::create(['order_no'=>'PO-'.now()->format('Ymd').'-'.strtoupper(str()->random(5)),'supplier_id'=>$d['supplier_id'],'branch_id'=>BranchContext::id(),'order_date'=>$d['order_date'],'expected_date'=>$d['expected_date']??null,'status'=>'pending_approval','subtotal'=>$sub,'discount'=>$discount,'tax'=>$tax,'total'=>round($sub-$discount+$tax,2),'notes'=>$d['notes']??null,'created_by'=>auth()->id()]);foreach($d['items'] as $i)$o->items()->create($i);return $o;});
        return response()->json(['message'=>'Purchase order submitted for approval.','data'=>$order->load('items.medicine')],201);
    }
    public function show(PurchaseOrder $purchaseOrder) { abort_unless((int)$purchaseOrder->branch_id===(int)BranchContext::id(),404); return $purchaseOrder->load(['supplier','branch','items.medicine']); }
    public function approve(PurchaseOrder $purchaseOrder, Request $r)
    {
        abort_unless((int)$purchaseOrder->branch_id===(int)BranchContext::id(),404);
        $d=$r->validate(['decision'=>'required|in:approve,reject','note'=>'nullable|string|max:1000']);
        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $d) {
            $order = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_unless($order->status==='pending_approval',422,'Only pending orders can be reviewed.');
            $order->update(['status'=>$d['decision']==='approve'?'approved':'rejected','approved_by'=>auth()->id(),'approved_at'=>now(),'notes'=>trim(($order->notes??'')."\n".($d['note']??''))]);
            return $order;
        });
        return response()->json(['message'=>'Purchase order '.$purchaseOrder->status.'.','data'=>$purchaseOrder->fresh('items')]);
    }
    public function receive(PurchaseOrder $purchaseOrder, Request $r, StockService $stock)
    {
        abort_unless((int)$purchaseOrder->branch_id===(int)BranchContext::id(),404);
        abort_unless($purchaseOrder->status==='approved',422,'Only approved purchase orders can be received.');
        $d=$r->validate(['paid'=>'nullable|numeric|min:0','payment_method'=>'nullable|in:cash,card,online,bank']);
        $purchase=DB::transaction(function()use($purchaseOrder,$d,$stock){
            $order=PurchaseOrder::with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);abort_unless($order->status==='approved',422,'This purchase order is no longer available to receive.');$paid=min((float)($d['paid']??0),(float)$order->total);
            $p=Purchase::create(['supplier_id'=>$order->supplier_id,'purchase_date'=>today(),'subtotal'=>$order->subtotal,'discount'=>$order->discount,'tax'=>$order->tax,'total'=>$order->total,'paid'=>$paid,'due'=>$order->total-$paid,'payment_status'=>payment_status($order->total,$paid),'note'=>'Received from '.$order->order_no,'user_id'=>auth()->id(),'branch_id'=>$order->branch_id]);
            $p->update(['reference_no'=>'PUR-'.now()->format('Ymd').'-'.str_pad($p->id,5,'0',STR_PAD_LEFT)]);
            foreach($order->items as $i){$m=Medicine::lockForUpdate()->findOrFail($i->medicine_id);if($i->expiry_date && $i->expiry_date->lt(today()))throw ValidationException::withMessages(['items'=>"Batch for {$m->name} has expired before receiving."]);$pack=max(1,(int)$m->pack_size);$qty=($i->quantity+$i->bonus_qty)*$pack;if(!$m->stock_units_are_base && $m->total_stock>0)throw ValidationException::withMessages(['items'=>"Convert legacy stock for {$m->name} before receiving this order."]);$m->forceFill(['stock_units_are_base'=>true])->save();$batch=$stock->addBatch($m,['purchase_id'=>$p->id,'supplier_id'=>$p->supplier_id,'branch_id'=>$order->branch_id,'batch_no'=>$i->batch_no,'expiry_date'=>$i->expiry_date,'purchase_price'=>round(($i->purchase_price*$i->quantity)/max(1,($i->quantity+$i->bonus_qty))/$pack,4),'sale_price'=>round($i->sale_price/$pack,4),'quantity'=>$qty],$p->reference_no);$p->items()->create(['medicine_id'=>$m->id,'medicine_batch_id'=>$batch->id,'batch_no'=>$i->batch_no,'expiry_date'=>$i->expiry_date,'quantity'=>$i->quantity,'bonus_qty'=>$i->bonus_qty,'purchase_price'=>$i->purchase_price,'sale_price'=>$i->sale_price,'total'=>$i->quantity*$i->purchase_price]);$m->forceFill(['purchase_price'=>$i->purchase_price,'sale_price'=>$i->sale_price])->saveQuietly();}
            if($paid>0){Payment::create(['party_type'=>'supplier','party_id'=>$p->supplier_id,'purchase_id'=>$p->id,'amount'=>$paid,'method'=>$d['payment_method']??'cash','payment_date'=>today(),'note'=>'Purchase order '.$order->order_no,'user_id'=>auth()->id(),'branch_id'=>$order->branch_id]);}
            Supplier::whereKey($p->supplier_id)->increment('balance',$p->due);$order->update(['status'=>'received','received_by'=>auth()->id(),'received_at'=>now()]);app(JournalPostingService::class)->postPurchase($p->load('items'));return $p;
        });
        StockService::flushCache();return response()->json(['message'=>'Purchase order received and stock updated.','data'=>$purchase->load('items')]);
    }
}
