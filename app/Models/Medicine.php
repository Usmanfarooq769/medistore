<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $fillable = [
        'name', 'generic_name', 'strength', 'category_id', 'manufacturer_id', 'barcode', 'unit',
        'pack_size', 'stock_units_are_base', 'requires_prescription', 'requires_controlled_tracking', 'purchase_price', 'sale_price', 'reorder_level', 'rack', 'is_active',
        'dosage_form', 'storage_condition', 'drug_class', 'hs_code',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price'     => 'decimal:2',
        'reorder_level'  => 'integer',
        'pack_size'      => 'integer',
        'stock_units_are_base' => 'boolean',
        'total_stock'    => 'integer',
        'is_active'      => 'boolean',
        'requires_prescription' => 'boolean',
        'requires_controlled_tracking' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function manufacturer()
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function batches()
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Batches that can be sold: qty > 0 and not expired, earliest expiry first (FEFO) */
    public function sellableBatches()
    {
        return $this->hasMany(MedicineBatch::class)
            ->where('branch_id', \App\Services\BranchContext::id())
            ->where('quantity', '>', 0)
            ->where('is_quarantined', false)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()))
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('id');
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->whereRaw('(SELECT COALESCE(SUM(mb.quantity),0) FROM medicine_batches mb WHERE mb.medicine_id = medicines.id AND mb.branch_id = ? AND mb.is_quarantined = 0 AND (mb.expiry_date IS NULL OR mb.expiry_date >= ?)) <= medicines.reorder_level', [\App\Services\BranchContext::id(), today()->toDateString()]);
    }

    /** Adds `sellable_stock` column (non-expired stock) with one sub-query */
    public function scopeWithSellableStock(Builder $q): Builder
    {
        return $q->withSum(['batches as sellable_stock' => function ($b) {
            $b->where('branch_id', \App\Services\BranchContext::id())->where('quantity', '>', 0)->where('is_quarantined', false)
              ->where(fn ($w) => $w->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()));
        }], 'quantity');
    }

    /** Only medicines that have at least one non-expired batch with stock (EXISTS => uses index) */
    public function scopeSellable(Builder $q): Builder
    {
        return $q->whereHas('batches', function ($b) {
            $b->where('branch_id', \App\Services\BranchContext::id())->where('quantity', '>', 0)->where('is_quarantined', false)
              ->where(fn ($w) => $w->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()));
        });
    }

    /** Adds `nearest_expiry` of sellable batches */
    public function scopeWithNearestExpiry(Builder $q): Builder
    {
        return $q->withMin(['batches as nearest_expiry' => function ($b) {
            $b->where('branch_id', \App\Services\BranchContext::id())->where('quantity', '>', 0)->where('is_quarantined', false)->where('expiry_date', '>=', today());
        }], 'expiry_date');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function ($w) use ($term) {
            $w->where('name', 'like', "{$term}%")          // prefix search can use index
              ->orWhere('name', 'like', "% {$term}%")
              ->orWhere('generic_name', 'like', "{$term}%")
              ->orWhere('barcode', $term);
        });
    }

    public function getDisplayNameAttribute(): string
    {
        return trim($this->name . ' ' . ($this->strength ?? ''));
    }
}
