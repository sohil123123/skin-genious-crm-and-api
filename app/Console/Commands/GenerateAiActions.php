<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Services\AiActionService;

class GenerateAiActions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:generate-actions
                            {--clinic= : Specific clinic ID to generate for (default: all active clinics)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate AI-powered daily action recommendations for staff. Run each morning at 7:00 AM.';

    /**
     * Execute the console command.
     */
    public function handle(AiActionService $service): int
    {
        $clinicId = $this->option('clinic');
        $clinicIds = $clinicId ? [(int) $clinicId] : null;

        $this->info('🤖 Generating AI actions for today...');
        $this->newLine();

        $startTime = microtime(true);
        $totalActions = $service->generateForToday($clinicIds);
        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("✅ Generated {$totalActions} action(s) in {$elapsed}s.");

        if ($totalActions > 0) {
            $stats = $service->getSummaryStats(
                clinicId: $clinicId ? (int) $clinicId : null
            );

            $this->newLine();
            $this->table(
                ['Metric', 'Count'],
                [
                    ['🔥 High Priority (70+)', $stats['high_priority']],
                    ['🚨 Rescue Actions', $stats['rescue_count']],
                    ['🎯 Conversion Actions', $stats['conversion_count']],
                    ['🔄 Retention Actions', $stats['retention_count']],
                    ['📊 Capacity Actions', $stats['capacity_count']],
                    ['📅 Empty Slots Today', $stats['empty_slots_today']],
                    ['📈 Est. Potential', "{$stats['estimated_potential_min']}–{$stats['estimated_potential_max']} appointments"],
                ]
            );
        }

        return self::SUCCESS;
    }
}
