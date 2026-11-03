<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AnalyticsForecastRun extends Model { protected $guarded=[]; protected $casts=['period_start'=>'date','period_end'=>'date','summary'=>'array']; public function items(){return $this->hasMany(AnalyticsForecastItem::class,'run_id');} public function branch(){return $this->belongsTo(Branch::class);} }
