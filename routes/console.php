<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send the store's daily closing report to the configured super-admin email.
Schedule::command('store:daily-report')->dailyAt('23:59')->withoutOverlapping();

// Daily WhatsApp stock digest and encrypted off-site database backup.
Schedule::command('store:whatsapp-stock-alerts')->dailyAt('09:00')->withoutOverlapping();
Schedule::command('store:backup-cloud')->dailyAt('02:00')->withoutOverlapping();

// Send due medicine refill reminders to customers who explicitly opted in.
Schedule::command('store:send-refill-reminders')->dailyAt('10:00')->withoutOverlapping();

// Keep supplier contract status aligned with its validity period.
Schedule::command('store:expire-supplier-contracts')->dailyAt('00:20')->withoutOverlapping();
