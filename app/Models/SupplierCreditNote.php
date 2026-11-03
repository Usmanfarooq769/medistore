<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierCreditNote extends Model { protected $guarded=[]; protected $casts=['credit_date'=>'date']; public function supplier(){return $this->belongsTo(Supplier::class);} }