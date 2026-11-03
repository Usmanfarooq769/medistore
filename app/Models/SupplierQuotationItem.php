<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierQuotationItem extends Model { protected $guarded=[]; public function quotation(){return $this->belongsTo(SupplierQuotation::class,'quotation_id');} public function medicine(){return $this->belongsTo(Medicine::class);} }
