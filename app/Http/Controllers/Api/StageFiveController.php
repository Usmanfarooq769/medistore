<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PurchaseOrder;
use App\Services\BranchContext;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StageFiveController extends Controller
{
    public function __construct(private StockService $stock) {}

    private function branch(): int { return BranchContext::id(); }
    private function manager(): bool { return in_array(auth()->user()?->role, ['admin','branch_manager'], true); }

    public function lookups()
    {
        return response()->json([
            'suppliers'=>DB::table('suppliers')->select('id','name')->orderBy('name')->get(),
            'medicines'=>DB::table('medicines')->where('is_active',true)->select('id','name','strength','requires_controlled_tracking')->orderBy('name')->limit(1000)->get(),
            'batches'=>DB::table('medicine_batches as b')->join('medicines as m','m.id','=','b.medicine_id')->where('b.branch_id',$this->branch())->where('b.quantity','>',0)->select('b.id','b.batch_no','b.quantity','m.name as medicine_name')->orderBy('m.name')->limit(1000)->get(),
            'customers'=>DB::table('customers')->select('id','name','phone','marketing_opt_in')->orderBy('name')->limit(1000)->get(),
            'insurance_providers'=>DB::table('insurance_providers')->where('is_active',true)->select('id','name')->orderBy('name')->get(),
            'insurance_plans'=>DB::table('insurance_plans')->where('is_active',true)->select('id','provider_id','name')->orderBy('name')->get(),
        ]);
    }

    public function setControlledMedicine(Request $request, int $medicine)
    {
        $data=$request->validate(['enabled'=>'required|boolean']);
        Medicine::whereKey($medicine)->update(['requires_controlled_tracking'=>$data['enabled']]);
        return response()->json(['message'=>$data['enabled']?'Controlled-medicine register enabled.':'Controlled-medicine register disabled.']);
    }

    public function overview()
    {
        $branch = $this->branch();
        return response()->json([
            'quotes_pending' => DB::table('supplier_quotations')->where('branch_id',$branch)->where('status','submitted')->count(),
            'stock_counts_open' => DB::table('warehouse_stock_counts')->where('branch_id',$branch)->whereIn('status',['draft','submitted'])->count(),
            'refills_pending' => DB::table('prescription_refills')->where('status','pending')->count(),
            'open_recalls' => DB::table('medicine_recalls')->whereIn('status',['open','in_progress'])->count(),
            'controlled_medicines' => Medicine::where('requires_controlled_tracking',true)->count(),
            'eligible_reminders' => DB::table('prescription_refill_reminders')->where('status','pending')->whereDate('remind_on','<=',today())->count(),
            'facilities' => DB::table('healthcare_facilities')->where('is_active',true)->count(),
        ]);
    }

    // 5.1: supplier quotations and purchasing comparison
    public function quotations(Request $request)
    {
        return DB::table('supplier_quotations as q')->join('suppliers as s','s.id','=','q.supplier_id')
            ->where('q.branch_id',$this->branch())->select('q.*','s.name as supplier_name')
            ->orderByDesc('q.id')->paginate(20);
    }

    public function quotationComparison()
    {
        return DB::table('supplier_quotation_items as qi')->join('supplier_quotations as q','q.id','=','qi.quotation_id')
            ->join('suppliers as s','s.id','=','q.supplier_id')->join('medicines as m','m.id','=','qi.medicine_id')
            ->where('q.branch_id',$this->branch())->whereIn('q.status',['submitted','accepted'])
            ->select('qi.id','q.quote_no','q.status','q.valid_until','s.name as supplier_name','m.id as medicine_id','m.name as medicine_name','m.strength','qi.quantity','qi.unit_price','qi.tax_percent','qi.lead_days')
            ->orderBy('m.name')->orderBy('qi.unit_price')->paginate(50);
    }

    public function storeQuotation(Request $request)
    {
        $data = $request->validate([
            'supplier_id'=>'required|exists:suppliers,id','quote_date'=>'required|date','valid_until'=>'nullable|date|after_or_equal:quote_date',
            'notes'=>'nullable|string|max:2000','items'=>'required|array|min:1','items.*.medicine_id'=>'required|exists:medicines,id',
            'items.*.quantity'=>'required|integer|min:1','items.*.unit_price'=>'required|numeric|min:0','items.*.tax_percent'=>'nullable|numeric|min:0|max:100','items.*.lead_days'=>'nullable|integer|min:0|max:365',
        ]);
        return DB::transaction(function () use ($data) {
            $id = DB::table('supplier_quotations')->insertGetId([
                'quote_no'=>'SQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)), 'supplier_id'=>$data['supplier_id'], 'branch_id'=>$this->branch(),
                'quote_date'=>$data['quote_date'],'valid_until'=>$data['valid_until'] ?? null,'status'=>'submitted','notes'=>$data['notes'] ?? null,
                'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            $subtotal = 0; $tax = 0;
            foreach ($data['items'] as $item) {
                $line = (int)$item['quantity'] * (float)$item['unit_price']; $lineTax = $line * ((float)($item['tax_percent'] ?? 0) / 100);
                $subtotal += $line; $tax += $lineTax;
                DB::table('supplier_quotation_items')->insert([
                    'quotation_id'=>$id,'medicine_id'=>$item['medicine_id'],'quantity'=>$item['quantity'],'unit_price'=>$item['unit_price'],
                    'tax_percent'=>$item['tax_percent'] ?? 0,'lead_days'=>$item['lead_days'] ?? 0,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            DB::table('supplier_quotations')->where('id',$id)->update(['subtotal'=>$subtotal,'tax'=>$tax,'total'=>$subtotal+$tax,'updated_at'=>now()]);
            return response()->json(['message'=>'Supplier quotation submitted.','id'=>$id],201);
        });
    }

    public function quotationReview(Request $request, int $quotation)
    {
        $data = $request->validate(['decision'=>['required',Rule::in(['accepted','rejected'])],'notes'=>'nullable|string|max:2000']);
        $row = DB::table('supplier_quotations')->where('id',$quotation)->where('branch_id',$this->branch())->first();
        if (!$row || $row->status !== 'submitted') throw ValidationException::withMessages(['quotation'=>'Only submitted quotations can be reviewed.']);
        if ($data['decision']==='accepted' && $row->valid_until && $row->valid_until < today()->toDateString()) throw ValidationException::withMessages(['quotation'=>'This supplier quotation has expired.']);
        DB::table('supplier_quotations')->where('id',$quotation)->update(['status'=>$data['decision'],'reviewed_by'=>auth()->id(),'reviewed_at'=>now(),'notes'=>trim(($row->notes ?? '')."\n".($data['notes'] ?? '')),'updated_at'=>now()]);
        return response()->json(['message'=>'Quotation '.$data['decision'].'.']);
    }

    public function quotationConvert(int $quotation)
    {
        $quote = DB::table('supplier_quotations')->where('id',$quotation)->where('branch_id',$this->branch())->first();
        if (!$quote || $quote->status !== 'accepted') throw ValidationException::withMessages(['quotation'=>'Only accepted quotations can be converted.']);
        $items = DB::table('supplier_quotation_items')->where('quotation_id',$quotation)->get();
        if ($items->isEmpty()) throw ValidationException::withMessages(['quotation'=>'Quotation has no items.']);
        return DB::transaction(function () use ($quote,$items,$quotation) {
            $order = PurchaseOrder::create([
                'order_no'=>'PO-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)), 'supplier_id'=>$quote->supplier_id,'branch_id'=>$quote->branch_id,
                'order_date'=>today(),'expected_date'=>null,'status'=>'draft','subtotal'=>$quote->subtotal,'discount'=>0,'tax'=>$quote->tax,'total'=>$quote->total,
                'notes'=>'Converted from supplier quotation '.$quote->quote_no,'created_by'=>auth()->id(),
            ]);
            foreach ($items as $item) {
                $medicine = Medicine::findOrFail($item->medicine_id);
                $order->items()->create(['medicine_id'=>$medicine->id,'quantity'=>$item->quantity,'bonus_qty'=>0,'purchase_price'=>$item->unit_price,'sale_price'=>$medicine->sale_price,'batch_no'=>null,'expiry_date'=>null]);
            }
            DB::table('supplier_quotations')->where('id',$quotation)->update(['status'=>'converted','updated_at'=>now()]);
            return response()->json(['message'=>'Quotation converted to a draft purchase order.','purchase_order_id'=>$order->id],201);
        });
    }

    // 5.1: warehouse bins and batch allocation
    public function locations()
    {
        return DB::table('warehouse_locations')->where('branch_id',$this->branch())->orderBy('code')->get();
    }

    public function storeLocation(Request $request)
    {
        $data = $request->validate(['code'=>'required|string|max:40','name'=>'required|string|max:120','location_type'=>['required',Rule::in(['zone','rack','shelf','bin'])],'parent_id'=>'nullable|integer|exists:warehouse_locations,id']);
        if (!empty($data['parent_id']) && !DB::table('warehouse_locations')->where('id',$data['parent_id'])->where('branch_id',$this->branch())->exists()) throw ValidationException::withMessages(['parent_id'=>'Parent location must belong to the active branch.']);
        $id = DB::table('warehouse_locations')->insertGetId($data + ['branch_id'=>$this->branch(),'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Warehouse location created.','id'=>$id],201);
    }

    public function assignBatchLocation(Request $request)
    {
        $data = $request->validate(['batch_id'=>'required|exists:medicine_batches,id','location_id'=>'required|exists:warehouse_locations,id','quantity'=>'required|integer|min:0']);
        $batch = MedicineBatch::whereKey($data['batch_id'])->where('branch_id',$this->branch())->firstOrFail();
        $location = DB::table('warehouse_locations')->where('id',$data['location_id'])->where('branch_id',$this->branch())->first();
        if (!$location) throw ValidationException::withMessages(['location_id'=>'Location does not belong to the active branch.']);
        $other = DB::table('batch_location_stocks')->where('medicine_batch_id',$batch->id)->where('location_id','!=',$location->id)->sum('quantity');
        if ($other + (int)$data['quantity'] > (int)$batch->quantity) throw ValidationException::withMessages(['quantity'=>'Allocated bin quantity cannot exceed the batch on-hand quantity.']);
        DB::table('batch_location_stocks')->updateOrInsert(['medicine_batch_id'=>$batch->id,'location_id'=>$location->id],['quantity'=>$data['quantity'],'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Batch location updated.']);
    }

    public function stockCounts()
    {
        return DB::table('warehouse_stock_counts')->where('branch_id',$this->branch())->orderByDesc('id')->paginate(20);
    }

    public function storeStockCount(Request $request)
    {
        $data = $request->validate(['notes'=>'nullable|string|max:2000','items'=>'required|array|min:1','items.*.batch_id'=>'required|exists:medicine_batches,id','items.*.counted_quantity'=>'required|integer|min:0']);
        return DB::transaction(function () use ($data) {
            $id = DB::table('warehouse_stock_counts')->insertGetId(['count_no'=>'SC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),'branch_id'=>$this->branch(),'count_date'=>today(),'status'=>'draft','notes'=>$data['notes'] ?? null,'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            foreach ($data['items'] as $item) {
                $batch = MedicineBatch::whereKey($item['batch_id'])->where('branch_id',$this->branch())->firstOrFail();
                DB::table('warehouse_stock_count_items')->insert(['stock_count_id'=>$id,'medicine_batch_id'=>$batch->id,'system_quantity'=>$batch->quantity,'counted_quantity'=>$item['counted_quantity'],'difference'=>(int)$item['counted_quantity']-(int)$batch->quantity,'created_at'=>now(),'updated_at'=>now()]);
            }
            return response()->json(['message'=>'Stock count saved as draft.','id'=>$id],201);
        });
    }

    public function reviewStockCount(Request $request, int $count)
    {
        $data = $request->validate(['decision'=>['required',Rule::in(['approve','cancel'])],'notes'=>'nullable|string|max:1000']);
        return DB::transaction(function () use ($count,$data) {
            $head = DB::table('warehouse_stock_counts')->where('id',$count)->where('branch_id',$this->branch())->lockForUpdate()->first();
            if (!$head || !in_array($head->status,['draft','submitted'],true)) throw ValidationException::withMessages(['count'=>'This stock count cannot be reviewed.']);
            if ($data['decision']==='cancel') {
                DB::table('warehouse_stock_counts')->where('id',$count)->update(['status'=>'cancelled','reviewed_by'=>auth()->id(),'reviewed_at'=>now(),'updated_at'=>now()]);
                return response()->json(['message'=>'Stock count cancelled.']);
            }
            $items = DB::table('warehouse_stock_count_items')->where('stock_count_id',$count)->get();
            foreach ($items as $item) {
                $batch = MedicineBatch::whereKey($item->medicine_batch_id)->where('branch_id',$this->branch())->lockForUpdate()->firstOrFail();
                $delta = (int)$item->counted_quantity - (int)$batch->quantity;
                if ($delta !== 0) {
                    $this->stock->moveBatch($batch,$delta,$delta>0?'adjustment_in':'adjustment_out',$head->count_no,'Approved warehouse stock count');
                    \App\Models\StockAdjustment::create(['medicine_id'=>$batch->medicine_id,'medicine_batch_id'=>$batch->id,'branch_id'=>$this->branch(),'type'=>$delta>0?'add':'subtract','quantity'=>abs($delta),'reason'=>'correction','note'=>'Stock count '.$head->count_no,'user_id'=>auth()->id()]);
                }
            }
            DB::table('warehouse_stock_counts')->where('id',$count)->update(['status'=>'approved','reviewed_by'=>auth()->id(),'reviewed_at'=>now(),'updated_at'=>now()]);
            \App\Services\StockService::flushCache();
            return response()->json(['message'=>'Stock count approved and stock ledger updated.']);
        });
    }

    // 5.2: hospital / clinic directory and prescription refills
    public function facilities()
    {
        return DB::table('healthcare_facilities')->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$this->branch()))->orderBy('name')->get();
    }

    public function saveFacility(Request $request, ?int $facility = null)
    {
        $data = $request->validate(['name'=>'required|string|max:180','facility_type'=>['required',Rule::in(['hospital','clinic','laboratory','other'])],'registration_no'=>'nullable|string|max:100','contact_person'=>'nullable|string|max:120','phone'=>'nullable|string|max:40','email'=>'nullable|email|max:190','address'=>'nullable|string|max:1000','branch_id'=>'nullable|exists:branches,id','is_active'=>'sometimes|boolean']);
        $data['branch_id'] = auth()->user()->isAdmin() && !empty($data['branch_id']) ? (int)$data['branch_id'] : $this->branch();
        if ($facility) {
            DB::table('healthcare_facilities')->where('id',$facility)->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$this->branch()))->update($data+['updated_at'=>now()]);
            return response()->json(['message'=>'Healthcare facility updated.']);
        }
        $id=DB::table('healthcare_facilities')->insertGetId($data+['is_active'=>$data['is_active']??true,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Healthcare facility created.','id'=>$id],201);
    }

    public function createFacilityToken(Request $request,int $facility)
    {
        abort_unless(DB::table('healthcare_facilities')->where('id',$facility)->where('is_active',true)->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$this->branch()))->exists(),404);
        $data=$request->validate(['name'=>'nullable|string|max:120','expires_at'=>'nullable|date|after:now']);
        $plain=Str::random(72);
        DB::table('healthcare_facility_api_tokens')->insert([
            'facility_id'=>$facility,'name'=>$data['name']??'Integration token','token_hash'=>hash('sha256',$plain),
            'created_by'=>auth()->id(),'expires_at'=>$data['expires_at']??null,'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return response()->json(['message'=>'Facility API token created. Copy it now; it will not be shown again.','token'=>$plain],201);
    }

    public function receiveFacilityPrescription(Request $request,int $facility)
    {
        $plain=(string)$request->header('X-Facility-Token');
        abort_if($plain==='',401,'A facility API token is required.');
        $token=DB::table('healthcare_facility_api_tokens')->where('facility_id',$facility)->where('token_hash',hash('sha256',$plain))
            ->where('is_active',true)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->first();
        abort_unless($token,403,'The facility API token is invalid, expired, or revoked.');
        $facilityRow=DB::table('healthcare_facilities')->where('id',$facility)->where('is_active',true)->first();
        abort_unless($facilityRow,404);
        DB::table('healthcare_facility_api_tokens')->where('id',$token->id)->update(['last_used_at'=>now(),'updated_at'=>now()]);
        $data=$request->validate([
            'customer_id'=>'nullable|exists:customers,id','doctor_name'=>'required|string|max:150','doctor_license'=>'nullable|string|max:100',
            'prescription_date'=>'required|date','notes'=>'nullable|string|max:2000','items'=>'required|array|min:1',
            'items.*.medicine_id'=>'nullable|exists:medicines,id','items.*.medicine_text'=>'nullable|string|max:180',
            'items.*.quantity'=>'required|numeric|min:0.01','items.*.dosage'=>'nullable|string|max:120',
            'items.*.duration'=>'nullable|string|max:100','items.*.instructions'=>'nullable|string|max:1000',
        ]);
        $prescription=DB::transaction(function()use($data,$facility,$token){
            $p=\App\Models\Prescription::create([
                'prescription_no'=>'RX-EXT-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
                'customer_id'=>$data['customer_id']??null,'doctor_name'=>$data['doctor_name'],'doctor_license'=>$data['doctor_license']??null,
                'prescription_date'=>$data['prescription_date'],'status'=>'pending','notes'=>$data['notes']??null,
                'created_by'=>$token->created_by,'healthcare_facility_id'=>$facility,
            ]);
            foreach($data['items'] as $item) $p->items()->create($item);
            DB::table('prescription_events')->insert(['prescription_id'=>$p->id,'event'=>'received_from_facility_api','notes'=>'Submitted through facility integration API.','user_id'=>$token->created_by,'created_at'=>now(),'updated_at'=>now()]);
            return $p;
        });
        return response()->json(['message'=>'Prescription received and queued for pharmacist review.','prescription_no'=>$prescription->prescription_no,'id'=>$prescription->id],201);
    }

    public function facilityTokens(int $facility)
    {
        abort_unless(DB::table('healthcare_facilities')->where('id',$facility)->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$this->branch()))->exists(),404);
        return DB::table('healthcare_facility_api_tokens')->where('facility_id',$facility)
            ->select('id','facility_id','name','last_used_at','expires_at','is_active','created_at')->orderByDesc('id')->get();
    }

    public function revokeFacilityToken(int $facility,int $token)
    {
        abort_unless(DB::table('healthcare_facilities')->where('id',$facility)->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$this->branch()))->exists(),404);
        $updated=DB::table('healthcare_facility_api_tokens')->where('id',$token)->where('facility_id',$facility)->update(['is_active'=>false,'updated_at'=>now()]);
        abort_unless($updated,404);
        return response()->json(['message'=>'Facility API token revoked.']);
    }

    public function refills()
    {
        return DB::table('prescription_refills as r')->leftJoin('customers as c','c.id','=','r.customer_id')->join('medicines as m','m.id','=','r.medicine_id')->where(function($q){$q->whereNull('r.customer_id')->orWhere('r.customer_id','>',0);})
            ->select('r.*','c.name as customer_name','m.name as medicine_name')->orderByDesc('r.id')->paginate(20);
    }

    public function requestRefill(Request $request)
    {
        $data=$request->validate(['prescription_id'=>'required|exists:prescriptions,id','medicine_id'=>'required|exists:medicines,id','requested_quantity'=>'required|integer|min:1','customer_note'=>'nullable|string|max:1000']);
        return DB::transaction(function () use ($data) {
            $rx=DB::table('prescriptions')->where('id',$data['prescription_id'])->whereIn('status',['approved','dispensed'])->lockForUpdate()->first();
            if (!$rx) throw ValidationException::withMessages(['prescription_id'=>'A reviewed prescription is required for a refill request.']);
            $rxItem=DB::table('prescription_items')->where('prescription_id',$rx->id)->where('medicine_id',$data['medicine_id'])->first();
            if (!$rxItem) throw ValidationException::withMessages(['medicine_id'=>'This medicine is not listed on the selected prescription.']);
            $alreadyRequested=(int)DB::table('prescription_refills')->where('prescription_id',$rx->id)->where('medicine_id',$data['medicine_id'])->whereIn('status',['pending','approved','dispensed'])->sum('requested_quantity');
            $alreadyDispensed=(int)DB::table('sale_items as si')->join('sales as s','s.id','=','si.sale_id')->where('s.prescription_id',$rx->id)->where('si.medicine_id',$data['medicine_id'])->sum('si.quantity');
            if ($alreadyRequested + $alreadyDispensed + (int)$data['requested_quantity'] > (float)$rxItem->quantity) throw ValidationException::withMessages(['requested_quantity'=>'Requested refill quantity exceeds the remaining quantity authorized on the prescription.']);
            $id=DB::table('prescription_refills')->insertGetId([
                'request_no'=>'RF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),'prescription_id'=>$rx->id,'customer_id'=>$rx->customer_id,
                'medicine_id'=>$data['medicine_id'],'requested_quantity'=>$data['requested_quantity'],'status'=>'pending','customer_note'=>$data['customer_note']??null,
                'requested_by'=>auth()->id(),'requested_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('prescription_events')->insert(['prescription_id'=>$rx->id,'event'=>'refill_requested','notes'=>'Refill request '.$id.' submitted.','user_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            return response()->json(['message'=>'Refill request submitted for pharmacist review.','id'=>$id],201);
        });
    }

    public function reviewRefill(Request $request,int $refill)
    {
        $data=$request->validate(['decision'=>['required',Rule::in(['approved','rejected'])],'review_note'=>'nullable|string|max:1000']);
        return DB::transaction(function () use ($refill,$data) {
            $row=DB::table('prescription_refills')->where('id',$refill)->lockForUpdate()->first();
            if (!$row || $row->status!=='pending') throw ValidationException::withMessages(['refill'=>'Only pending refill requests can be reviewed.']);
            $approvedPrescriptionId = null;
            if ($data['decision'] === 'approved') {
                $original=\App\Models\Prescription::with('items')->findOrFail($row->prescription_id);
                $sourceItem=$original->items->firstWhere('medicine_id',$row->medicine_id);
                if (!$sourceItem) throw ValidationException::withMessages(['refill'=>'The source prescription item is no longer available.']);
                $newRx=\App\Models\Prescription::create([
                    'prescription_no'=>'RX-REFILL-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
                    'customer_id'=>$row->customer_id,'doctor_name'=>$original->doctor_name,'doctor_license'=>$original->doctor_license,
                    'doctor_id'=>$original->doctor_id,'clinic_id'=>$original->clinic_id,'referral_id'=>$original->referral_id,
                    'healthcare_facility_id'=>$original->healthcare_facility_id,'prescription_date'=>today(),'status'=>'approved',
                    'notes'=>'Approved refill from '.$original->prescription_no.'. '.($data['review_note']??''),'created_by'=>auth()->id(),
                    'reviewed_by'=>auth()->id(),'reviewed_at'=>now(),
                ]);
                $newRx->items()->create(['medicine_id'=>$row->medicine_id,'medicine_text'=>$sourceItem->medicine_text,'quantity'=>$row->requested_quantity,'dosage'=>$sourceItem->dosage,'duration'=>$sourceItem->duration,'instructions'=>$sourceItem->instructions]);
                $approvedPrescriptionId=$newRx->id;
            }
            DB::table('prescription_refills')->where('id',$refill)->update([
                'status'=>$data['decision'],'approved_prescription_id'=>$approvedPrescriptionId,'review_note'=>$data['review_note']??null,
                'reviewed_by'=>auth()->id(),'reviewed_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('prescription_events')->insert([
                'prescription_id'=>$row->prescription_id,'event'=>'refill_'.$data['decision'],
                'notes'=>'Refill request '.$row->request_no.' '.$data['decision'].($approvedPrescriptionId?' and created approved prescription ID '.$approvedPrescriptionId:''),
                'user_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            return response()->json(['message'=>'Refill request '.$data['decision'].'.','approved_prescription_id'=>$approvedPrescriptionId]);
        });
    }

    // 5.3: insurance eligibility and claim workflow audit events
    public function eligibilityChecks()
    {
        return DB::table('insurance_eligibility_checks as e')->join('insurance_providers as p','p.id','=','e.provider_id')->leftJoin('customers as c','c.id','=','e.customer_id')->where('e.branch_id',$this->branch())->select('e.*','p.name as provider_name','c.name as customer_name')->orderByDesc('e.id')->paginate(20);
    }

    public function checkEligibility(Request $request)
    {
        $data=$request->validate(['provider_id'=>'required|exists:insurance_providers,id','plan_id'=>'nullable|exists:insurance_plans,id','customer_id'=>'nullable|exists:customers,id','member_no'=>'required|string|max:100','result'=>['required',Rule::in(['eligible','ineligible','manual_review'])],'notes'=>'nullable|string|max:1000']);
        if (!empty($data['plan_id']) && !DB::table('insurance_plans')->where('id',$data['plan_id'])->where('provider_id',$data['provider_id'])->exists()) throw ValidationException::withMessages(['plan_id'=>'Selected plan does not belong to the selected insurer.']);
        if (!empty($data['customer_id'])) {
            $customer=DB::table('customers')->where('id',$data['customer_id'])->first();
            if ($customer->insurance_provider_id && (int)$customer->insurance_provider_id !== (int)$data['provider_id']) throw ValidationException::withMessages(['customer_id'=>'Selected insurer does not match the customer insurance profile.']);
            if ($customer->insurance_plan_id && !empty($data['plan_id']) && (int)$customer->insurance_plan_id !== (int)$data['plan_id']) throw ValidationException::withMessages(['plan_id'=>'Selected plan does not match the customer insurance profile.']);
            if ($customer->insurance_member_no && $customer->insurance_member_no !== $data['member_no']) throw ValidationException::withMessages(['member_no'=>'Member number does not match the customer insurance profile.']);
        }
        $id=DB::table('insurance_eligibility_checks')->insertGetId($data+['branch_id'=>$this->branch(),'checked_on'=>today(),'checked_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Eligibility check recorded. External payer verification must be completed through your insurer integration.','id'=>$id],201);
    }

    public function claimEvents(int $claim)
    {
        abort_unless(DB::table('insurance_claims')->where('id',$claim)->where('branch_id',$this->branch())->exists(),404);
        return DB::table('insurance_claim_events')->where('claim_id',$claim)->orderBy('id')->get();
    }

    public function recordClaimEvent(Request $request,int $claim)
    {
        $data=$request->validate(['event'=>'required|string|max:60','external_reference'=>'nullable|string|max:100','amount'=>'nullable|numeric|min:0','notes'=>'nullable|string|max:2000']);
        abort_unless(DB::table('insurance_claims')->where('id',$claim)->where('branch_id',$this->branch())->exists(),404);
        $id=DB::table('insurance_claim_events')->insertGetId($data+['claim_id'=>$claim,'user_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Insurance claim event recorded.','id'=>$id],201);
    }

    // 5.3: controlled medicine register and recall quarantine
    public function controlledRegister(Request $request)
    {
        return DB::table('controlled_medicine_register as r')->join('medicines as m','m.id','=','r.medicine_id')->where('r.branch_id',$this->branch())
            ->when($request->medicine_id,fn($q,$v)=>$q->where('r.medicine_id',$v))->select('r.*','m.name as medicine_name')->orderByDesc('r.id')->paginate(30);
    }

    public function recordControlledMovement(Request $request)
    {
        $data=$request->validate(['medicine_id'=>'required|exists:medicines,id','batch_id'=>'nullable|exists:medicine_batches,id','transaction_type'=>['required',Rule::in(['purchase','dispense','return','adjustment','transfer_in','transfer_out'])],'quantity_change'=>'required|integer|not_in:0','balance_after'=>'nullable|integer|min:0','reference_no'=>'nullable|string|max:80','customer_id'=>'nullable|exists:customers,id','prescription_id'=>'nullable|exists:prescriptions,id','reason'=>'required|string|max:1000']);
        if (!Medicine::whereKey($data['medicine_id'])->where('requires_controlled_tracking',true)->exists()) throw ValidationException::withMessages(['medicine_id'=>'Enable controlled tracking on this medicine before recording register movements.']);
        if (!empty($data['batch_id']) && !MedicineBatch::whereKey($data['batch_id'])->where('medicine_id',$data['medicine_id'])->where('branch_id',$this->branch())->exists()) throw ValidationException::withMessages(['batch_id'=>'Batch does not belong to this medicine and branch.']);
        $id=DB::table('controlled_medicine_register')->insertGetId($data+['branch_id'=>$this->branch(),'recorded_by'=>auth()->id(),'recorded_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Controlled medicine register entry recorded.','id'=>$id],201);
    }

    public function recalls()
    {
        return DB::table('medicine_recalls')->orderByDesc('id')->paginate(20);
    }

    public function createRecall(Request $request)
    {
        $data=$request->validate(['source'=>'nullable|string|max:120','severity'=>['required',Rule::in(['notice','warning','urgent'])],'reason'=>'required|string|max:3000','issued_on'=>'required|date','items'=>'required|array|min:1','items.*.medicine_id'=>'required|exists:medicines,id','items.*.batch_id'=>'nullable|exists:medicine_batches,id','items.*.affected_quantity'=>'nullable|integer|min:0']);
        return DB::transaction(function() use($data){
            $id=DB::table('medicine_recalls')->insertGetId(['recall_no'=>'REC-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),'source'=>$data['source']??null,'severity'=>$data['severity'],'reason'=>$data['reason'],'status'=>'open','issued_on'=>$data['issued_on'],'created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
            foreach($data['items'] as $item){
                $batchId=$item['batch_id']??null;
                $batches = $batchId
                    ? MedicineBatch::whereKey($batchId)->where('medicine_id',$item['medicine_id'])->where('branch_id',$this->branch())->lockForUpdate()->get()
                    : MedicineBatch::where('medicine_id',$item['medicine_id'])->where('branch_id',$this->branch())->where('quantity','>',0)->lockForUpdate()->get();
                if ($batches->isEmpty()) {
                    DB::table('medicine_recall_items')->insert(['recall_id'=>$id,'medicine_id'=>$item['medicine_id'],'batch_id'=>null,'affected_quantity'=>0,'quarantine_applied'=>false,'created_at'=>now(),'updated_at'=>now()]);
                    continue;
                }
                foreach ($batches as $batch) {
                    $batch->update(['is_quarantined'=>true]);
                    DB::table('medicine_recall_items')->insert(['recall_id'=>$id,'medicine_id'=>$item['medicine_id'],'batch_id'=>$batch->id,'affected_quantity'=>!empty($item['affected_quantity'])?(int)$item['affected_quantity']:(int)$batch->quantity,'quarantine_applied'=>true,'created_at'=>now(),'updated_at'=>now()]);
                }
            }
            return response()->json(['message'=>'Recall created. Selected batches have been quarantined from POS dispensing.','id'=>$id],201);
        });
    }

    public function recallItems()
    {
        return DB::table('medicine_recall_items as i')->join('medicine_recalls as r','r.id','=','i.recall_id')
            ->join('medicines as m','m.id','=','i.medicine_id')->leftJoin('medicine_batches as b','b.id','=','i.batch_id')
            ->where(fn($q)=>$q->whereNull('b.branch_id')->orWhere('b.branch_id',$this->branch()))
            ->select('i.*','r.recall_no','m.name as medicine_name','b.batch_no','b.quantity as current_quantity','b.is_quarantined')
            ->orderByDesc('i.id')->paginate(30);
    }

    public function closeRecall(int $recall)
    {
        $row=DB::table('medicine_recalls')->where('id',$recall)->first(); abort_unless($row,404);
        DB::table('medicine_recalls')->where('id',$recall)->update(['status'=>'closed','updated_at'=>now()]);
        return response()->json(['message'=>'Recall marked closed. Quarantined batches remain blocked until explicitly released by an administrator.']);
    }

    public function releaseRecallBatch(Request $request,int $item)
    {
        $data=$request->validate(['release_reason'=>'required|string|max:1000']);
        return DB::transaction(function() use($item,$data){
            $row=DB::table('medicine_recall_items')->where('id',$item)->lockForUpdate()->first(); abort_unless($row,404);
            if($row->batch_id) MedicineBatch::whereKey($row->batch_id)->where('branch_id',$this->branch())->firstOrFail()->update(['is_quarantined'=>false]);
            DB::table('medicine_recall_items')->where('id',$item)->update(['quarantine_applied'=>false,'updated_at'=>now()]);
            $recall=DB::table('medicine_recalls')->where('id',$row->recall_id)->first();
            DB::table('medicine_recalls')->where('id',$row->recall_id)->update(['reason'=>$recall->reason."\nRelease: ".$data['release_reason'],'updated_at'=>now()]);
            return response()->json(['message'=>'Batch quarantine released.']);
        });
    }

    // 5.4: campaigns and reminders. Actual outbound delivery remains through configured provider integrations.
    public function setCustomerMarketingConsent(Request $request,int $customer)
    {
        $data=$request->validate(['opted_in'=>'required|boolean']);
        abort_unless(DB::table('customers')->where('id',$customer)->exists(),404);
        DB::table('customers')->where('id',$customer)->update(['marketing_opt_in'=>$data['opted_in'],'marketing_opt_in_at'=>$data['opted_in']?now():null,'updated_at'=>now()]);
        return response()->json(['message'=>$data['opted_in']?'Marketing consent recorded.':'Marketing consent withdrawn.']);
    }

    public function campaigns()
    {
        return DB::table('customer_campaigns')->orderByDesc('id')->paginate(20);
    }

    public function saveCampaign(Request $request, ?int $campaign=null)
    {
        $data=$request->validate(['name'=>'required|string|max:160','campaign_type'=>['required',Rule::in(['refill_reminder','loyalty','promotion','general'])],'message'=>'required|string|max:5000','status'=>['required',Rule::in(['draft','scheduled','active','completed','cancelled'])],'starts_at'=>'nullable|date','ends_at'=>'nullable|date|after_or_equal:starts_at']);
        if($campaign){DB::table('customer_campaigns')->where('id',$campaign)->update($data+['updated_at'=>now()]);return response()->json(['message'=>'Campaign updated.']);}
        $id=DB::table('customer_campaigns')->insertGetId($data+['created_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Campaign created.','id'=>$id],201);
    }

    public function sendCampaign(Request $request,int $campaign)
    {
        $data=$request->validate(['customer_ids'=>'required|array|min:1|max:100','customer_ids.*'=>'integer|exists:customers,id']);
        $template=config('services.whatsapp.campaign_template');
        if(!$template) throw ValidationException::withMessages(['campaign'=>'Set WHATSAPP_CAMPAIGN_TEMPLATE to an approved WhatsApp template before sending.']);
        $campaignRow=DB::table('customer_campaigns')->where('id',$campaign)->whereIn('status',['active','scheduled'])->first();
        if(!$campaignRow) throw ValidationException::withMessages(['campaign'=>'Only active or scheduled campaigns can be sent.']);
        if ($campaignRow->starts_at && now()->lt($campaignRow->starts_at)) throw ValidationException::withMessages(['campaign'=>'This campaign has not reached its start time.']);
        if ($campaignRow->ends_at && now()->gt($campaignRow->ends_at)) throw ValidationException::withMessages(['campaign'=>'This campaign has expired.']);
        $service=app(\App\Services\WhatsAppCloudService::class); $sent=0; $failed=0; $skipped=0;
        foreach(array_unique($data['customer_ids']) as $customerId){
            $customer=DB::table('customers')->where('id',$customerId)->first();
            if(!$customer || !$customer->phone){$failed++;continue;}
            $existing=DB::table('customer_campaign_deliveries')->where('campaign_id',$campaign)->where('customer_id',$customerId)->where('channel','whatsapp')->first();
            if($existing && $existing->status==='sent') continue;
            if (!(bool)$customer->marketing_opt_in) {
                DB::table('customer_campaign_deliveries')->updateOrInsert(['campaign_id'=>$campaign,'customer_id'=>$customerId,'channel'=>'whatsapp'],['status'=>'skipped','error_message'=>'Customer has not opted in to marketing messages.','updated_at'=>now(),'created_at'=>now()]);
                $skipped++; continue;
            }
            $deliveryId=$existing?->id;
            if($deliveryId) DB::table('customer_campaign_deliveries')->where('id',$deliveryId)->update(['status'=>'queued','error_message'=>null,'updated_at'=>now()]);
            else $deliveryId=DB::table('customer_campaign_deliveries')->insertGetId(['campaign_id'=>$campaign,'customer_id'=>$customerId,'channel'=>'whatsapp','status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
            try{
                $service->sendTemplate($customer->phone,$template,[$customer->name,$campaignRow->message],'stage5_customer_campaign');
                DB::table('customer_campaign_deliveries')->where('id',$deliveryId)->update(['status'=>'sent','sent_at'=>now(),'updated_at'=>now()]); $sent++;
            }catch(\Throwable $e){
                DB::table('customer_campaign_deliveries')->where('id',$deliveryId)->update(['status'=>'failed','error_message'=>mb_substr($e->getMessage(),0,2000),'updated_at'=>now()]); $failed++;
            }
        }
        return response()->json(['message'=>'Campaign delivery run completed.','sent'=>$sent,'failed'=>$failed,'skipped'=>$skipped]);
    }

    public function seasonality(Request $request)
    {
        $months=max(6,min(36,(int)$request->input('months',24))); $from=today()->startOfMonth()->subMonths($months-1);
        $rows=DB::table('sales')->where('branch_id',$this->branch())->whereBetween('sale_date',[$from,today()])
            ->selectRaw('YEAR(sale_date) as year, MONTH(sale_date) as month, COUNT(*) as invoices, SUM(total) as revenue, SUM(paid) as collected')
            ->groupByRaw('YEAR(sale_date), MONTH(sale_date)')->orderByRaw('YEAR(sale_date), MONTH(sale_date)')->get();
        return response()->json(['months'=>$months,'data'=>$rows]);
    }

    public function reminders()
    {
        return DB::table('prescription_refill_reminders as r')->join('customers as c','c.id','=','r.customer_id')->join('medicines as m','m.id','=','r.medicine_id')->select('r.*','c.name as customer_name','c.phone','m.name as medicine_name')->orderBy('remind_on')->paginate(30);
    }

    public function createReminder(Request $request)
    {
        $data=$request->validate(['customer_id'=>'required|exists:customers,id','medicine_id'=>'required|exists:medicines,id','sale_id'=>'nullable|exists:sales,id','last_dispensed_on'=>'required|date','remind_on'=>'required|date|after_or_equal:last_dispensed_on']);
        $id=DB::table('prescription_refill_reminders')->insertGetId($data+['status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Refill reminder scheduled.','id'=>$id],201);
    }

    public function registerPushSubscription(Request $request)
    {
        $data=$request->validate(['endpoint'=>'required|url|max:4000','keys.p256dh'=>'nullable|string|max:500','keys.auth'=>'nullable|string|max:500','content_encoding'=>'nullable|string|max:40']);
        DB::table('mobile_push_subscriptions')->updateOrInsert(['user_id'=>auth()->id(),'endpoint_hash'=>hash('sha256',$data['endpoint'])],['endpoint'=>$data['endpoint'],'public_key'=>$data['keys']['p256dh']??null,'auth_token'=>$data['keys']['auth']??null,'content_encoding'=>$data['content_encoding']??'aes128gcm','is_active'=>true,'updated_at'=>now(),'created_at'=>now()]);
        return response()->json(['message'=>'This device is registered for notifications.']);
    }

    // 5.4: simple transparent demand forecast from recent completed sales; no ML claims.
    public function forecast(Request $request)
    {
        $days=max(30,min(365,(int)$request->input('history_days',90))); $horizon=max(7,min(90,(int)$request->input('forecast_days',30))); $from=today()->subDays($days);
        $branch=$this->branch();
        $rows=DB::table('sale_items as si')->join('sales as s','s.id','=','si.sale_id')->leftJoin('medicines as m','m.id','=','si.medicine_id')
            ->where('s.branch_id',$branch)->whereBetween('s.sale_date',[$from,today()])->whereNotNull('si.medicine_id')
            ->select('si.medicine_id','m.name','m.sale_price','m.purchase_price','m.reorder_level',DB::raw('SUM(si.quantity) as sold_qty'),DB::raw('SUM(si.total) as revenue'),DB::raw('SUM(si.quantity * si.cost_price) as cost_total'))
            ->groupBy('si.medicine_id','m.name','m.sale_price','m.purchase_price','m.reorder_level')->orderByDesc('sold_qty')->limit(100)->get();
        $items=$rows->map(function($r)use($days,$horizon,$branch){
            $daily=(float)$r->sold_qty/$days; $forecast=round($daily*$horizon,2); $stock=(int)DB::table('medicine_batches')->where('medicine_id',$r->medicine_id)->where('branch_id',$branch)->where('quantity','>',0)->where(fn($q)=>$q->whereNull('expiry_date')->orWhereDate('expiry_date','>=',today()))->where('is_quarantined',false)->sum('quantity');
            $suggest=max(0,(int)ceil($forecast+(float)$r->reorder_level-$stock)); $avgPrice=$r->sold_qty>0?(float)$r->revenue/(float)$r->sold_qty:(float)$r->sale_price; $avgCost=$r->sold_qty>0?(float)$r->cost_total/(float)$r->sold_qty:(float)$r->purchase_price;
            return ['medicine_id'=>$r->medicine_id,'medicine_name'=>$r->name,'historical_daily_average'=>round($daily,3),'forecast_quantity'=>$forecast,'current_sellable_stock'=>$stock,'suggested_purchase_qty'=>$suggest,'forecast_revenue'=>round($forecast*$avgPrice,2),'forecast_gross_profit'=>round($forecast*max(0,$avgPrice-$avgCost),2)];
        });
        return response()->json(['method'=>'Simple average daily demand over selected history window; not a statistical or clinical prediction.','history_days'=>$days,'forecast_days'=>$horizon,'items'=>$items]);
    }

    public function saveForecast(Request $request)
    {
        $data=$request->validate(['period_start'=>'required|date','period_end'=>'required|date|after_or_equal:period_start','forecast_type'=>['required',Rule::in(['sales','demand','profit'])]]);
        $result=$this->forecast(new Request(['history_days'=>90,'forecast_days'=>max(7,min(90,\Carbon\Carbon::parse($data['period_start'])->diffInDays(\Carbon\Carbon::parse($data['period_end']))+1))]));
        $payload=$result->getData(true); $id=DB::table('analytics_forecast_runs')->insertGetId(['branch_id'=>$this->branch(),'period_start'=>$data['period_start'],'period_end'=>$data['period_end'],'forecast_type'=>$data['forecast_type'],'summary'=>json_encode(['method'=>$payload['method'],'items_count'=>count($payload['items'])]),'generated_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        foreach($payload['items'] as $item) DB::table('analytics_forecast_items')->insert(['run_id'=>$id,'medicine_id'=>$item['medicine_id'],'label'=>$item['medicine_name'],'historical_daily_average'=>$item['historical_daily_average'],'forecast_quantity'=>$item['forecast_quantity'],'forecast_revenue'=>$item['forecast_revenue'],'forecast_gross_profit'=>$item['forecast_gross_profit'],'suggested_purchase_qty'=>$item['suggested_purchase_qty'],'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Forecast snapshot saved.','id'=>$id]);
    }
}
