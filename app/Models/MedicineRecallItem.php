<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MedicineRecallItem extends Model { protected $guarded=[]; protected $casts=['quarantine_applied'=>'boolean']; public function recall(){return $this->belongsTo(MedicineRecall::class,'recall_id');} public function medicine(){return $this->belongsTo(Medicine::class);} public function batch(){return $this->belongsTo(MedicineBatch::class,'batch_id');} }
