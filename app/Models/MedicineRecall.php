<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MedicineRecall extends Model { protected $guarded=[]; protected $casts=['issued_on'=>'date']; public function items(){return $this->hasMany(MedicineRecallItem::class,'recall_id');} }
