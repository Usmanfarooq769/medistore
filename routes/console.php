<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$tenantMode = config('tenancy.mode', 'single') === 'saas';
$scheduled = [
    ['store:daily-report', fn ($event) => $event->dailyAt('23:59')],
    ['store:whatsapp-stock-alerts', fn ($event) => $event->dailyAt('09:00')],
    ['store:backup-cloud', fn ($event) => $event->dailyAt('02:00')],
    ['store:send-refill-reminders', fn ($event) => $event->dailyAt('10:00')],
    ['store:expire-supplier-contracts', fn ($event) => $event->dailyAt('00:20')],
    ['partners:deliver-webhooks', fn ($event) => $event->everyMinute()],
    ['system:health-check', fn ($event) => $event->hourly()],
    ['store:expire-order-reservations', fn ($event) => $event->everyMinute()],
    ['store:fbr-sync', fn ($event) => $event->everyFiveMinutes()],
];

foreach ($scheduled as [$command, $frequency]) {
    $event = Schedule::command($tenantMode ? 'tenants:run ' . $command : $command);
    $frequency($event);
    $event->withoutOverlapping();
}
