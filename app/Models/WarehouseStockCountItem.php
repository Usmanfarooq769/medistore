<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WarehouseStockCountItem extends Model { protected $guarded=[]; public function count(){return $this->belongsTo(WarehouseStockCount::class,'stock_count_id');} public function batch(){return $this->belongsTo(MedicineBatch::class,'medicine_batch_id');} }
