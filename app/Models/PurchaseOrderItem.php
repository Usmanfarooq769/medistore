<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderItem extends Model { protected $guarded=[]; protected $casts=['expiry_date'=>'date','purchase_price'=>'decimal:2','sale_price'=>'decimal:2']; public function medicine(){return $this->belongsTo(Medicine::class);} }
