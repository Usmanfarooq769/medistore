<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WhatsAppNotificationLog extends Model { protected $guarded=[]; protected $casts=['provider_response'=>'array','notification_date'=>'date']; }
