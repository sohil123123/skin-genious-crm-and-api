<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserLeaveEntitlement;
use Carbon\Carbon;

class GenerateNewYearEntitlements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leaves:generate-new-year';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create new leave entitlements for all therapist users for the new year';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $newYear = Carbon::now()->year;
        $previousYear = $newYear - 1;

        // ✅ Only therapists (case-insensitive role check)
        $therapists = User::role('therapist')->with('leaveEntitlements')->get();

        $count = 0;

        foreach ($therapists as $user) {
            $previousEntitlements = $user->leaveEntitlements()
                ->where('year', $previousYear)
                ->get();

            // If no previous record (first year), assign defaults
            if ($previousEntitlements->isEmpty()) {
                $this->createDefaultEntitlements($user, $newYear);
                $count++;
                continue;
            }

            foreach ($previousEntitlements as $old) {
                $leaveType = strtolower(trim($old->leave_type)); // normalize case

                // (Optional) Carry forward logic — uncomment if needed
                // $carryForward = 0;
                // if ($leaveType === 'paid') {
                //     $carryForward = min($old->remaining ?? 0, 5);
                // }

                // ✅ Check duplicates case-insensitively
                $exists = $user->leaveEntitlements()
                    ->where('year', $newYear)
                    ->whereRaw('LOWER(leave_type) = ?', [$leaveType])
                    ->exists();

                if ($exists) {
                    $this->warn("⚠️ Skipped duplicate for {$user->name} ({$leaveType}, {$newYear})");
                    continue;
                }

                $user->leaveEntitlements()->create([
                    'leave_type'     => $leaveType,
                    'total_allowed'  => $old->total_allowed, // + $carryForward if needed
                    'used'           => 0,
                    'remaining'      => $old->total_allowed, // + $carryForward if needed
                    'year'           => $newYear,
                ]);

                $count++;
            }
        }

        $this->info("✅ {$count} new leave entitlement records created for Therapist users for {$newYear}.");
    }

    /**
     * Assign default entitlements for new therapists (no previous record).
     */
    private function createDefaultEntitlements($user, $year): void
    {
        $defaults = [
            ['leave_type' => 'paid',   'total_allowed' => 12],
            ['leave_type' => 'unpaid', 'total_allowed' => 10],
            ['leave_type' => 'sick',   'total_allowed' => 5],
            ['leave_type' => 'emergency',  'total_allowed' => 2],
            ['leave_type' => 'other',  'total_allowed' => 0],
        ];

        foreach ($defaults as $type) {
            $user->leaveEntitlements()->firstOrCreate(
                [
                    'year'        => $year,
                    'leave_type'  => strtolower($type['leave_type']),
                ],
                [
                    'total_allowed' => $type['total_allowed'],
                    'used'          => 0,
                    'remaining'     => $type['total_allowed'],
                ]
            );
        }
    }
}
