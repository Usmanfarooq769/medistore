<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_no', 'customer_id', 'customer_name', 'customer_phone', 'sale_date', 'subtotal', 'discount',
        'tax_percent', 'tax', 'total', 'paid', 'due', 'change_amount', 'returned_amount', 'payment_method',
        'payment_status', 'note', 'user_id',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax_percent' => 'decimal:2', 'tax' => 'decimal:2',
        'total' => 'decimal:2', 'paid' => 'decimal:2', 'due' => 'decimal:2', 'change_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }
}
