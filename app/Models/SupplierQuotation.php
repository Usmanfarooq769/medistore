<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierQuotation extends Model { protected $guarded=[]; protected $casts=['quote_date'=>'date','valid_until'=>'date','reviewed_at'=>'datetime']; public function supplier(){return $this->belongsTo(Supplier::class);} public function items(){return $this->hasMany(SupplierQuotationItem::class,'quotation_id');} }
