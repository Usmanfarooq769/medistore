<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DoctorReferral extends Model {
 protected $guarded=[];
 protected $casts=['referred_at'=>'date'];
 public function doctor(){return $this->belongsTo(Doctor::class);}
 public function clinic(){return $this->belongsTo(Clinic::class);}
 public function customer(){return $this->belongsTo(Customer::class);}
}