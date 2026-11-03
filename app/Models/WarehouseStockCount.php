<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseStockCount extends Model { protected $guarded=[]; protected $casts=['count_date'=>'date','reviewed_at'=>'datetime']; public function items(){return $this->hasMany(WarehouseStockCountItem::class,'stock_count_id');} public function branch(){return $this->belongsTo(Branch::class);} }
