<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $fillable = ['medicine_id', 'medicine_batch_id', 'type', 'quantity', 'reason', 'note', 'user_id'];

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
