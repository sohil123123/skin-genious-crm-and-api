<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallStatus;
use App\Events\Call\CallAnnounced;
use App\Models\Call;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Puts call cards on the clinic's screens without waiting for a phone to ring.
 *
 * The popup is the one part of this system that cannot be checked by reading a
 * database row: it either appears on a screen at the right moment, worded the
 * right way, or it does not. Testing it against real traffic means placing a
 * call and hoping — and three of the four cards are hard to produce on demand,
 * since a live outgoing card needs a provider that reports a call before it
 * ends.
 *
 * So this borrows a real call and re-announces it under each combination. The
 * borrowed record is modified in memory and never saved: ShouldBroadcastNow is
 * dispatched through dispatchNow() rather than a queue, so the event carries
 * the instance as-is instead of re-reading it from the database.
 *
 * It also bypasses the recency window in CallIngestionService, which is why it
 * works on a call from last week: that guard belongs to ingestion, not to the
 * event, and skipping it is the entire point of a demonstration.
 */
class DemoCallPopup extends Command
{
    protected $signature = 'calls:demo-popup
        {--direction=* : incoming, outgoing. Both by default.}
        {--state=* : live, ended. Both by default.}
        {--call= : Borrow a specific call id rather than the most recent one.}
        {--clinic= : Broadcast to a different clinic than the borrowed call belongs to.}';

    protected $description = 'Send demonstration call popups to the clinic screens';

    public function handle(): int
    {
        // Real staff watch these channels. A demonstration card telling
        // reception that a patient is on the line is a person picking up a
        // phone that never rang.
        if (app()->isProduction()) {
            $this->error('Refusing to send demonstration popups on production.');

            return self::FAILURE;
        }

        $call = $this->borrowedCall();

        if ($call === null) {
            $this->error('No call with a clinic to borrow.');
            $this->line('  The card is delivered on a clinic channel, so the call needs one.');
            $this->line('  Ingest a call first, or pass --call= and --clinic= explicitly.');

            return self::FAILURE;
        }

        if ($clinic = $this->option('clinic')) {
            $call->clinic_id = (int) $clinic;
        }

        $directions = $this->chosen('direction', ['incoming', 'outgoing']);
        $states = $this->chosen('state', ['live', 'ended']);

        if ($directions === [] || $states === []) {
            $this->error('Unrecognised --direction or --state.');
            $this->line('  Directions: incoming, outgoing. States: live, ended.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Borrowing call #%d on clinic %d. Nothing is saved.',
            $call->getKey(),
            $call->clinic_id,
        ));

        $this->newLine();

        foreach ($directions as $direction) {
            foreach ($states as $state) {
                $call->direction = CallDirection::from($direction);

                $call->call_status = $state === 'live'
                    ? ($direction === 'incoming' ? CallStatus::Ringing : CallStatus::InProgress)
                    : CallStatus::Completed;

                // The popup draws a given call once, by uuid. Without a fresh
                // one each time only the first of these four would appear.
                $call->uuid = (string) Str::uuid();

                CallAnnounced::dispatch($call);

                $this->line(sprintf('  %-9s %-6s  %s', $direction, $state, $this->wording($direction, $state)));
            }
        }

        $this->newLine();
        $this->comment('Reverb must be running, and the browser signed in as someone who can watch that clinic.');

        return self::SUCCESS;
    }

    /**
     * The call whose details the cards borrow.
     */
    protected function borrowedCall(): ?Call
    {
        if ($id = $this->option('call')) {
            return Call::find($id);
        }

        // Preferring a matched call is not vanity: a card for a known patient
        // exercises the name, the initials and the "Open patient" link, where
        // an unmatched one leaves most of the card empty.
        return Call::query()
            ->whereNotNull('clinic_id')
            ->orderByRaw('customer_user_id IS NULL')
            ->latest('id')
            ->first();
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    protected function chosen(string $option, array $allowed): array
    {
        $given = array_map('strtolower', (array) $this->option($option));

        return $given === [] ? $allowed : array_values(array_intersect($allowed, $given));
    }

    protected function wording(string $direction, string $state): string
    {
        return match (true) {
            $direction === 'incoming' && $state === 'live' => 'Incoming call (green, ringing)',
            $direction === 'outgoing' && $state === 'live' => 'Calling out (blue, ringing)',
            $direction === 'incoming' => 'Incoming call ended (grey)',
            default => 'Outgoing call ended (blue)',
        };
    }
}
