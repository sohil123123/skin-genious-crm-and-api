<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallMatchingStatus;
use App\Enums\Call\CallStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Models\Call;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reusable call metrics, computed in the database rather than in PHP.
 *
 * Written as aggregates on purpose. A clinic doing three hundred calls a day
 * accumulates six figures of rows within a year, and a widget that loads them
 * to count in a loop stops working long before anyone thinks to look at it.
 *
 * Every metric here is deliberately available to the Next Best Action engine as
 * well as to the dashboards: the point of a unified call layer is that the
 * numbers staff read on a screen and the numbers an algorithm acts on come from
 * one definition. Two definitions of "connected call" is how a dashboard and an
 * action queue end up disagreeing about the same patient.
 */
class CallAnalyticsService
{
    /**
     * Headline figures for a period.
     *
     * One query rather than a dozen counts. On a table this size the
     * difference between one indexed scan and twelve is the difference between
     * a dashboard that loads and one people stop opening.
     *
     * @return array<string, int|float|null>
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null, ?int $clinicId = null): array
    {
        $connected = CallStatus::connectedValues();

        $row = $this->baseQuery($from, $to, $clinicId)
            ->selectRaw('COUNT(*) as total_calls')
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as incoming_calls', [CallDirection::Incoming->value])
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as outgoing_calls', [CallDirection::Outgoing->value])
            ->selectRaw('SUM(CASE WHEN call_status = ? THEN 1 ELSE 0 END) as missed_calls', [CallStatus::Missed->value])
            ->selectRaw('SUM(CASE WHEN call_status = ? THEN 1 ELSE 0 END) as rejected_calls', [CallStatus::Rejected->value])
            ->selectRaw('SUM(CASE WHEN is_connected = 1 THEN 1 ELSE 0 END) as connected_calls')
            ->selectRaw('SUM(COALESCE(talk_duration_seconds, duration_seconds, 0)) as total_duration')
            // Averaged over connected calls only. Including ring-outs would
            // drag the mean towards zero and describe nothing that happened.
            ->selectRaw('AVG(CASE WHEN is_connected = 1 THEN COALESCE(talk_duration_seconds, duration_seconds) END) as average_duration')
            ->selectRaw('COUNT(DISTINCT client_phone_key) as unique_customers')
            ->selectRaw('COUNT(DISTINCT agent_user_id) as active_agents')
            ->selectRaw('SUM(CASE WHEN has_recording = 1 THEN 1 ELSE 0 END) as calls_with_recording')
            ->selectRaw('SUM(CASE WHEN transcription_status = ? THEN 1 ELSE 0 END) as calls_transcribed', [TranscriptionStatus::Completed->value])
            ->selectRaw('SUM(CASE WHEN analysis_status = ? THEN 1 ELSE 0 END) as calls_analysed', ['completed'])
            ->selectRaw('SUM(CASE WHEN follow_up_required = 1 AND follow_up_completed_at IS NULL THEN 1 ELSE 0 END) as follow_ups_pending')
            ->selectRaw('SUM(CASE WHEN matching_status IN (?, ?) THEN 1 ELSE 0 END) as unattributed_calls', [
                CallMatchingStatus::Unmatched->value,
                CallMatchingStatus::Ambiguous->value,
            ])
            ->first();

        $total = (int) ($row->total_calls ?? 0);
        $connectedCount = (int) ($row->connected_calls ?? 0);

        return [
            'total_calls' => $total,
            'incoming_calls' => (int) ($row->incoming_calls ?? 0),
            'outgoing_calls' => (int) ($row->outgoing_calls ?? 0),
            'missed_calls' => (int) ($row->missed_calls ?? 0),
            'rejected_calls' => (int) ($row->rejected_calls ?? 0),
            'connected_calls' => $connectedCount,
            // The single most useful number on the page: how much of the
            // clinic's calling actually reached a person.
            'connection_rate' => $total > 0 ? round($connectedCount / $total * 100, 1) : 0.0,
            'total_duration_seconds' => (int) ($row->total_duration ?? 0),
            'average_duration_seconds' => $row->average_duration !== null ? (int) round((float) $row->average_duration) : null,
            'unique_customers' => (int) ($row->unique_customers ?? 0),
            'active_agents' => (int) ($row->active_agents ?? 0),
            'calls_with_recording' => (int) ($row->calls_with_recording ?? 0),
            'calls_transcribed' => (int) ($row->calls_transcribed ?? 0),
            'calls_analysed' => (int) ($row->calls_analysed ?? 0),
            'follow_ups_pending' => (int) ($row->follow_ups_pending ?? 0),
            'unattributed_calls' => (int) ($row->unattributed_calls ?? 0),
        ];
    }

    /**
     * Call counts per day, for a trend chart.
     *
     * @return Collection<int, object>
     */
    public function dailyVolume(Carbon $from, Carbon $to, ?int $clinicId = null): Collection
    {
        return $this->baseQuery($from, $to, $clinicId)
            ->selectRaw('DATE(started_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as incoming', [CallDirection::Incoming->value])
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as outgoing', [CallDirection::Outgoing->value])
            ->selectRaw('SUM(CASE WHEN is_connected = 1 THEN 1 ELSE 0 END) as connected')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    /**
     * Call counts per hour of day, for staffing decisions.
     *
     * The question this answers is when the clinic is missing calls, which is
     * almost never spread evenly and is usually invisible in a daily total.
     *
     * @return Collection<int, object>
     */
    public function hourlyDistribution(Carbon $from, Carbon $to, ?int $clinicId = null): Collection
    {
        return $this->baseQuery($from, $to, $clinicId)
            ->selectRaw('HOUR(started_at) as hour')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN call_status = ? THEN 1 ELSE 0 END) as missed', [CallStatus::Missed->value])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    /**
     * Per-agent performance for a period.
     *
     * @return Collection<int, object>
     */
    public function agentPerformance(?Carbon $from = null, ?Carbon $to = null, ?int $clinicId = null): Collection
    {
        return $this->baseQuery($from, $to, $clinicId)
            ->whereNotNull('agent_user_id')
            ->selectRaw('agent_user_id')
            ->selectRaw('COUNT(*) as total_calls')
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as incoming_calls', [CallDirection::Incoming->value])
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as outgoing_calls', [CallDirection::Outgoing->value])
            ->selectRaw('SUM(CASE WHEN is_connected = 1 THEN 1 ELSE 0 END) as connected_calls')
            ->selectRaw('SUM(CASE WHEN call_status = ? THEN 1 ELSE 0 END) as missed_calls', [CallStatus::Missed->value])
            ->selectRaw('SUM(COALESCE(talk_duration_seconds, duration_seconds, 0)) as total_talk_seconds')
            ->selectRaw('AVG(CASE WHEN is_connected = 1 THEN COALESCE(talk_duration_seconds, duration_seconds) END) as average_talk_seconds')
            ->selectRaw('COUNT(DISTINCT client_phone_key) as unique_customers')
            ->selectRaw('SUM(CASE WHEN follow_up_required = 1 AND follow_up_completed_at IS NOT NULL THEN 1 ELSE 0 END) as follow_ups_completed')
            ->selectRaw('SUM(CASE WHEN follow_up_required = 1 THEN 1 ELSE 0 END) as follow_ups_total')
            ->groupBy('agent_user_id')
            ->orderByDesc('total_calls')
            ->with('agent:id,first_name,last_name')
            ->get();
    }

    /**
     * Everything the CRM knows about its calling relationship with one person.
     *
     * This is the shape the Next Best Action engine consumes. It is
     * deliberately a set of facts rather than a recommendation: "eleven days
     * since the last call, three missed in a row, no conversation since March"
     * is reusable by any rule, while a baked-in score is reusable by none.
     *
     * @return array<string, mixed>
     */
    public function customerEngagement(?int $userId, ?int $leadId = null, ?string $phoneKey = null): array
    {
        $query = Call::query()->forCustomer($userId, $leadId, $phoneKey);

        $row = (clone $query)
            ->selectRaw('COUNT(*) as total_calls')
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as incoming_calls', [CallDirection::Incoming->value])
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as outgoing_calls', [CallDirection::Outgoing->value])
            ->selectRaw('SUM(CASE WHEN call_status = ? THEN 1 ELSE 0 END) as missed_calls', [CallStatus::Missed->value])
            ->selectRaw('SUM(CASE WHEN is_connected = 1 THEN 1 ELSE 0 END) as connected_calls')
            ->selectRaw('SUM(COALESCE(talk_duration_seconds, duration_seconds, 0)) as total_talk_seconds')
            ->selectRaw('AVG(CASE WHEN is_connected = 1 THEN COALESCE(talk_duration_seconds, duration_seconds) END) as average_talk_seconds')
            ->selectRaw('MAX(started_at) as last_call_at')
            ->selectRaw('MAX(CASE WHEN direction = ? THEN started_at END) as last_incoming_at', [CallDirection::Incoming->value])
            ->selectRaw('MAX(CASE WHEN direction = ? THEN started_at END) as last_outgoing_at', [CallDirection::Outgoing->value])
            ->selectRaw('MAX(CASE WHEN is_connected = 1 THEN started_at END) as last_connected_at')
            ->selectRaw('SUM(CASE WHEN follow_up_required = 1 AND follow_up_completed_at IS NULL THEN 1 ELSE 0 END) as follow_ups_pending')
            ->first();

        $lastCallAt = $row?->last_call_at !== null ? Carbon::parse($row->last_call_at) : null;
        $lastConnectedAt = $row?->last_connected_at !== null ? Carbon::parse($row->last_connected_at) : null;

        return [
            'total_calls' => (int) ($row->total_calls ?? 0),
            'incoming_calls' => (int) ($row->incoming_calls ?? 0),
            'outgoing_calls' => (int) ($row->outgoing_calls ?? 0),
            'missed_calls' => (int) ($row->missed_calls ?? 0),
            'connected_calls' => (int) ($row->connected_calls ?? 0),
            'total_talk_seconds' => (int) ($row->total_talk_seconds ?? 0),
            'average_talk_seconds' => $row?->average_talk_seconds !== null
                ? (int) round((float) $row->average_talk_seconds)
                : null,
            'last_call_at' => $lastCallAt,
            'last_incoming_call_at' => $row?->last_incoming_at !== null ? Carbon::parse($row->last_incoming_at) : null,
            'last_outgoing_call_at' => $row?->last_outgoing_at !== null ? Carbon::parse($row->last_outgoing_at) : null,
            'last_connected_call_at' => $lastConnectedAt,
            // Floored to whole days. Carbon returns a float, and "3.0000084
            // days since the last call" is not a number any rule should be
            // comparing against a threshold.
            'days_since_last_call' => $lastCallAt !== null
                ? (int) floor($lastCallAt->diffInDays(now(), absolute: true))
                : null,
            // Distinct from days_since_last_call and more useful: three
            // unanswered attempts are not contact, and a rule that treats them
            // as contact stops chasing a patient who was never reached.
            'days_since_last_conversation' => $lastConnectedAt !== null
                ? (int) floor($lastConnectedAt->diffInDays(now(), absolute: true))
                : null,
            'follow_ups_pending' => (int) ($row->follow_ups_pending ?? 0),
            // A run of missed calls with nothing connected is the signal that a
            // different channel is needed, not another call.
            'consecutive_unanswered' => $this->consecutiveUnanswered($userId, $leadId, $phoneKey),
        ];
    }

    /**
     * How many attempts in a row have gone unanswered.
     */
    protected function consecutiveUnanswered(?int $userId, ?int $leadId, ?string $phoneKey): int
    {
        $recent = Call::query()
            ->forCustomer($userId, $leadId, $phoneKey)
            ->whereNotNull('started_at')
            ->latest('started_at')
            ->limit(20)
            ->pluck('is_connected');

        $streak = 0;

        foreach ($recent as $connected) {
            if ($connected) {
                break;
            }

            $streak++;
        }

        return $streak;
    }

    /**
     * Engagement figures for a patient, by their id.
     *
     * The phone key is included so calls that arrived before this person was in
     * the CRM still count towards their history — otherwise a lead who becomes
     * a patient appears to have never been spoken to.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        return $this->customerEngagement(
            $user->getKey(),
            null,
            app(PhoneNumberNormalizer::class)->matchKey($user->mobile),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function forLead(Lead $lead): array
    {
        return $this->customerEngagement(
            $lead->matched_user_id,
            $lead->getKey(),
            app(PhoneNumberNormalizer::class)->matchKey($lead->phone),
        );
    }

    /**
     * The base query every metric is built on.
     *
     * Scoped to the current clinic like everything else in this CRM, and to
     * calls that actually have a start time — a call still ringing has no place
     * in a completed-period report.
     */
    protected function baseQuery(?Carbon $from, ?Carbon $to, ?int $clinicId): Builder
    {
        return Call::query()
            ->whereNotNull('started_at')
            ->when($from !== null, fn (Builder $query): Builder => $query->where('started_at', '>=', $from))
            ->when($to !== null, fn (Builder $query): Builder => $query->where('started_at', '<=', $to))
            ->when($clinicId !== null, fn (Builder $query): Builder => $query->where('clinic_id', $clinicId))
            ->when($clinicId === null, fn (Builder $query): Builder => $query->forCurrentClinic());
    }
}
