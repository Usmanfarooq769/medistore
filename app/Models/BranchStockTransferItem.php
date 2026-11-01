<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BranchStockTransferItem extends Model { protected $guarded=[]; public function medicine(){return $this->belongsTo(Medicine::class);} public function sourceBatch(){return $this->belongsTo(MedicineBatch::class,'source_batch_id');} }
