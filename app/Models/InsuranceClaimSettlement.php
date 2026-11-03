<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InsuranceClaimSettlement extends Model {
 protected $guarded=[];
 protected $casts=['payment_date'=>'date','amount'=>'decimal:2'];
}