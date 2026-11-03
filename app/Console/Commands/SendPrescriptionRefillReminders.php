<?php

namespace App\Console\Commands;

use App\Services\WhatsAppCloudService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendPrescriptionRefillReminders extends Command
{
    protected $signature = 'store:send-refill-reminders {--limit=100}';
    protected $description = 'Send due medicine refill reminders to customers who opted in to WhatsApp messages';

    public function handle(WhatsAppCloudService $whatsapp): int
    {
        $template = config('services.whatsapp.refill_template');
        if (!$template) {
            $this->warn('WHATSAPP_REFILL_TEMPLATE is not configured. No reminders were sent.');
            return self::SUCCESS;
        }

        $limit = max(1, min(500, (int) $this->option('limit')));
        $reminders = DB::table('prescription_refill_reminders as r')
            ->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->join('medicines as m', 'm.id', '=', 'r.medicine_id')
            ->where('r.status', 'pending')
            ->whereDate('r.remind_on', '<=', today())
            ->where('c.marketing_opt_in', true)
            ->whereNotNull('c.phone')
            ->select('r.id', 'c.name as customer_name', 'c.phone', 'm.name as medicine_name')
            ->orderBy('r.remind_on')->limit($limit)->get();

        $sent = 0;
        foreach ($reminders as $reminder) {
            try {
                $whatsapp->sendTemplate(
                    $reminder->phone,
                    $template,
                    [$reminder->customer_name, $reminder->medicine_name],
                    'stage5_refill_reminder'
                );
                DB::table('prescription_refill_reminders')->where('id', $reminder->id)
                    ->where('status', 'pending')->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
                $sent++;
            } catch (Throwable $e) {
                report($e);
                $this->warn("Reminder #{$reminder->id} failed and remains pending.");
            }
        }

        $this->info("Refill reminders sent: {$sent}; eligible records processed: {$reminders->count()}.");
        return self::SUCCESS;
    }
}
