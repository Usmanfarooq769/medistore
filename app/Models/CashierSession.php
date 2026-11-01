<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CashierSession extends Model
{
    protected $fillable = ['user_id','opened_at','closed_at','opening_cash','expected_cash','counted_cash','difference','closing_note','branch_id'];
    protected $casts = ['opened_at'=>'datetime','closed_at'=>'datetime','opening_cash'=>'decimal:2','expected_cash'=>'decimal:2','counted_cash'=>'decimal:2','difference'=>'decimal:2'];
    public function user(){ return $this->belongsTo(User::class); }
    public function transactions(){ return $this->hasMany(CashTransaction::class); }
    public function sales(){ return $this->hasMany(Sale::class); }
}
