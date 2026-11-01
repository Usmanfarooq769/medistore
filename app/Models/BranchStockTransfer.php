<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BranchStockTransfer extends Model { protected $guarded=[]; protected $casts=['approved_at'=>'datetime','completed_at'=>'datetime']; public function fromBranch(){return $this->belongsTo(Branch::class,'from_branch_id');} public function toBranch(){return $this->belongsTo(Branch::class,'to_branch_id');} public function items(){return $this->hasMany(BranchStockTransferItem::class,'transfer_id');} }
