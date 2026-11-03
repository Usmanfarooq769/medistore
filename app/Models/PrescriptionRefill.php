<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PrescriptionRefill extends Model { protected $guarded=[]; protected $casts=['requested_at'=>'datetime','reviewed_at'=>'datetime']; public function prescription(){return $this->belongsTo(Prescription::class);} public function approvedPrescription(){return $this->belongsTo(Prescription::class,'approved_prescription_id');} public function customer(){return $this->belongsTo(Customer::class);} public function medicine(){return $this->belongsTo(Medicine::class);} }
