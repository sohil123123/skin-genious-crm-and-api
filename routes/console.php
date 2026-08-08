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

// Appointment Reminders:
// 1. Appointments 09:00 to 11:30 -> Send previous day at 18:00 (6:00 PM)
Schedule::command('appointments:send-today-whatsapp --mode=previous_day_evening')->dailyAt('18:00');

// 2. Appointments after 11:30 -> Send same day morning at 08:00 (8:00 AM)
Schedule::command('appointments:send-today-whatsapp --mode=same_day_morning')->dailyAt('08:00');

// 3. AI Actions - 7:00 AM daily
Schedule::command('ai:generate-actions')->dailyAt('07:00');

// Lead imports: the wizard deletes each temporary upload once it has copied the
// export, so this only sweeps up files abandoned before that point — someone
// choosing a file and then closing the tab.
Schedule::command('leads:cleanup-temp-uploads')->dailyAt('03:00');
