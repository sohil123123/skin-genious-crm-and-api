<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('leaves:generate-new-year')->yearlyOn(1, 1, '00:00'); // January 1st, midnight
Schedule::command('treatment-plans:cleanup')->daily();
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run')->dailyAt('02:00');

// WhatsApp Automation Scheduler
Schedule::command('whatsapp:process-retries')->everyMinute();
Schedule::command('whatsapp:process-scheduled')->everyMinute();
Schedule::command('whatsapp:cleanup-media 30')->weekly();

