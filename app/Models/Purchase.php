<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'reference_no', 'supplier_id', 'supplier_invoice_no', 'purchase_date', 'subtotal', 'discount',
        'tax', 'total', 'paid', 'due', 'payment_status', 'note', 'user_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2',
        'total' => 'decimal:2', 'paid' => 'decimal:2', 'due' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
