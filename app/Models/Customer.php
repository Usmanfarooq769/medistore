<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'address', 'balance', 'credit_limit', 'loyalty_points', 'insurance_provider_id', 'insurance_plan_id', 'insurance_member_no',
        'allergies', 'chronic_conditions', 'date_of_birth', 'gender', 'cnic', 'email'];

    protected $casts = ['balance' => 'decimal:2', 'credit_limit' => 'decimal:2', 'loyalty_points' => 'integer', 'date_of_birth' => 'date'];

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(LoyaltyTransaction::class)->latest();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'party_id')->where('party_type', 'customer');
    }
}
