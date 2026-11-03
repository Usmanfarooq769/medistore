<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseLocation extends Model { protected $guarded=[]; protected $casts=['is_active'=>'boolean']; public function branch(){return $this->belongsTo(Branch::class);} public function parent(){return $this->belongsTo(self::class,'parent_id');} public function children(){return $this->hasMany(self::class,'parent_id');} }
