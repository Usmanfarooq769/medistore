<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerCampaignDelivery extends Model { protected $guarded=[]; protected $casts=['sent_at'=>'datetime']; public function campaign(){return $this->belongsTo(CustomerCampaign::class,'campaign_id');} public function customer(){return $this->belongsTo(Customer::class);} }
