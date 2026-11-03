<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ManufacturerScheme extends Model {
 protected $guarded=[];
 protected $casts=['valid_from'=>'date','valid_to'=>'date','is_active'=>'boolean','discount_percent'=>'decimal:3'];
 public function manufacturer(){return $this->belongsTo(Manufacturer::class);}
 public function supplier(){return $this->belongsTo(Supplier::class);}
 public function medicine(){return $this->belongsTo(Medicine::class);}
}