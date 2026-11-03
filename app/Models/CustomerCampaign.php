<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerCampaign extends Model { protected $guarded=[]; protected $casts=['starts_at'=>'datetime','ends_at'=>'datetime']; public function deliveries(){return $this->hasMany(CustomerCampaignDelivery::class,'campaign_id');} }
