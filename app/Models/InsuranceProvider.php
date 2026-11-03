<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceProvider extends Model { protected $guarded=[]; protected $casts=['is_active'=>'boolean']; public function plans(){return $this->hasMany(InsurancePlan::class,'provider_id');} }