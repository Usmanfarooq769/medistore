<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class HealthcareFacility extends Model { protected $guarded=[]; protected $casts=['is_active'=>'boolean']; public function branch(){return $this->belongsTo(Branch::class);} public function prescriptions(){return $this->hasMany(Prescription::class,'healthcare_facility_id');} }
