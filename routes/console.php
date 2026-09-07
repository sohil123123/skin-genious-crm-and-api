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

// 4. Meta Lead Actions - 7:10 AM daily.
//
// Runs after ai:generate-actions on purpose: the lead engine skips any lead
// whose phone already has a patient action today, so the patient queue must
// exist before this runs or that check has nothing to compare against.
// withoutOverlapping guards the case where a large import makes a run outlast
// the next tick.
Schedule::command('leads:generate-actions')
    ->dailyAt('07:10')
    ->withoutOverlapping();

// Call integrations.
//
// The Callyzer pull runs hourly rather than daily because its window is
// bounded by the API rate limit — one request every two seconds — and a day's
// worth of calls in one run takes long enough to be worth avoiding. Nothing is
// duplicated by running it often: every record is upserted on its provider
// call id, and a run that fails does not advance the cursor, so the next one
// covers the same ground.
Schedule::command('calls:sync-callyzer')
    ->hourly()
    ->withoutOverlapping();

// Sweeps up recordings, transcriptions and customer matches that stalled. The
// recording retry matters most: a provider often publishes a recording URL
// before the file behind it exists, so the first download 404s and nothing
// would ever fetch it again.
Schedule::command('calls:retry')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Retention. Does nothing unless a retention period is configured, so this is
// safe to schedule before anyone has decided on a policy.
Schedule::command('calls:prune')->dailyAt('03:30');

// Lead imports: the wizard deletes each temporary upload once it has copied the
// export, so this only sweeps up files abandoned before that point — someone
// choosing a file and then closing the tab.
Schedule::command('leads:cleanup-temp-uploads')->dailyAt('03:00');
