<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\Enums\Call\CallSignalKey;
use App\Models\CallInsightSignal;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * What recent calls say about a patient or a lead.
 *
 * One reader for both Next Best Action engines, for the same reason the
 * priority formula is shared: staff read the two queues as one worklist, and
 * two implementations of "does this person have an open price objection" would
 * drift the moment either was tuned. A lead and a patient who said the same
 * sentence must be treated the same way.
 *
 * The reader answers three questions and nothing else — which signals are live,
 * how hard they push, and how to say why in a sentence. Deciding what to do
 * about them belongs to the engines, which know about appointments, packages
 * and lead status.
 */
class CallSignalReader
{
    /**
     * Everything a subject's recent calls established, strongest first.
     *
     * @return Collection<int, CallInsightSignal>
     */
    public function forPatient(int $userId): Collection
    {
        return $this->query()->forPatient($userId)->get();
    }

    /**
     * @return Collection<int, CallInsightSignal>
     */
    public function forLead(int $leadId): Collection
    {
        return $this->query()->forLead($leadId)->get();
    }

    /**
     * Signals for many patients at once, keyed by patient id.
     *
     * The engines score a whole clinic in one pass, and asking per subject
     * would put a query inside a loop over every patient with an outstanding
     * action — the shape that turns a morning job into a slow one. Subjects
     * with nothing to say get an empty collection rather than being absent, so
     * callers never have to check.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, Collection<int, CallInsightSignal>>
     */
    public function forPatients(array $userIds): Collection
    {
        return $this->batch('customer_user_id', $userIds);
    }

    /**
     * @param  array<int, int>  $leadIds
     * @return Collection<int, Collection<int, CallInsightSignal>>
     */
    public function forLeads(array $leadIds): Collection
    {
        return $this->batch('lead_id', $leadIds);
    }

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Collection<int, CallInsightSignal>>
     */
    protected function batch(string $column, array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return collect();
        }

        $grouped = $this->query()
            ->whereIn($column, $ids)
            ->get()
            ->groupBy($column);

        return collect($ids)->mapWithKeys(fn (int $id): array => [
            $id => $grouped->get($id, collect()),
        ]);
    }

    /**
     * The net argument for contacting this person sooner or leaving them alone.
     *
     * Positive means the conversation asked for follow-up; negative means it
     * asked to be left alone, and that direction matters more than the
     * magnitude — a person who said "not interested" should drop down the queue
     * even if three older signals argue for calling them.
     *
     * Every signal is weighted by age and confidence, so a fresh certain
     * refusal outweighs a stale uncertain enthusiasm.
     *
     * @param  Collection<int, CallInsightSignal>  $signals
     */
    public function pressure(Collection $signals): float
    {
        $window = $this->windowDays();

        return (float) $signals->sum(
            fn (CallInsightSignal $signal): float => $signal->signal_key->pressure() * $signal->weight($window)
        );
    }

    /**
     * Whether the subject has recently and confidently refused.
     *
     * Its own question rather than a number, because refusal is not a weak
     * negative to be outvoted: somebody who said no should not be rung again
     * this week however good the other reasons look. The engines use this to
     * suppress rather than to score.
     *
     * @param  Collection<int, CallInsightSignal>  $signals
     */
    public function hasRefused(Collection $signals): bool
    {
        return $signals->contains(
            fn (CallInsightSignal $signal): bool => $signal->signal_key === CallSignalKey::NotInterested
        );
    }

    /**
     * Whether an appointment was agreed on a recent call.
     *
     * The engines already skip patients with a future appointment in the diary.
     * This covers the gap before it gets there: a booking agreed on the phone
     * this morning may not be entered until the afternoon, and chasing somebody
     * in that window is the most visible way for this system to look broken.
     *
     * @param  Collection<int, CallInsightSignal>  $signals
     */
    public function hasJustBooked(Collection $signals): bool
    {
        return $signals->contains(
            fn (CallInsightSignal $signal): bool => $signal->signal_key === CallSignalKey::AppointmentBooked
        );
    }

    /**
     * The signals worth naming in a reason, strongest argument first.
     *
     * @param  Collection<int, CallInsightSignal>  $signals
     * @return Collection<int, CallInsightSignal>
     */
    public function notable(Collection $signals, int $limit = 3): Collection
    {
        $window = $this->windowDays();

        return $signals
            ->sortByDesc(fn (CallInsightSignal $signal): float => abs($signal->signal_key->pressure()) * $signal->weight($window))
            ->take($limit)
            ->values();
    }

    /**
     * A sentence a staff member can read before picking up the phone.
     *
     * Written as what the person said rather than as signal names, because the
     * reason field is read aloud in effect — somebody is about to ring this
     * patient, and "price_objection, information_requested" tells them nothing
     * they can open a conversation with.
     *
     * @param  Collection<int, CallInsightSignal>  $signals
     */
    public function explain(Collection $signals): ?string
    {
        // Signals that argue neither way are dropped. "They sounded neutral" is
        // true and useless: it takes up one of the three slots without telling
        // anybody anything they can use, and a reason full of it stops being
        // read at all.
        $notable = $this->notable(
            $signals->filter(fn (CallInsightSignal $signal): bool => $signal->signal_key->pressure() !== 0.0)
        );

        if ($notable->isEmpty()) {
            return null;
        }

        // The wording lives on the enum, where the match is exhaustive: a key
        // with no phrase is a fatal error rather than a reason that reads
        // "they appointment intent, staff followup required".
        $phrases = $notable->map(
            fn (CallInsightSignal $signal): string => $signal->signal_key->phrase($signal->value)
        );

        $when = $notable->first()?->occurred_at?->diffForHumans() ?? 'recently';

        return 'On the call ' . $when . ' they ' . $this->join($phrases->all()) . '.';
    }

    /**
     * The structured basis, for the action log's call_signals column.
     *
     * @param  Collection<int, CallInsightSignal>  $signals
     * @return array<int, array<string, mixed>>
     */
    public function toBasis(Collection $signals): array
    {
        $window = $this->windowDays();

        return $this->notable($signals, 6)
            ->map(fn (CallInsightSignal $signal): array => [
                'key' => $signal->signal_key->value,
                'type' => $signal->signal_type->value,
                'confidence' => $signal->confidence,
                'weight' => round($signal->weight($window), 3),
                'call_id' => $signal->call_id,
                'occurred_at' => $signal->occurred_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The base query: recent enough to matter, confident enough to act on.
     */
    protected function query(): \Illuminate\Database\Eloquent\Builder
    {
        return CallInsightSignal::query()
            ->recent($this->windowDays())
            ->confident($this->confidenceThreshold())
            ->orderByDesc('occurred_at');
    }

    /**
     * How far back a call still says something about somebody.
     *
     * A price objection from six months ago is history; from yesterday it is
     * the reason to send a payment plan. The window is where that judgement
     * lives, and it is configurable because clinics differ on how long a
     * conversation stays true.
     */
    public function windowDays(): int
    {
        return max(1, (int) Setting::getConfigured(
            'call_signal_window_days',
            config('calls.signals.window_days', 45),
        ));
    }

    /**
     * How long to leave a promise alone before queueing it as outstanding.
     *
     * Somebody who asked for a price list ten minutes ago is being dealt with
     * by the person who took the call, and putting them in a work queue asks a
     * colleague to duplicate it. A few hours later nobody is holding it any
     * more and it is genuinely outstanding.
     *
     * Measured in hours, and that is the correction. The first version of this
     * asked for a whole calendar day and compared against midnight, so a call
     * at 10:29 yesterday morning counted as zero days old this morning and was
     * skipped — as was every call, until it was nearly two days old. The queue
     * is built at 07:00 each day, so a rule that cannot see yesterday's calls
     * cannot see calls at all.
     */
    public function cooloffHours(): int
    {
        return max(0, (int) Setting::getConfigured(
            'call_signal_cooloff_hours',
            config('calls.signals.cooloff_hours', 2),
        ));
    }

    /**
     * Whether enough time has passed to treat this as somebody's outstanding work.
     */
    public function isDue(?\Illuminate\Support\Carbon $occurredAt): bool
    {
        if ($occurredAt === null) {
            return false;
        }

        return $occurredAt->copy()->addHours($this->cooloffHours())->isPast();
    }

    /**
     * The confidence below which a signal is evidence but not an instruction.
     */
    public function confidenceThreshold(): float
    {
        return (float) Setting::getConfigured(
            'call_signal_min_confidence',
            config('calls.signals.min_confidence', 0.65),
        );
    }

    /**
     * @param  array<int, string>  $parts
     */
    protected function join(array $parts): string
    {
        if (count($parts) <= 1) {
            return $parts[0] ?? '';
        }

        $last = array_pop($parts);

        return implode(', ', $parts) . ' and ' . $last;
    }
}
