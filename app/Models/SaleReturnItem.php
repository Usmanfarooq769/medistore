<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id', 'sale_item_id', 'medicine_id', 'medicine_batch_id', 'medicine_name', 'batch_no',
        'expiry_date', 'quantity', 'price', 'total', 'condition', 'restocked',
    ];

    protected $casts = ['expiry_date' => 'date', 'price' => 'decimal:2', 'total' => 'decimal:2', 'restocked' => 'boolean'];

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
