<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'medicine_id', 'medicine_batch_id', 'medicine_name', 'batch_no', 'expiry_date',
        'quantity', 'returned_qty', 'price', 'cost_price', 'total',
    ];

    protected $casts = ['expiry_date' => 'date', 'price' => 'decimal:2', 'cost_price' => 'decimal:2', 'total' => 'decimal:2'];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
