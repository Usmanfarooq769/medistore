<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubscriptionManagementController extends Controller
{
    public function plans()
    {
        return response()->json(DB::table('subscription_plans')->orderBy('monthly_price')->get());
    }

    public function savePlan(Request $request, ?int $plan = null)
    {
        $data=$request->validate(['code'=>'required|string|max:40','name'=>'required|string|max:120','description'=>'nullable|string|max:1000','monthly_price'=>'required|numeric|min:0','annual_price'=>'required|numeric|min:0','branch_limit'=>'required|integer|min:1|max:1000','user_limit'=>'required|integer|min:1|max:10000','features'=>'nullable|array','is_active'=>'boolean']);
        if (isset($data['features'])) $data['features'] = json_encode($data['features']);
        $payload=$data+['updated_at'=>now()];
        if($plan){DB::table('subscription_plans')->where('id',$plan)->update($payload);$id=$plan;}else{$id=DB::table('subscription_plans')->insertGetId($payload+['created_at'=>now()]);}
        return response()->json(['message'=>'Subscription plan saved.','plan'=>DB::table('subscription_plans')->find($id)],$plan?200:201);
    }

    public function subscriptions()
    {
        return response()->json(DB::table('branch_subscriptions as s')->join('branches as b','b.id','=','s.branch_id')->join('subscription_plans as p','p.id','=','s.plan_id')->orderByDesc('s.id')->paginate(30,['s.*','b.name as branch_name','p.name as plan_name','p.monthly_price','p.annual_price']));
    }

    public function assign(Request $request)
    {
        $data=$request->validate(['branch_id'=>'required|integer|exists:branches,id','plan_id'=>'required|integer|exists:subscription_plans,id','billing_cycle'=>['required',Rule::in(['monthly','annual'])],'starts_on'=>'required|date','ends_on'=>'nullable|date|after_or_equal:starts_on','trial_ends_on'=>'nullable|date']);
        $id=DB::table('branch_subscriptions')->insertGetId($data+['status'=>'trial','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Branch subscription assigned.','subscription'=>DB::table('branch_subscriptions')->find($id)],201);
    }

    public function createInvoice(Request $request, int $subscription)
    {
        $sub=DB::table('branch_subscriptions as s')->join('subscription_plans as p','p.id','=','s.plan_id')->where('s.id',$subscription)->select('s.*','p.monthly_price','p.annual_price')->first();
        abort_unless($sub,404);
        $amount=$sub->billing_cycle==='annual'?(float)$sub->annual_price:(float)$sub->monthly_price;
        $id=DB::table('subscription_invoices')->insertGetId(['subscription_id'=>$subscription,'invoice_number'=>'SUB-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),'invoice_date'=>today(),'due_date'=>today()->addDays(7),'amount'=>$amount,'status'=>'unpaid','created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Subscription invoice generated.','invoice'=>DB::table('subscription_invoices')->find($id)],201);
    }

    public function recordPayment(Request $request, int $invoice)
    {
        $data=$request->validate(['payment_reference'=>'required|string|max:150']);
        $row=DB::table('subscription_invoices')->where('id',$invoice)->where('status','unpaid')->first(); abort_unless($row,404);
        DB::transaction(function()use($data,$row,$request){
            DB::table('subscription_invoices')->where('id',$row->id)->update(['status'=>'paid','payment_reference'=>$data['payment_reference'],'paid_at'=>now(),'recorded_by'=>$request->user()->id,'updated_at'=>now()]);
            $subscription=DB::table('branch_subscriptions')->where('id',$row->subscription_id)->lockForUpdate()->first();
            $base=($subscription->ends_on && \Illuminate\Support\Carbon::parse($subscription->ends_on)->isFuture()) ? \Illuminate\Support\Carbon::parse($subscription->ends_on) : today();
            $newEnd=$subscription->billing_cycle==='annual' ? $base->copy()->addYear()->toDateString() : $base->copy()->addMonth()->toDateString();
            DB::table('branch_subscriptions')->where('id',$row->subscription_id)->update(['status'=>'active','starts_on'=>$subscription->starts_on ?: today()->toDateString(),'ends_on'=>$newEnd,'updated_at'=>now()]);
        });
        return response()->json(['message'=>'Payment recorded. Verify the payment with your payment provider before recording it.']);
    }
}
