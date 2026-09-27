<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $fillable = [
        'return_no', 'sale_id', 'customer_id', 'customer_name', 'return_date', 'subtotal', 'deduction',
        'total', 'due_adjusted', 'refund_amount', 'refund_method', 'reason', 'user_id',
    ];

    protected $casts = [
        'return_date' => 'date',
        'subtotal' => 'decimal:2', 'deduction' => 'decimal:2', 'total' => 'decimal:2',
        'due_adjusted' => 'decimal:2', 'refund_amount' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
