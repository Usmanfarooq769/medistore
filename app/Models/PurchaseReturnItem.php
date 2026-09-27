<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    protected $fillable = ['purchase_return_id', 'medicine_id', 'medicine_batch_id', 'batch_no', 'expiry_date', 'quantity', 'price', 'total'];

    protected $casts = ['expiry_date' => 'date', 'price' => 'decimal:2', 'total' => 'decimal:2'];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }
}
