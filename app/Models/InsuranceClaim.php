<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceClaim extends Model { protected $guarded=[]; protected $casts=['claim_date'=>'date','submitted_at'=>'datetime','reviewed_at'=>'datetime']; public function items(){return $this->hasMany(InsuranceClaimItem::class,'claim_id');} public function provider(){return $this->belongsTo(InsuranceProvider::class,'provider_id');} public function customer(){return $this->belongsTo(Customer::class);} public function settlements(){return $this->hasMany(InsuranceClaimSettlement::class,'claim_id')->latest('payment_date');} }