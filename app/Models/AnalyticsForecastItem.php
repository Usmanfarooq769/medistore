<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AnalyticsForecastItem extends Model { protected $guarded=[]; public function run(){return $this->belongsTo(AnalyticsForecastRun::class,'run_id');} public function medicine(){return $this->belongsTo(Medicine::class);} }
