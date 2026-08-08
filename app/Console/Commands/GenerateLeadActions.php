<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lead\LeadActionService;
use Illuminate\Console\Command;

/**
 * Builds the Meta lead action queue for the day.
 *
 * Deliberately mirrors ai:generate-actions — same options, same output shape —
 * so the two morning jobs are read and operated the same way. It runs a few
 * minutes after the patient job because the lead engine suppresses any lead
 * whose phone already has a patient action today; if it ran first, that
 * cross-queue check would have nothing to look at.
 */
class GenerateLeadActions extends Command
{
    protected $signature = 'leads:generate-actions
                            {--clinic= : Specific clinic ID to generate for (default: all active clinics)}';

    protected $description = 'Generate the daily Next Best Action queue from imported Meta leads. Run each morning at 7:10 AM.';

    public function handle(LeadActionService $service): int
    {
        $clinicId = $this->option('clinic');
        $clinicIds = $clinicId ? [(int) $clinicId] : null;

        $this->info('📣 Generating Meta lead actions for today...');
        $this->newLine();

        $startTime = microtime(true);
        $totalActions = $service->generateForToday($clinicIds);
        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("✅ Generated {$totalActions} lead action(s) in {$elapsed}s.");

        if ($totalActions > 0) {
            $stats = $service->getSummaryStats(
                clinicId: $clinicId ? (int) $clinicId : null
            );

            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['🔥 Wants to Visit Now', $stats['hot_now']],
                    ['⭐ High Priority (' . LeadActionService::HIGH_PRIORITY_THRESHOLD . '+)', $stats['high_priority']],
                    ['🏥 Already Patients', $stats['existing_patients']],
                    ['📞 Never Contacted', $stats['never_contacted']],
                    ['⏳ Aging & Untouched', $stats['aging_uncontacted']],
                    ['📋 Open Leads', $stats['total_open_leads']],
                ]
            );

            // Every lead here was paid for, so a queue with nothing urgent in it
            // is worth saying out loud rather than leaving buried in the table.
            if ($stats['hot_now'] === 0 && $stats['aging_uncontacted'] > 0) {
                $this->newLine();
                $this->warn(
                    "⚠️  No leads want to visit imminently, but {$stats['aging_uncontacted']} are still uncontacted. " .
                    'Import a fresh export to surface current enquiries.'
                );
            }
        }

        return self::SUCCESS;
    }
}
