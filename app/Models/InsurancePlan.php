<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsurancePlan extends Model { protected $guarded=[]; protected $casts=['is_active'=>'boolean','annual_limit'=>'decimal:2','default_coverage_percent'=>'decimal:3']; public function provider(){return $this->belongsTo(InsuranceProvider::class,'provider_id');} public function coverages(){return $this->hasMany(InsuranceCoverage::class,'plan_id');} }