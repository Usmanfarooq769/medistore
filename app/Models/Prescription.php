<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Prescription extends Model { protected $guarded=[]; protected $casts=['prescription_date'=>'date','reviewed_at'=>'datetime']; public function customer(){return $this->belongsTo(Customer::class);} public function items(){return $this->hasMany(PrescriptionItem::class);} }
