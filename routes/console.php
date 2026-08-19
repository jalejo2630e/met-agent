<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('contact-queues:process')->everyMinute();
Schedule::command('callback-requests:process')->everyMinute();
Schedule::command('messages:check-quota')->hourly();
