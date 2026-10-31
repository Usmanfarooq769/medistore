<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Send the store's daily closing report to the configured super-admin email.
Schedule::command('store:daily-report')->dailyAt('23:59')->withoutOverlapping();
