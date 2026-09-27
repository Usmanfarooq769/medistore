<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineBatch extends Model
{
    protected $fillable = [
        'medicine_id', 'purchase_id', 'supplier_id', 'batch_no', 'expiry_date',
        'purchase_price', 'sale_price', 'initial_quantity', 'quantity',
    ];

    protected $casts = [
        'expiry_date'    => 'date',
        'purchase_price' => 'decimal:2',
        'sale_price'     => 'decimal:2',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->lt(today());
    }
}
