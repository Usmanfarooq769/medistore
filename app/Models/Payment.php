<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['party_type', 'party_id', 'purchase_id', 'sale_id', 'amount', 'method', 'payment_date', 'note', 'user_id'];

    protected $casts = ['payment_date' => 'date', 'amount' => 'decimal:2'];
}
