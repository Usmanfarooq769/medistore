<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'invoice_no', 'customer_id', 'customer_name', 'customer_phone', 'sale_date', 'subtotal', 'discount',
        'tax_percent', 'tax', 'total', 'paid', 'initial_paid', 'due', 'change_amount', 'returned_amount', 'payment_method',
        'payment_status', 'note', 'user_id', 'cashier_session_id', 'branch_id', 'prescription_id', 'tax_profile_id', 'promotion_id', 'loyalty_points_earned', 'loyalty_points_redeemed', 'loyalty_points_reversed', 'loyalty_points_restored',
        'fbr_invoice_no', 'fbr_status', 'fbr_error', 'fbr_synced_at', 'approved_by', 'safety_override_note',
    ];

    protected $casts = [
        'sale_date' => 'date', 'fbr_synced_at' => 'datetime',
        'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax_percent' => 'decimal:2', 'tax' => 'decimal:2',
        'total' => 'decimal:2', 'paid' => 'decimal:2', 'initial_paid' => 'decimal:2', 'due' => 'decimal:2', 'change_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2', 'loyalty_points_earned' => 'integer', 'loyalty_points_redeemed' => 'integer', 'loyalty_points_reversed' => 'integer', 'loyalty_points_restored' => 'integer',
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
    public function promotion() { return $this->belongsTo(Promotion::class); }
    public function branch() { return $this->belongsTo(Branch::class); }

}
