<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Doctor extends Model {
 protected $fillable=['name','license_no','specialty','phone','email','address','is_active'];
 protected $casts=['is_active'=>'boolean'];
 public function clinics(){return $this->belongsToMany(Clinic::class)->withTimestamps();}
 public function prescriptions(){return $this->hasMany(Prescription::class);}
 public function referrals(){return $this->hasMany(DoctorReferral::class);}
}