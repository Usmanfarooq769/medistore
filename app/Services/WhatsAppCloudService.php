<?php
namespace App\Services;

use App\Models\WhatsAppNotificationLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudService
{
    public function sendTemplate(string $phone, string $template, array $parameters, string $event): array
    {
        $token=config('services.whatsapp.token'); $phoneId=config('services.whatsapp.phone_number_id');
        if(!$token||!$phoneId) throw new RuntimeException('WhatsApp Cloud API credentials are not configured.');
        $phone=preg_replace('/[^0-9]/','',$phone);
        $components=[['type'=>'body','parameters'=>array_map(fn($v)=>['type'=>'text','text'=>(string)$v],$parameters)]];
        $response=Http::withToken($token)->acceptJson()->post('https://graph.facebook.com/'.config('services.whatsapp.graph_version','v22.0').'/'.$phoneId.'/messages',[
            'messaging_product'=>'whatsapp','to'=>$phone,'type'=>'template','template'=>['name'=>$template,'language'=>['code'=>config('services.whatsapp.language','en_US')],'components'=>$components],
        ]);
        WhatsAppNotificationLog::create(['event'=>$event,'phone'=>$phone,'template_name'=>$template,'status'=>$response->successful()?'sent':'failed','provider_response'=>$response->json()??['body'=>$response->body()],'notification_date'=>today()]);
        if(!$response->successful()) throw new RuntimeException('WhatsApp API error: '.$response->body());
        return $response->json()??[];
    }
}
