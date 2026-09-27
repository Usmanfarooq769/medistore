<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'opening_balance', 'balance'];

    protected $casts = ['opening_balance' => 'decimal:2', 'balance' => 'decimal:2'];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'party_id')->where('party_type', 'supplier');
    }
}
