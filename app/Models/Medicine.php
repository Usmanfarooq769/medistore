<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $fillable = [
        'name', 'generic_name', 'strength', 'category_id', 'manufacturer_id', 'barcode', 'unit',
        'purchase_price', 'sale_price', 'reorder_level', 'rack', 'is_active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price'     => 'decimal:2',
        'reorder_level'  => 'integer',
        'total_stock'    => 'integer',
        'is_active'      => 'boolean',
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
            ->where('quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()))
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('id');
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->whereColumn('total_stock', '<=', 'reorder_level');
    }

    /** Adds `sellable_stock` column (non-expired stock) with one sub-query */
    public function scopeWithSellableStock(Builder $q): Builder
    {
        return $q->withSum(['batches as sellable_stock' => function ($b) {
            $b->where('quantity', '>', 0)
              ->where(fn ($w) => $w->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()));
        }], 'quantity');
    }

    /** Only medicines that have at least one non-expired batch with stock (EXISTS => uses index) */
    public function scopeSellable(Builder $q): Builder
    {
        return $q->whereHas('batches', function ($b) {
            $b->where('quantity', '>', 0)
              ->where(fn ($w) => $w->whereNull('expiry_date')->orWhere('expiry_date', '>=', today()));
        });
    }

    /** Adds `nearest_expiry` of sellable batches */
    public function scopeWithNearestExpiry(Builder $q): Builder
    {
        return $q->withMin(['batches as nearest_expiry' => function ($b) {
            $b->where('quantity', '>', 0)->where('expiry_date', '>=', today());
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
