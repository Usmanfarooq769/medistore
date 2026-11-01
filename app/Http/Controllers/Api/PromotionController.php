<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromotionController extends Controller
{
    public function index(Request $request)
    {
        return Promotion::when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->when($request->search, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->latest()->paginate($request->integer('per_page', 20));
    }

    public function active()
    {
        return Promotion::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->orderBy('code')->get(['id','name','code','discount_type','discount_value','minimum_subtotal','maximum_discount','ends_at']);
    }

    public function preview(Request $request)
    {
        $d = $request->validate(['code' => 'required|string|max:40','subtotal' => 'required|numeric|min:0','customer_id' => 'nullable|exists:customers,id']);
        $p = Promotion::where('code', strtoupper(trim($d['code'])))->where('is_active', true)->first();
        if (!$p || ($p->starts_at && $p->starts_at->isFuture()) || ($p->ends_at && $p->ends_at->isPast()) || ($p->usage_limit !== null && $p->used_count >= $p->usage_limit)) {
            return response()->json(['valid' => false, 'message' => 'Promotion code is invalid or inactive.'], 422);
        }
        if ((float)$d['subtotal'] < (float)$p->minimum_subtotal) return response()->json(['valid'=>false,'message'=>'Minimum subtotal is '.money($p->minimum_subtotal).'.'],422);
        if ($p->per_customer_limit && empty($d['customer_id'])) return response()->json(['valid'=>false,'message'=>'Select a customer to use this coupon.'],422);
        if ($p->per_customer_limit && !empty($d['customer_id'])) {
            $count = PromotionRedemption::where('promotion_id',$p->id)->where('customer_id',$d['customer_id'])->count();
            if ($count >= $p->per_customer_limit) return response()->json(['valid'=>false,'message'=>'This customer has reached the promotion limit.'],422);
        }
        $discount = $p->discount_type === 'percentage' ? (float)$d['subtotal'] * (float)$p->discount_value / 100 : (float)$p->discount_value;
        if ($p->maximum_discount !== null) $discount = min($discount,(float)$p->maximum_discount);
        return response()->json(['valid'=>true,'name'=>$p->name,'code'=>$p->code,'discount'=>round(min($discount,(float)$d['subtotal']),2)]);
    }

    public function store(Request $request)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $p = Promotion::create($data);
        return response()->json(['message'=>'Promotion created.','data'=>$p],201);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $this->validated($request, $promotion->id);
        $promotion->update($data);
        return response()->json(['message'=>'Promotion updated.','data'=>$promotion->fresh()]);
    }

    public function destroy(Promotion $promotion)
    {
        abort_if($promotion->used_count > 0, 422, 'This promotion has already been used. Deactivate it instead of deleting it.');
        $promotion->delete();
        return response()->json(['message'=>'Promotion deleted.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'=>'required|string|max:150',
            'code'=>['required','string','max:40',Rule::unique('promotions','code')->ignore($id)],
            'discount_type'=>'required|in:percentage,fixed',
            'discount_value'=>'required|numeric|gt:0',
            'minimum_subtotal'=>'nullable|numeric|min:0',
            'maximum_discount'=>'nullable|numeric|min:0',
            'usage_limit'=>'nullable|integer|min:1',
            'per_customer_limit'=>'nullable|integer|min:1',
            'starts_at'=>'nullable|date',
            'ends_at'=>'nullable|date|after_or_equal:starts_at',
            'is_active'=>'boolean',
        ]);
    }
}
