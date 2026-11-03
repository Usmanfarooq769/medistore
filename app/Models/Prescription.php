<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Prescription extends Model { protected $guarded=[]; protected $casts=['prescription_date'=>'date','reviewed_at'=>'datetime']; public function customer(){return $this->belongsTo(Customer::class);} public function items(){return $this->hasMany(PrescriptionItem::class);} public function doctor(){return $this->belongsTo(Doctor::class);} public function clinic(){return $this->belongsTo(Clinic::class);} public function referral(){return $this->belongsTo(DoctorReferral::class,'referral_id');} public function healthcareFacility(){return $this->belongsTo(HealthcareFacility::class,'healthcare_facility_id');} }
