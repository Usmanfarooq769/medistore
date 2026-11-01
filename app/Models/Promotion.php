<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $guarded = [];
    protected $casts = [
        'discount_value' => 'decimal:2', 'minimum_subtotal' => 'decimal:2',
        'maximum_discount' => 'decimal:2', 'starts_at' => 'datetime:Y-m-d\TH:i', 'ends_at' => 'datetime:Y-m-d\TH:i',
        'is_active' => 'boolean',
    ];

    public function redemptions() { return $this->hasMany(PromotionRedemption::class); }
}
