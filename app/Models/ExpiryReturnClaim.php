<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExpiryReturnClaim extends Model {
 protected $guarded=[];
 protected $casts=['reviewed_at'=>'datetime','credited_at'=>'datetime'];
 public function supplier(){return $this->belongsTo(Supplier::class);}
 public function items(){return $this->hasMany(ExpiryReturnClaimItem::class,'claim_id');}
 public function creditNote(){return $this->hasOne(SupplierCreditNote::class,'claim_id');}
}