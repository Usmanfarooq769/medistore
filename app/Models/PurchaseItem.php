<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'medicine_id', 'medicine_batch_id', 'batch_no', 'expiry_date',
        'quantity', 'bonus_qty', 'purchase_price', 'sale_price', 'total',
    ];

    protected $casts = ['expiry_date' => 'date'];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }
}
