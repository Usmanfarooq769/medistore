<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrder extends Model
{
 protected $guarded=[]; protected $casts=['order_date'=>'date','expected_date'=>'date','approved_at'=>'datetime','received_at'=>'datetime','subtotal'=>'decimal:2','discount'=>'decimal:2','tax'=>'decimal:2','total'=>'decimal:2'];
 public function supplier(){return $this->belongsTo(Supplier::class);} public function branch(){return $this->belongsTo(Branch::class);} public function items(){return $this->hasMany(PurchaseOrderItem::class);} 
}
