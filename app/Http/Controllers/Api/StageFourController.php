<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Branch, Clinic, Doctor, DoctorReferral, ExpiryReturnClaim, ExpiryReturnClaimItem, InsuranceClaim, InsuranceClaimItem, InsuranceCoverage, InsurancePlan, InsuranceProvider, ManufacturerScheme, Medicine, MedicineBatch, SupplierCreditNote};
use App\Services\{BranchContext, JournalPostingService, StockService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StageFourController extends Controller
{
    private function assertBranchRecord($record): void
    {
        abort_unless(auth()->user()->isAdmin() || (int) $record->branch_id === BranchContext::id(), 404);
    }


    public function lookups()
    {
        $branch=BranchContext::id();
        return response()->json([
            'manufacturers'=>DB::table('manufacturers')->orderBy('name')->get(['id','name']),
            'medicines'=>DB::table('medicines')->where('is_active',1)->orderBy('name')->limit(500)->get(['id','name']),
            'suppliers'=>DB::table('suppliers')->orderBy('name')->get(['id','name']),
            'expiring_batches'=>DB::table('medicine_batches')->join('medicines','medicines.id','=','medicine_batches.medicine_id')
                ->where('medicine_batches.branch_id',$branch)->where('medicine_batches.quantity','>',0)
                ->whereNotNull('medicine_batches.expiry_date')->where('medicine_batches.expiry_date','<=',today()->addMonths(6))
                ->orderBy('medicine_batches.expiry_date')->limit(300)
                ->get(['medicine_batches.id','medicine_batches.batch_no','medicine_batches.quantity','medicine_batches.expiry_date','medicine_batches.supplier_id','medicines.name as medicine_name']),
        ]);
    }

    /* -------------------- HEAD OFFICE / CHAIN -------------------- */
    public function chainOverview(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $from = $request->date('from')?->toDateString() ?? now()->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? today()->toDateString();
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get()->map(function ($branch) use ($from, $to) {
            $sales = DB::table('sales')->where('branch_id',$branch->id)->whereBetween('sale_date',[$from,$to]);
            $stock = DB::table('medicine_batches')->where('branch_id',$branch->id)->where(fn($q)=>$q->whereNull('expiry_date')->orWhere('expiry_date','>=',today()))->sum('quantity');
            $cost=DB::table('sale_items as i')->join('sales as s','s.id','=','i.sale_id')->where('s.branch_id',$branch->id)->whereBetween('s.sale_date',[$from,$to])
                ->selectRaw('SUM(i.price*(i.quantity-i.returned_qty)) as revenue,SUM(i.cost_price*(i.quantity-i.returned_qty)) as cost')->first();
            $expenses=(float)DB::table('expenses')->where('branch_id',$branch->id)->where('status','approved')->whereBetween('expense_date',[$from,$to])->sum('amount');
            $purchases=(float)DB::table('purchases')->where('branch_id',$branch->id)->whereBetween('purchase_date',[$from,$to])->sum('total');
            return [
                'id'=>$branch->id,'name'=>$branch->name,'code'=>$branch->code,
                'sales_count'=>(clone $sales)->count(),
                'sales_total'=>(float)(clone $sales)->sum('total'),
                'sales_paid'=>(float)(clone $sales)->sum('paid'),
                'sales_due'=>(float)(clone $sales)->sum('due'),
                'purchase_total'=>$purchases,'expenses_total'=>$expenses,
                'gross_profit'=>round((float)($cost->revenue??0)-(float)($cost->cost??0),2),
                'stock_units'=>(int)$stock,
            ];
        });
        return response()->json([
            'from'=>$from,'to'=>$to,'branches'=>$branches,
            'totals'=>[
                'sales_count'=>$branches->sum('sales_count'),'sales_total'=>round($branches->sum('sales_total'),2),
                'sales_paid'=>round($branches->sum('sales_paid'),2),'sales_due'=>round($branches->sum('sales_due'),2),
                'purchase_total'=>round($branches->sum('purchase_total'),2),'expenses_total'=>round($branches->sum('expenses_total'),2),
                'gross_profit'=>round($branches->sum('gross_profit'),2),'stock_units'=>$branches->sum('stock_units'),
            ],
        ]);
    }

    /* -------------------- DOCTORS / CLINICS / REFERRALS -------------------- */
    public function doctors(Request $r) {
        return Doctor::with('clinics:id,name')->when($r->boolean('active'),fn($q)=>$q->where('is_active',1))->orderBy('name')->paginate(30);
    }
    public function saveDoctor(Request $r, ?Doctor $doctor = null) {
        $data=$r->validate(['name'=>'required|string|max:150','license_no'=>['nullable','string','max:100',Rule::unique('doctors','license_no')->ignore($doctor?->id)],
            'specialty'=>'nullable|string|max:120','phone'=>'nullable|string|max:40','email'=>'nullable|email|max:190','address'=>'nullable|string','is_active'=>'sometimes|boolean','clinic_ids'=>'array','clinic_ids.*'=>'integer|exists:clinics,id']);
        $ids=$data['clinic_ids']??[]; unset($data['clinic_ids']);
        if (!auth()->user()->isAdmin() && count($ids)) abort_unless(!DB::table('clinics')->whereIn('id',$ids)->whereNotNull('branch_id')->where('branch_id','!=',BranchContext::id())->exists(),403,'One or more clinics belong to another branch.');
        $doctor ? $doctor->update($data) : $doctor=Doctor::create($data);
        if ($r->has('clinic_ids')) $doctor->clinics()->sync($ids);
        return response()->json($doctor->load('clinics'),$doctor->wasRecentlyCreated?201:200);
    }
    public function deleteDoctor(Doctor $doctor) { abort_if($doctor->prescriptions()->exists() || $doctor->referrals()->exists(),422,'Doctor has linked prescriptions or referrals.'); $doctor->delete(); return response()->json(['message'=>'Doctor deleted.']); }
    public function clinics(Request $r) { return Clinic::with('doctors:id,name')->when(!auth()->user()->isAdmin(),fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',BranchContext::id()))->orderBy('name')->paginate(30); }
    public function saveClinic(Request $r, ?Clinic $clinic = null) {
        $data=$r->validate(['name'=>'required|string|max:180','phone'=>'nullable|string|max:40','email'=>'nullable|email','address'=>'nullable|string','branch_id'=>'nullable|exists:branches,id','is_active'=>'sometimes|boolean','doctor_ids'=>'array','doctor_ids.*'=>'integer|exists:doctors,id']);
        $ids=$data['doctor_ids']??[]; unset($data['doctor_ids']);
        if (!auth()->user()->isAdmin()) $data['branch_id']=BranchContext::id();
        $clinic ? $clinic->update($data) : $clinic=Clinic::create($data);
        if ($r->has('doctor_ids')) $clinic->doctors()->sync($ids);
        return response()->json($clinic->load('doctors'),$clinic->wasRecentlyCreated?201:200);
    }
    public function referrals(Request $r) {
        return DoctorReferral::with(['doctor:id,name,specialty','clinic:id,name','customer:id,name','creator:id,name'])
            ->when(!auth()->user()->isAdmin(),fn($q)=>$q->where('branch_id',BranchContext::id()))
            ->latest('referred_at')->paginate(30);
    }
    public function createReferral(Request $r) {
        $d=$r->validate(['doctor_id'=>'required|exists:doctors,id','clinic_id'=>'nullable|exists:clinics,id','customer_id'=>'nullable|exists:customers,id','sale_id'=>'nullable|exists:sales,id','referred_at'=>'required|date','notes'=>'nullable|string']);
        if (!empty($d['clinic_id']) && !auth()->user()->isAdmin()) abort_unless(Clinic::whereKey($d['clinic_id'])->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',BranchContext::id()))->exists(),404);
        if (!empty($d['sale_id'])) abort_unless(DB::table('sales')->where('id',$d['sale_id'])->where('branch_id',BranchContext::id())->exists() || auth()->user()->isAdmin(),404);
        if (!empty($d['clinic_id'])) abort_unless(DB::table('clinic_doctor')->where('doctor_id',$d['doctor_id'])->where('clinic_id',$d['clinic_id'])->exists(),422,'The selected doctor is not linked to this clinic.');
        $d['branch_id']=BranchContext::id(); $d['created_by']=auth()->id(); $d['reference_no']='REF-'.now()->format('ymd').'-'.strtoupper(str()->random(6));
        return response()->json(DoctorReferral::create($d)->load('doctor','clinic','customer'),201);
    }

    /* -------------------- MANUFACTURER SCHEMES -------------------- */
    public function schemes() { return ManufacturerScheme::with(['manufacturer:id,name','supplier:id,name','medicine:id,name'])->when(!auth()->user()->isAdmin(),fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',BranchContext::id()))->latest()->paginate(30); }
    public function saveScheme(Request $r, ?ManufacturerScheme $scheme = null) {
        $d=$r->validate(['name'=>'required|string|max:180','manufacturer_id'=>'nullable|exists:manufacturers,id','supplier_id'=>'nullable|exists:suppliers,id','medicine_id'=>'nullable|exists:medicines,id','branch_id'=>'nullable|exists:branches,id',
            'scheme_type'=>'required|in:buy_x_get_y,discount','buy_quantity'=>'required|integer|min:1','free_quantity'=>'nullable|integer|min:0','discount_percent'=>'nullable|numeric|min:0|max:100',
            'valid_from'=>'required|date','valid_to'=>'required|date|after_or_equal:valid_from','max_free_quantity'=>'nullable|integer|min:1','is_active'=>'sometimes|boolean','terms'=>'nullable|string']);
        if (!auth()->user()->isAdmin()) $d['branch_id']=BranchContext::id();
        $scheme ? $scheme->update($d) : $scheme=ManufacturerScheme::create($d);
        return response()->json($scheme->load(['manufacturer','supplier','medicine']),$scheme->wasRecentlyCreated?201:200);
    }
    public function schemePreview(Request $r) {
        $d=$r->validate(['scheme_id'=>'required|exists:manufacturer_schemes,id','quantity'=>'required|integer|min:1','unit_cost'=>'nullable|numeric|min:0']);
        $s=ManufacturerScheme::findOrFail($d['scheme_id']);
        if ($s->branch_id) abort_unless((int)$s->branch_id===BranchContext::id() || auth()->user()->isAdmin(),404);
        abort_unless($s->is_active && $s->valid_from->lte(today()) && $s->valid_to->gte(today()),422,'This scheme is not currently active.');
        $free=$s->scheme_type==='buy_x_get_y' ? intdiv($d['quantity'],max(1,$s->buy_quantity))*(int)$s->free_quantity : 0;
        if ($s->max_free_quantity) $free=min($free,(int)$s->max_free_quantity);
        $cost=(float)($d['unit_cost']??0);
        return response()->json(['paid_quantity'=>(int)$d['quantity'],'free_quantity'=>$free,'total_quantity'=>(int)$d['quantity']+$free,
            'estimated_saving'=>round($free*$cost,2),'discount_percent'=>(float)$s->discount_percent]);
    }
    public function redeemScheme(Request $r) {
        $d=$r->validate(['scheme_id'=>'required|exists:manufacturer_schemes,id','medicine_id'=>'required|exists:medicines,id','paid_quantity'=>'required|integer|min:1','free_quantity'=>'required|integer|min:0','purchase_id'=>'nullable|exists:purchases,id','purchase_value'=>'required|numeric|min:0','unit_cost'=>'required|numeric|min:0']);
        $s=ManufacturerScheme::findOrFail($d['scheme_id']);
        if ($s->branch_id) abort_unless((int)$s->branch_id===BranchContext::id() || auth()->user()->isAdmin(),404);
        abort_unless($s->is_active && $s->valid_from->lte(today()) && $s->valid_to->gte(today()),422,'Scheme is not active.');
        if ($s->medicine_id && (int)$s->medicine_id !== (int)$d['medicine_id']) throw ValidationException::withMessages(['medicine_id'=>'Medicine is not eligible for this scheme.']);
        if ($s->scheme_type==='buy_x_get_y') {
            $expected=intdiv($d['paid_quantity'],max(1,$s->buy_quantity))*(int)$s->free_quantity;
            if ($d['free_quantity']>$expected || ($s->max_free_quantity && $d['free_quantity']>$s->max_free_quantity)) throw ValidationException::withMessages(['free_quantity'=>'Free quantity exceeds the scheme entitlement.']);
        }
        return DB::transaction(fn()=>response()->json(DB::table('manufacturer_scheme_redemptions')->insertGetId([
            'scheme_id'=>$s->id,'purchase_id'=>$d['purchase_id']??null,'medicine_id'=>$d['medicine_id'],'branch_id'=>BranchContext::id(),
            'paid_quantity'=>$d['paid_quantity'],'free_quantity'=>$d['free_quantity'],'purchase_value'=>$d['purchase_value'],
            'estimated_saving'=>round($d['free_quantity']*$d['unit_cost'],2),'redeemed_at'=>today(),'created_at'=>now(),'updated_at'=>now()
        ]),201));
    }
    public function schemeProfitability(Request $r) {
        return DB::table('manufacturer_scheme_redemptions as x')->join('manufacturer_schemes as s','s.id','=','x.scheme_id')
            ->leftJoin('manufacturers as m','m.id','=','s.manufacturer_id')->select('s.id','s.name','m.name as manufacturer',
                DB::raw('SUM(x.paid_quantity) as paid_quantity'),DB::raw('SUM(x.free_quantity) as free_quantity'),
                DB::raw('SUM(x.purchase_value) as purchase_value'),DB::raw('SUM(x.estimated_saving) as estimated_saving'))
            ->when(!auth()->user()->isAdmin(),fn($q)=>$q->where('x.branch_id',BranchContext::id()))
            ->groupBy('s.id','s.name','m.name')->orderByDesc('estimated_saving')->get();
    }

    /* -------------------- EXPIRY CLAIMS / SUPPLIER CREDIT -------------------- */
    public function expiryClaims(Request $r) {
        return ExpiryReturnClaim::with(['supplier:id,name','items.medicine:id,name','creditNote'])
            ->when(!auth()->user()->isAdmin(),fn($q)=>$q->where('branch_id',BranchContext::id()))
            ->when($r->search, fn($q,$s)=>$q->where('claim_no','like',"%{$s}%"))
            ->latest()->paginate(30);
    }
    public function createExpiryClaim(Request $r) {
        $d=$r->validate(['supplier_id'=>'required|exists:suppliers,id','notes'=>'nullable|string','items'=>'required|array|min:1',
            'items.*.batch_id'=>'required|distinct|exists:medicine_batches,id','items.*.quantity'=>'required|integer|min:1']);
        return DB::transaction(function() use($d) {
            $claim=ExpiryReturnClaim::create(['claim_no'=>'EXP-'.now()->format('ymd').'-'.strtoupper(str()->random(6)),
                'supplier_id'=>$d['supplier_id'],'branch_id'=>BranchContext::id(),'status'=>'draft','notes'=>$d['notes']??null,'created_by'=>auth()->id()]);
            $total=0;
            foreach($d['items'] as $line) {
                $batch=MedicineBatch::whereKey($line['batch_id'])->where('branch_id',BranchContext::id())->lockForUpdate()->firstOrFail();
                if (!$batch->expiry_date || $batch->expiry_date->gt(today()->addMonths(6))) throw ValidationException::withMessages(['items'=>'Only batches expired or expiring within six months can be claimed.']);
                if ((int)$batch->supplier_id !== (int)$d['supplier_id']) throw ValidationException::withMessages(['supplier_id'=>'Selected batch belongs to a different supplier.']);
                $reserved=(int)ExpiryReturnClaimItem::where('batch_id',$batch->id)->whereHas('claim',fn($q)=>$q->whereIn('status',['submitted','approved']))->sum('quantity');
                if ($line['quantity'] > $batch->quantity-$reserved) throw ValidationException::withMessages(['items'=>"Batch {$batch->batch_no} has insufficient unclaimed stock."]);
                $amount=round($line['quantity']*(float)$batch->purchase_price,2); $total+=$amount;
                $claim->items()->create(['medicine_id'=>$batch->medicine_id,'batch_id'=>$batch->id,'quantity'=>$line['quantity'],'unit_cost'=>$batch->purchase_price,'line_total'=>$amount]);
            }
            $claim->update(['requested_amount'=>$total]);
            return response()->json($claim->load('supplier','items.medicine'),201);
        });
    }
    public function reviewExpiryClaim(Request $r, ExpiryReturnClaim $claim) {
        $this->assertBranchRecord($claim);
        $d=$r->validate(['decision'=>'required|in:approved,rejected','approved_amount'=>'nullable|numeric|min:0']);
        abort_unless($claim->status==='submitted',422,'Only submitted claims can be reviewed.');
        $approvedAmount = (float) ($d['approved_amount'] ?? $claim->requested_amount);
        if ($d['decision'] === 'approved' && abs($approvedAmount - (float)$claim->requested_amount) > 0.01) {
            throw ValidationException::withMessages(['approved_amount' => 'This release supports full-quantity supplier credit claims. Partial approvals are not allowed.']);
        }
        $claim->update(['status'=>$d['decision'],'approved_amount'=>$d['decision']==='approved'?$claim->requested_amount:0,
            'reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
        return response()->json($claim->fresh(['items','supplier']));
    }
    public function submitExpiryClaim(ExpiryReturnClaim $claim) {
        $this->assertBranchRecord($claim);
        abort_unless($claim->status==='draft',422,'Only draft claims can be submitted.');
        return DB::transaction(function() use($claim) {
            $claim->load('items');
            foreach ($claim->items as $item) {
                $batch=MedicineBatch::whereKey($item->batch_id)->where('branch_id',$claim->branch_id)->lockForUpdate()->firstOrFail();
                $reserved=(int)ExpiryReturnClaimItem::where('batch_id',$batch->id)->where('claim_id','!=',$claim->id)
                    ->whereHas('claim',fn($q)=>$q->whereIn('status',['submitted','approved']))->sum('quantity');
                if ((int)$item->quantity > (int)$batch->quantity-$reserved) throw ValidationException::withMessages(['items'=>"Batch {$batch->batch_no} no longer has enough unclaimed stock."]);
            }
            $claim->update(['status'=>'submitted']);
            return response()->json($claim->fresh(['items','supplier']));
        });
    }
    public function creditExpiryClaim(ExpiryReturnClaim $claim, StockService $stock) {
        $this->assertBranchRecord($claim);
        abort_unless($claim->status==='approved',422,'Only approved claims can be credited.');
        return DB::transaction(function() use($claim,$stock) {
            $claim->load('items.batch');
            foreach($claim->items as $item) {
                $batch=MedicineBatch::whereKey($item->batch_id)->lockForUpdate()->firstOrFail();
                $stock->moveBatch($batch,-(int)$item->quantity,'supplier_expiry_return',$claim->claim_no,'Supplier expiry return');
            }
            $note=SupplierCreditNote::create(['credit_note_no'=>'SCN-'.now()->format('ymd').'-'.strtoupper(str()->random(6)),
                'claim_id'=>$claim->id,'supplier_id'=>$claim->supplier_id,'branch_id'=>$claim->branch_id,
                'amount'=>$claim->approved_amount,'credit_date'=>today(),'created_by'=>auth()->id()]);
            $supplier=DB::table('suppliers')->where('id',$claim->supplier_id)->lockForUpdate()->first();
            DB::table('suppliers')->where('id',$claim->supplier_id)->update(['balance'=>round((float)$supplier->balance-(float)$claim->approved_amount,2),'updated_at'=>now()]);
            // Reverse inventory value and supplier payable with a balanced journal.
            if (Schema::hasTable('journal_entries') && (float)$claim->approved_amount>0) {
                $ap=DB::table('accounting_accounts')->where('code','2000')->value('id');
                $inv=DB::table('accounting_accounts')->where('code','1100')->value('id');
                if ($ap && $inv) {
                    $je=DB::table('journal_entries')->insertGetId(['entry_no'=>'AUTO-EXP-'.$note->id,'branch_id'=>$claim->branch_id,'entry_date'=>today(),
                        'narration'=>'Supplier expiry credit '.$note->credit_note_no,'source_type'=>SupplierCreditNote::class,'source_id'=>$note->id,
                        'status'=>'posted','created_by'=>auth()->id(),'posted_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
                    DB::table('journal_entry_lines')->insert([
                        ['journal_entry_id'=>$je,'account_id'=>$ap,'debit'=>$claim->approved_amount,'credit'=>0,'description'=>'Supplier credit note','created_at'=>now(),'updated_at'=>now()],
                        ['journal_entry_id'=>$je,'account_id'=>$inv,'debit'=>0,'credit'=>$claim->approved_amount,'description'=>'Expired stock returned','created_at'=>now(),'updated_at'=>now()],
                    ]);
                }
            }
            $claim->update(['status'=>'credited','credited_at'=>now()]);
            StockService::flushCache([(int)$claim->branch_id]);
            return response()->json(['claim'=>$claim->fresh(),'credit_note'=>$note]);
        });
    }

    /* -------------------- INSURANCE / PANEL -------------------- */
    public function insuranceProviders() { return InsuranceProvider::with('plans')->orderBy('name')->paginate(30); }
    public function saveInsuranceProvider(Request $r, ?InsuranceProvider $provider=null) {
        $d=$r->validate(['name'=>'required|string|max:180','code'=>['required','string','max:50',Rule::unique('insurance_providers','code')->ignore($provider?->id)],
            'phone'=>'nullable|string|max:40','email'=>'nullable|email','claims_email'=>'nullable|email','address'=>'nullable|string','is_active'=>'sometimes|boolean']);
        $provider ? $provider->update($d) : $provider=InsuranceProvider::create($d);
        return response()->json($provider,$provider->wasRecentlyCreated?201:200);
    }
    public function saveInsurancePlan(Request $r, ?InsurancePlan $plan=null) {
        $d=$r->validate(['provider_id'=>'required|exists:insurance_providers,id','name'=>'required|string|max:150','plan_code'=>'nullable|string|max:80',
            'annual_limit'=>'nullable|numeric|min:0','default_coverage_percent'=>'required|numeric|min:0|max:100','is_active'=>'sometimes|boolean']);
        $plan ? $plan->update($d) : $plan=InsurancePlan::create($d);
        return response()->json($plan,$plan->wasRecentlyCreated?201:200);
    }
    public function saveInsuranceCoverage(Request $r) {
        $d=$r->validate(['plan_id'=>'required|exists:insurance_plans,id','medicine_id'=>'nullable|exists:medicines,id','coverage_percent'=>'required|numeric|min:0|max:100','max_amount'=>'nullable|numeric|min:0','is_covered'=>'required|boolean']);
        $c=InsuranceCoverage::updateOrCreate(['plan_id'=>$d['plan_id'],'medicine_id'=>$d['medicine_id']??null],$d);
        return response()->json($c);
    }
    public function createInsuranceClaim(Request $r) {
        $d=$r->validate(['sale_id'=>'required|exists:sales,id','provider_id'=>'required|exists:insurance_providers,id','plan_id'=>'required|exists:insurance_plans,id','customer_id'=>'nullable|exists:customers,id','external_reference'=>'nullable|string|max:100','notes'=>'nullable|string']);
        $sale=DB::table('sales')->where('id',$d['sale_id'])->first();
        if (!$sale) abort(404);
        if (!auth()->user()->isAdmin() && (int)$sale->branch_id!==BranchContext::id()) abort(403);
        if (InsuranceClaim::where('sale_id',$sale->id)->whereNotIn('status',['rejected'])->exists()) throw ValidationException::withMessages(['sale_id'=>'An active insurance claim already exists for this sale.']);
        $plan=isset($d['plan_id'])?InsurancePlan::with('coverages')->findOrFail($d['plan_id']):null;
        if ($plan && (int)$plan->provider_id !== (int)$d['provider_id']) throw ValidationException::withMessages(['plan_id'=>'Selected plan does not belong to the selected insurance provider.']);
        $claimCustomerId=$d['customer_id']??$sale->customer_id;
        if ($sale->customer_id && (int)$claimCustomerId !== (int)$sale->customer_id) throw ValidationException::withMessages(['customer_id'=>'Insurance customer must match the customer on the sale.']);
        if ($claimCustomerId) {
            $panelCustomer=\App\Models\Customer::find($claimCustomerId);
            if ($panelCustomer?->insurance_provider_id && (int)$panelCustomer->insurance_provider_id !== (int)$d['provider_id']) throw ValidationException::withMessages(['provider_id'=>'Provider does not match this customer panel record.']);
            if ($panelCustomer?->insurance_plan_id && (int)$panelCustomer->insurance_plan_id !== (int)$plan->id) throw ValidationException::withMessages(['plan_id'=>'Plan does not match this customer panel record.']);
        }
        return DB::transaction(function() use($d,$sale,$plan,$claimCustomerId) {
            $claim=InsuranceClaim::create(['claim_no'=>'INS-'.now()->format('ymd').'-'.strtoupper(str()->random(6)),
                'provider_id'=>$d['provider_id'],'plan_id'=>$plan?->id,'customer_id'=>$claimCustomerId,
                'sale_id'=>$sale->id,'branch_id'=>$sale->branch_id,'claim_date'=>today(),'external_reference'=>$d['external_reference']??null,
                'notes'=>$d['notes']??null,'created_by'=>auth()->id()]);
            $gross=0;$coveredTotal=0;$copayTotal=0;
            $customerId=$claimCustomerId;
            $remainingAnnual=$plan && $plan->annual_limit!==null && $customerId
                ? max(0,(float)$plan->annual_limit-(float)InsuranceClaim::where('customer_id',$customerId)->where('plan_id',$plan->id)->whereYear('claim_date',today()->year)->whereNotIn('status',['rejected'])->sum('covered_amount'))
                : PHP_FLOAT_MAX;
            $items=DB::table('sale_items')->where('sale_id',$sale->id)->get();
            foreach($items as $line) {
                $eligibleQty=max(0,(int)$line->quantity-(int)$line->returned_qty);
                if ($eligibleQty===0) continue;
                $amount=(float)$line->price*$eligibleQty;
                $coverage=$plan?->coverages->firstWhere('medicine_id',$line->medicine_id) ?? $plan?->coverages->firstWhere('medicine_id',null);
                $percent=$coverage ? ($coverage->is_covered?(float)$coverage->coverage_percent:0) : (float)($plan->default_coverage_percent??0);
                $covered=$coverage && $coverage->max_amount!==null ? min($amount*(min(100,$percent)/100),(float)$coverage->max_amount) : $amount*(min(100,$percent)/100);
                $covered=min($covered,$remainingAnnual);$remainingAnnual=max(0,$remainingAnnual-$covered);$copay=max(0,$amount-$covered);$gross+=$amount;$coveredTotal+=$covered;$copayTotal+=$copay;
                $claim->items()->create(['medicine_id'=>$line->medicine_id,'medicine_name'=>$line->medicine_name,'quantity'=>$eligibleQty,
                    'unit_price'=>$line->price,'gross_amount'=>$amount,'covered_amount'=>round($covered,2),'copay_amount'=>round($copay,2)]);
            }
            $claim->update(['gross_amount'=>round($gross,2),'covered_amount'=>round($coveredTotal,2),'copay_amount'=>round($copayTotal,2)]);
            return response()->json($claim->load('items','provider'),201);
        });
    }
    public function insuranceClaims(Request $r) {
        return InsuranceClaim::with(['provider:id,name','customer:id,name','items'])
            ->when(!auth()->user()->isAdmin(),fn($q)=>$q->where('branch_id',BranchContext::id()))
            ->latest()->paginate(30);
    }
    public function submitInsuranceClaim(InsuranceClaim $claim) {
        $this->assertBranchRecord($claim);
        abort_unless(in_array($claim->status,['draft','rejected']),422,'Claim cannot be submitted in its current status.');
        $claim->update(['status'=>'submitted','submitted_at'=>now()]); return response()->json($claim);
    }
    public function reviewInsuranceClaim(Request $r, InsuranceClaim $claim) {
        $this->assertBranchRecord($claim);
        $d=$r->validate(['decision'=>'required|in:approved,rejected','approved_amount'=>'nullable|numeric|min:0']);
        abort_unless($claim->status==='submitted',422,'Only submitted claims can be reviewed.');
        $approved=min((float)($d['approved_amount']??$claim->covered_amount),(float)$claim->covered_amount);
        $claim->update(['status'=>$d['decision'],'covered_amount'=>$d['decision']==='approved'?$approved:0,'reviewed_by'=>auth()->id(),'reviewed_at'=>now()]);
        return response()->json($claim);
    }
    public function settleInsuranceClaim(Request $r, InsuranceClaim $claim) {
        $this->assertBranchRecord($claim);
        $d=$r->validate(['amount'=>'required|numeric|min:0.01','reference'=>'nullable|string|max:100','payment_date'=>'nullable|date']);
        return DB::transaction(function() use($d,$claim) {
            $locked=InsuranceClaim::lockForUpdate()->findOrFail($claim->id);
            abort_unless(in_array($locked->status,['approved','partially_settled']),422,'Claim is not approved for settlement.');
            $remaining=round((float)$locked->covered_amount-(float)$locked->settled_amount,2);
            if ($d['amount']>$remaining+0.01) throw ValidationException::withMessages(['amount'=>'Settlement exceeds the outstanding claim amount.']);
            $locked->settlements()->create(['amount'=>$d['amount'],'reference'=>$d['reference']??null,'payment_date'=>$d['payment_date']??today(),'received_by'=>auth()->id()]);
            $newSettled=round((float)$locked->settled_amount+(float)$d['amount'],2);
            $locked->update(['settled_amount'=>$newSettled,'status'=>$newSettled >= (float)$locked->covered_amount-0.01?'settled':'partially_settled']);
            return response()->json($locked->fresh(['settlements','provider','items']));
        });
    }

    /* -------------------- ANALYTICS / FORECASTS -------------------- */
    public function analytics(Request $r) {
        $days=max(30,min(730,(int)$r->input('days',180))); $from=now()->subDays($days)->toDateString(); $branch=BranchContext::id();
        $daily=DB::table('sales')->selectRaw('DATE(sale_date) as day, SUM(total-returned_amount) as revenue, COUNT(*) as invoices')
            ->where('branch_id',$branch)->where('sale_date','>=',$from)->groupBy('day')->orderBy('day')->get();
        $top=DB::table('sale_items as i')->join('sales as s','s.id','=','i.sale_id')->select('i.medicine_id','i.medicine_name')
            ->selectRaw('SUM(i.quantity-i.returned_qty) as units, SUM(i.price*(i.quantity-i.returned_qty)) as revenue')
            ->where('s.branch_id',$branch)->where('s.sale_date','>=',$from)->groupBy('i.medicine_id','i.medicine_name')->orderByDesc('units')->limit(10)->get();
        $months=DB::table('sales')->selectRaw('MONTH(sale_date) as month_no, SUM(total-returned_amount) / GREATEST(COUNT(DISTINCT YEAR(sale_date)),1) as avg_revenue, COUNT(*) as invoices')
            ->where('branch_id',$branch)->where('sale_date','>=',now()->subYears(2)->toDateString())->groupBy('month_no')->orderBy('month_no')->get();
        $cost=DB::table('sale_items as i')->join('sales as s','s.id','=','i.sale_id')->where('s.branch_id',$branch)->where('s.sale_date','>=',$from)
            ->selectRaw('SUM(i.price * (i.quantity-i.returned_qty)) as revenue, SUM(i.cost_price * (i.quantity-i.returned_qty)) as cost')->first();
        $revenue=(float)($cost->revenue??0);$costValue=(float)($cost->cost??0);
        $recentAvg=round((float)$daily->sum('revenue') / max(1,$days),2);$forecast=round($recentAvg*30,2);
        $salesByMedicine=DB::table('sale_items as i')->join('sales as s','s.id','=','i.sale_id')
            ->where('s.branch_id',$branch)->where('s.sale_date','>=',now()->subDays($days)->toDateString())
            ->select('i.medicine_id')->selectRaw('SUM(i.quantity-i.returned_qty) as units')->groupBy('i.medicine_id')->pluck('units','medicine_id');
        $incomingByMedicine=DB::table('purchase_order_items as poi')->join('purchase_orders as po','po.id','=','poi.purchase_order_id')
            ->join('medicines as mm','mm.id','=','poi.medicine_id')->where('po.branch_id',$branch)->where('po.status','approved')
            ->select('poi.medicine_id')->selectRaw('SUM((poi.quantity+poi.bonus_qty)*COALESCE(mm.pack_size,1)) as incoming')
            ->groupBy('poi.medicine_id')->pluck('incoming','poi.medicine_id');
        $reorder=Medicine::query()->where('is_active',1)->withSum(['batches as stock' => fn($q)=>$q->where('branch_id',$branch)->where('quantity','>',0)->where(fn($x)=>$x->whereNull('expiry_date')->orWhere('expiry_date','>=',today()))],'quantity')
            ->get()->map(function($m) use($days,$salesByMedicine,$incomingByMedicine) {
                $sold=(int)($salesByMedicine[$m->id]??0);$incoming=(int)($incomingByMedicine[$m->id]??0);
                $daily=$sold/max(1,$days);$target=(int)ceil($daily*30);$stock=(int)($m->stock??0);
                $suggested=max(0,$target-$stock-$incoming);$packSize=max(1,(int)($m->pack_size??1));
                return ['medicine_id'=>$m->id,'name'=>$m->name,'stock'=>$stock,'incoming'=>$incoming,'pack_size'=>$packSize,
                    'avg_daily_demand'=>round($daily,2),'suggested_order'=>$suggested,'suggested_packs'=>(int)ceil($suggested/$packSize),
                    'forecast_30_days'=>round($daily*30,2)];
            })->filter(fn($x)=>$x['suggested_order']>0)->sortByDesc('suggested_order')->values();
        return response()->json(['period_days'=>$days,'daily'=>$daily,'seasonality'=>$months,'top_medicines'=>$top,
            'profit'=>['revenue'=>round($revenue,2),'cost'=>round($costValue,2),'gross_profit'=>round($revenue-$costValue,2),'margin_percent'=>$revenue>0?round((($revenue-$costValue)/$revenue)*100,2):0],
            'forecast'=>['next_30_day_revenue'=>$forecast,'next_30_day_gross_profit'=>round($forecast*($revenue>0?max(0,($revenue-$costValue)/$revenue):0),2),'method'=>'30-day daily run-rate with historical gross margin; indicative, not a guarantee'],'purchase_plan'=>$reorder]);
    }


    public function mobileOverview()
    {
        $branch=BranchContext::id();
        return response()->json([
            'branch'=>Branch::find($branch),
            'today'=>['sales_count'=>DB::table('sales')->where('branch_id',$branch)->whereDate('sale_date',today())->count(),
                'sales_total'=>(float)DB::table('sales')->where('branch_id',$branch)->whereDate('sale_date',today())->sum('total'),
                'sales_due'=>(float)DB::table('sales')->where('branch_id',$branch)->whereDate('sale_date',today())->sum('due')],
            'low_stock'=>DB::table('medicines')->where('is_active',1)->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM medicine_batches WHERE medicine_id=medicines.id AND branch_id=? AND (expiry_date IS NULL OR expiry_date>=?)) <= reorder_level',[$branch,today()])->count(),
            'expiring_30_days'=>DB::table('medicine_batches')->where('branch_id',$branch)->where('quantity','>',0)->whereBetween('expiry_date',[today(),today()->addDays(30)])->count(),
            'recent_sales'=>DB::table('sales')->where('branch_id',$branch)->latest('id')->limit(8)->get(['id','invoice_no','customer_name','total','payment_status','sale_date']),
        ]);
    }

    public function linkPrescription(Request $r, \App\Models\Prescription $prescription)
    {
        $d=$r->validate(['doctor_id'=>'nullable|exists:doctors,id','clinic_id'=>'nullable|exists:clinics,id','referral_id'=>'nullable|exists:doctor_referrals,id']);
        if (!empty($d['referral_id']) && !empty($d['doctor_id'])) abort_unless(DoctorReferral::whereKey($d['referral_id'])->where('doctor_id',$d['doctor_id'])->exists(),422,'Selected referral belongs to a different doctor.');
        if (!empty($d['clinic_id']) && !empty($d['doctor_id'])) abort_unless(DB::table('clinic_doctor')->where('doctor_id',$d['doctor_id'])->where('clinic_id',$d['clinic_id'])->exists(),422,'The selected doctor is not linked to this clinic.');
        $prescription->update($d);
        return response()->json($prescription->fresh(['customer','items']));
    }

    /* -------------------- READ-ONLY AI ASSISTANT -------------------- */
    public function aiAssistant(Request $r) {
        $d=$r->validate(['question'=>'required|string|max:1200']);
        $q=mb_strtolower($d['question']);$branch=BranchContext::id();
        $context=[
            'branch_id'=>$branch,
            'today'=>today()->toDateString(),
            'low_stock'=>DB::table('medicines')->select('id','name','reorder_level')
                ->selectRaw('(SELECT COALESCE(SUM(quantity),0) FROM medicine_batches WHERE medicine_id=medicines.id AND branch_id=? AND (expiry_date IS NULL OR expiry_date>=?)) as branch_stock',[$branch,today()])
                ->where('is_active',1)->whereRaw('(SELECT COALESCE(SUM(quantity),0) FROM medicine_batches WHERE medicine_id=medicines.id AND branch_id=? AND (expiry_date IS NULL OR expiry_date>=?)) <= reorder_level',[$branch,today()])->limit(30)->get(),
            'expiring_batches'=>DB::table('medicine_batches')->join('medicines','medicines.id','=','medicine_batches.medicine_id')->where('medicine_batches.branch_id',$branch)->where('medicine_batches.quantity','>',0)->whereBetween('medicine_batches.expiry_date',[today()->toDateString(),today()->addDays(90)->toDateString()])->orderBy('medicine_batches.expiry_date')->limit(30)->get(['medicines.name','medicine_batches.batch_no','medicine_batches.quantity','medicine_batches.expiry_date']),
            'sales_30_days'=>['count'=>DB::table('sales')->where('branch_id',$branch)->where('sale_date','>=',now()->subDays(30)->toDateString())->count(),'total'=>(float)DB::table('sales')->where('branch_id',$branch)->where('sale_date','>=',now()->subDays(30)->toDateString())->sum(DB::raw('total-returned_amount'))],
            'gross_profit_30_days'=>(function() use($branch) {
                $row=DB::table('sale_items as i')->join('sales as s','s.id','=','i.sale_id')->where('s.branch_id',$branch)->where('s.sale_date','>=',now()->subDays(30)->toDateString())
                    ->selectRaw('SUM(i.price*(i.quantity-i.returned_qty)) as revenue,SUM(i.cost_price*(i.quantity-i.returned_qty)) as cost')->first();
                return ['revenue'=>round((float)($row->revenue??0),2),'cost'=>round((float)($row->cost??0),2),'gross_profit'=>round((float)($row->revenue??0)-(float)($row->cost??0),2)];
            })(),
            'purchases_30_days'=>['count'=>DB::table('purchases')->where('branch_id',$branch)->where('purchase_date','>=',now()->subDays(30)->toDateString())->count(),
                'total'=>(float)DB::table('purchases')->where('branch_id',$branch)->where('purchase_date','>=',now()->subDays(30)->toDateString())->sum('total'),
                'due'=>(float)DB::table('purchases')->where('branch_id',$branch)->where('purchase_date','>=',now()->subDays(30)->toDateString())->sum('due')],
            'purchase_orders'=>DB::table('purchase_orders')->where('branch_id',$branch)->whereIn('status',['pending_approval','approved'])->count(),
            'customer_receivables'=>(float)DB::table('sales')->where('branch_id',$branch)->sum('due'),
        ];
        if (!config('services.openai.key')) return response()->json(['answer'=>'AI assistant is not configured yet. Add OPENAI_API_KEY and OPENAI_MODEL to your server environment. Your existing WhatsApp, mail and storage settings are not involved.','context'=>$context,'configured'=>false]);
        try {
            $response=Http::withToken(config('services.openai.key'))->timeout(35)->post('https://api.openai.com/v1/chat/completions',[
                'model'=>config('services.openai.model','gpt-4o-mini'),
                'messages'=>[
                    ['role'=>'system','content'=>'You are MediStore pharmacy business analyst. Answer using only the supplied data context. Clearly state when data is insufficient. Do not diagnose patients, recommend treatment, prescribe medicines, or invent facts. You are read-only and must never claim to have changed records. Use concise practical business language.'],
                    ['role'=>'user','content'=>"Question: {$d['question']}

Authorized branch data:
".json_encode($context,JSON_PRETTY_PRINT)]
                ],
                'temperature'=>0.2,
            ]);
            if (!$response->successful()) return response()->json(['message'=>'AI provider request failed. Check the AI API key/model configuration.'],502);
            return response()->json(['answer'=>$response->json('choices.0.message.content')??'No answer returned.','configured'=>true,'context'=>$context]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message'=>'AI provider is temporarily unavailable. Please try again.'],502);
        }
    }
}
