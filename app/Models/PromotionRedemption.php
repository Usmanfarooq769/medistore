<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionRedemption extends Model
{
    protected $guarded = [];
    protected $casts = ['discount_amount' => 'decimal:2'];
    public function promotion() { return $this->belongsTo(Promotion::class); }
    public function sale() { return $this->belongsTo(Sale::class); }
}
