<?php

namespace App\Services\Concerns;

use Carbon\Carbon;

/**
 * The Next Best Action priority formula, shared by the patient and lead engines.
 *
 * Extracted verbatim from AiActionService so both queues score on one identical
 * definition. A copied formula would drift the moment either side was tuned,
 * and the two queues would silently stop being comparable — which matters
 * because staff read them as one ranked worklist across two tabs.
 */
trait CalculatesActionPriority
{
    /** Maximum contact attempts in a 7-day window before fatigue penalty */
    protected int $maxContactAttempts = 3;

    /**
     * Calculate a priority score (0-100) from weighted factors.
     *
     * Formula: (intent × recency × treatment_fit × urgency × slot_availability) − fatigue_penalty
     * Then scaled to 0-100.
     *
     * Note on the scale: because five sub-1.0 factors are multiplied, the
     * output compresses hard toward the low end — a candidate strong on every
     * axis (0.9 across the board) still only reaches 59. Scores are therefore
     * meaningful as a *ranking* rather than as a percentage, which is how both
     * queues sort and how the 70+ "high priority" threshold should be read.
     * The behaviour is deliberately preserved as-is so the patient engine's
     * existing scores do not shift.
     */
    protected function calculatePriority(array $factors): int
    {
        $base = ($factors['intent'] ?? 0.5)
            * ($factors['recency'] ?? 0.5)
            * ($factors['treatment_fit'] ?? 0.5)
            * ($factors['urgency'] ?? 0.5)
            * ($factors['slot_availability'] ?? 0.5);

        $penalty = $factors['fatigue_penalty'] ?? 0;

        $score = max(0, ($base * 100) - ($penalty * 30));

        return (int) min(100, round($score));
    }

    /**
     * Calculate contact fatigue penalty (0.0 to 1.0) from recent acted-on
     * actions for one subject.
     *
     * The model class and key column are supplied by the caller so the patient
     * engine counts against ai_action_logs.user_id and the lead engine against
     * lead_action_logs.lead_id, using one implementation.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $logModel
     */
    protected function contactFatiguePenaltyFor(string $logModel, string $keyColumn, int $subjectId): float
    {
        $recentAttempts = $logModel::query()
            ->where($keyColumn, $subjectId)
            ->where('generated_date', '>=', Carbon::today()->subDays(7))
            ->whereNotNull('staff_outcome')
            ->count();

        if ($recentAttempts >= $this->maxContactAttempts) {
            return 1.0;
        }

        return $recentAttempts / $this->maxContactAttempts;
    }
}
