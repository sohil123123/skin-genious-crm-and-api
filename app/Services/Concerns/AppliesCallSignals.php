<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\Setting;
use App\Services\Call\CallSignalReader;
use Illuminate\Support\Collection;

/**
 * Lets a Next Best Action engine hear what was said on the phone.
 *
 * Shared by the patient and lead engines for the same reason the priority
 * formula is: staff read the two queues as one ranked worklist, and a patient
 * and a lead who said the same sentence must be treated the same way. Two
 * implementations of "somebody refused" would drift the first time either was
 * tuned, and the drift would be invisible until a person who declined got rung
 * anyway.
 *
 * The engines keep every existing trigger, every existing threshold, and the
 * shared multiplicative formula. This only tilts the result afterwards, within
 * a bound, and only when switched on.
 */
trait AppliesCallSignals
{
    protected ?CallSignalReader $callSignalReader = null;

    protected function callSignals(): CallSignalReader
    {
        return $this->callSignalReader ??= app(CallSignalReader::class);
    }

    /**
     * Whether call insight is allowed to move existing scores.
     *
     * Off by default. The new call-driven triggers add actions that did not
     * exist before, which is additive and safe; this changes the ranking of
     * actions the engines have been producing in production for months, and
     * that is a different kind of change to make silently.
     */
    protected function callSignalModifierEnabled(): bool
    {
        return (bool) Setting::getConfigured(
            'call_signal_modifier_enabled',
            config('calls.signals.modifier_enabled', false),
        );
    }

    /**
     * Adjust a score by what recent calls said, and explain the adjustment.
     *
     * Returns the score untouched when the modifier is off or nothing was said,
     * so a caller can apply this unconditionally without branching.
     *
     * @param  Collection<int, \App\Models\CallInsightSignal>  $signals
     * @return array{score: int, basis: array<int, array<string, mixed>>, note: ?string}
     */
    protected function applyCallSignals(int $score, Collection $signals): array
    {
        if ($signals->isEmpty()) {
            return ['score' => $score, 'basis' => [], 'note' => null];
        }

        $reader = $this->callSignals();

        // The explanation is produced whether or not the modifier is on, and
        // that separation is the point. The setting exists because moving a
        // score changes which patient a clinic rings first, and that should not
        // change silently. Telling a staff member what was said on the phone
        // changes nothing about the ordering and is useful on its own — a card
        // reading "enquired yesterday" when the clinic spoke to them this
        // morning is wrong in a way no ranking preference justifies.
        $basis = $reader->toBasis($signals);
        $note = $reader->explain($signals);

        if (! $this->callSignalModifierEnabled()) {
            return ['score' => $score, 'basis' => $basis, 'note' => $note];
        }

        // Bounded in both directions. A conversation should tilt a ranking, not
        // decide it: the engines' own arithmetic knows about packages, no-shows
        // and appointment history, none of which a transcript can see.
        $ceiling = (float) config('calls.signals.max_influence', 0.40);
        $influence = max(-$ceiling, min($ceiling, $reader->pressure($signals)));

        return [
            'score' => max(0, min(100, (int) round($score * (1 + $influence)))),
            'basis' => $basis,
            'note' => $note,
        ];
    }

    /**
     * A script that opens from the conversation rather than from the form.
     *
     * The generic script is written for somebody nobody has spoken to: "thank
     * you for your enquiry, would you like to book a skin analysis". Read out
     * to a person the clinic rang yesterday, it tells them they were not
     * listened to — which is worse than having no script at all.
     *
     * Built from the strongest signal that has something sayable attached.
     * Sentiment and engagement have nothing, so a call that established only
     * "sounded neutral" leaves the original script alone rather than replacing
     * it with something vague.
     *
     * @param  Collection<int, \App\Models\CallInsightSignal>  $signals
     */
    protected function callAwareScript(?string $firstName, Collection $signals): ?string
    {
        if ($signals->isEmpty()) {
            return null;
        }

        $window = $this->callSignals()->windowDays();

        // Ordered for a conversation, not for a queue: complaint, then what was
        // asked for, then the objection, then the sell. Within a rank the
        // freshest and most confident wins.
        $line = $signals
            ->sortBy([
                fn (\App\Models\CallInsightSignal $a, \App\Models\CallInsightSignal $b): int
                    => $a->signal_key->scriptRank() <=> $b->signal_key->scriptRank(),
                fn (\App\Models\CallInsightSignal $a, \App\Models\CallInsightSignal $b): int
                    => $b->weight($window) <=> $a->weight($window),
            ])
            ->map(fn (\App\Models\CallInsightSignal $signal): ?string => $signal->signal_key->scriptLine($signal->value))
            ->first(fn (?string $line): bool => $line !== null);

        if ($line === null) {
            return null;
        }

        return sprintf('Hi %s, following up on our call. %s', $firstName ?: 'there', $line);
    }

    /**
     * Whether a recent call means this person should be left alone today.
     *
     * Separate from the score, because refusal is not a weak negative to be
     * outvoted by three older reasons to call. Somebody who said "I am not
     * interested" on Tuesday should not be in Wednesday's queue at all, and a
     * booking agreed on the phone this morning makes every chase about it wrong
     * — including in the hours before it reaches the diary, which is exactly
     * when the queue would otherwise still be recommending it.
     *
     * Applies even with the modifier off: not calling somebody who declined is
     * not a tuning preference.
     *
     * @param  Collection<int, \App\Models\CallInsightSignal>  $signals
     */
    protected function callSignalsSuppress(Collection $signals): bool
    {
        if ($signals->isEmpty()) {
            return false;
        }

        $reader = $this->callSignals();

        return $reader->hasRefused($signals) || $reader->hasJustBooked($signals);
    }

    /**
     * Append the conversational reason to a trigger's own reason text.
     *
     * Appended rather than replacing, because the existing sentence explains
     * the CRM fact that raised the action — the cancelled appointment, the
     * unused package — and the call explains what the person said about it.
     * Staff need both to open a conversation.
     */
    protected function withCallReason(string $reason, ?string $note): string
    {
        return $note === null ? $reason : rtrim($reason) . ' ' . $note;
    }
}
