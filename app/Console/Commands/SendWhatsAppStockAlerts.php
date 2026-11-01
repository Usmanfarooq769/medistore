<?php
namespace App\Console\Commands;
use App\Models\{MedicineBatch,WhatsAppNotificationLog};
use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command as BaseCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class SendWhatsAppStockAlerts extends BaseCommand
{
 protected $signature='store:whatsapp-stock-alerts'; protected $description='Send daily low-stock and expiry alerts using an approved WhatsApp template.';
 public function handle(WhatsAppCloudService $whatsapp): int
 {
  $phone=env('WHATSAPP_ALERT_PHONE');$template=config('services.whatsapp.alert_template');
  if(!$phone||!$template){$this->warn('Set WHATSAPP_ALERT_PHONE and WHATSAPP_ALERT_TEMPLATE to enable WhatsApp alerts.');return self::SUCCESS;}
  $stockByBranch=DB::table('medicine_batches')->where(function($q){$q->whereNull('expiry_date')->orWhere('expiry_date','>=',today()->toDateString());})->groupBy('medicine_id','branch_id')->selectRaw('medicine_id,branch_id,SUM(quantity) as stock');$low=DB::table('medicines as m')->crossJoin('branches as b')->leftJoinSub($stockByBranch,'bs',fn($j)=>$j->on('bs.medicine_id','=','m.id')->on('bs.branch_id','=','b.id'))->where('m.is_active',true)->where('b.is_active',true)->whereRaw('COALESCE(bs.stock,0) <= m.reorder_level')->count();$days=(int)setting('expiry_alert_days',60);
  $expired=MedicineBatch::where('quantity','>',0)->where('expiry_date','<',today())->count();$expiring=MedicineBatch::where('quantity','>',0)->whereBetween('expiry_date',[today(),today()->addDays($days)])->count();
  $event='daily_stock_digest';if(WhatsAppNotificationLog::where('event',$event)->whereDate('notification_date',today())->where('status','sent')->exists()){$this->info('Daily digest already sent.');return self::SUCCESS;}
  try{$whatsapp->sendTemplate($phone,$template,[(string)$low,(string)($expired+$expiring)],$event);$this->info('WhatsApp stock digest sent.');return self::SUCCESS;}catch(\Throwable $e){Log::error('WhatsApp stock alert failed',['error'=>$e->getMessage()]);$this->error($e->getMessage());return self::FAILURE;}
 }
}
