<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Clinic extends Model {
 protected $fillable=['name','phone','email','address','branch_id','is_active'];
 protected $casts=['is_active'=>'boolean'];
 public function doctors(){return $this->belongsToMany(Doctor::class)->withTimestamps();}
}