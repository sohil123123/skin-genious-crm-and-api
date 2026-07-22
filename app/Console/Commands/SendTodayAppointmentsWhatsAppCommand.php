<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendTodayAppointmentsWhatsAppJob;
use Carbon\Carbon;

class SendTodayAppointmentsWhatsAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-today-whatsapp
                            {--date= : Target date in YYYY-MM-DD format}
                            {--template= : Custom WhatsApp template name to override setting}
                            {--clinic= : Filter by clinic ID}
                            {--force : Force send reminders even if already sent recently}
                            {--mode= : Slot mode (previous_day_evening or same_day_morning)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send queued WhatsApp template messages to clients scheduled for appointments';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $date = $this->option('date') ?: null;
        $template = $this->option('template') ?: null;
        $clinicId = $this->option('clinic') ? (int) $this->option('clinic') : null;
        $force = (bool) $this->option('force');
        $mode = $this->option('mode') ?: null;

        $targetDateStr = $date ?: ($mode === 'previous_day_evening' ? Carbon::tomorrow()->format('Y-m-d') : Carbon::today()->format('Y-m-d'));

        $this->info("Dispatching WhatsApp reminder queue job (mode: " . ($mode ?? 'all') . ", date: {$targetDateStr})...");

        SendTodayAppointmentsWhatsAppJob::dispatch(
            $date,
            $template,
            $clinicId,
            $force,
            $mode
        );

        $this->info('Job successfully dispatched to the queue!');

        return Command::SUCCESS;
    }
}
