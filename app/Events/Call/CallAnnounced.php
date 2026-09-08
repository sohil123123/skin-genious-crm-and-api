<?php

declare(strict_types=1);

namespace App\Events\Call;

use App\Models\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A call just happened on this clinic's lines, and its screens should say so.
 *
 * Covers both directions and both moments. An Exotel call announces itself
 * while the phone is still ringing, which is the classic screen pop. A Callyzer
 * call cannot: its log leaves the agent's handset after the conversation ends,
 * so the card says the call just finished and offers somewhere to record the
 * outcome while it is still in mind. Both are worth showing; pretending the
 * second is the first would be a lie the timestamp gives away.
 *
 * Broadcast rather than polled: a phone rings for about thirty seconds, and a
 * popup that arrives after the receptionist has already said "hello" is worse
 * than no popup at all — it tells them who they are talking to a beat after
 * they needed to know.
 *
 * Scoped to one clinic's private channel. A call to the Jaipur Exophone is that
 * branch's conversation, and putting patient names on a channel every
 * authenticated user can subscribe to would leak them across the whole
 * business.
 *
 * ShouldBroadcastNow rather than ShouldBroadcast because everything that fires
 * this is already running on the queue. Queueing it again would add a second
 * hop, and every hop is delay a caller can hear.
 */
class CallAnnounced implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Call $call,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('clinic.' . $this->call->clinic_id . '.calls'),
        ];
    }

    /**
     * A short name, so the JavaScript listener is not coupled to the PHP
     * namespace — moving this class must not break the front end.
     */
    public function broadcastAs(): string
    {
        return 'call-announced';
    }

    /**
     * What the popup needs, and nothing more.
     *
     * Deliberately excludes the raw provider payload, the agent's personal
     * number and the recording state. This travels over a socket to every
     * logged-in member of the clinic, so it carries only what somebody about to
     * answer a phone actually has to see.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $call = $this->call;

        return [
            'uuid' => $call->uuid,
            'call_id' => $call->getKey(),

            // The number as a human reads it, falling back to whatever the
            // provider sent if it could not be normalised.
            'phone' => $call->client_phone_normalized ?: $call->client_phone,

            // Null when nobody was matched, which the popup renders as an
            // unknown caller rather than inventing a name.
            'name' => $call->customer_user_id !== null || $call->lead_id !== null
                ? $call->customer_name
                : null,

            'customer_user_id' => $call->customer_user_id,
            'lead_id' => $call->lead_id,

            // Drives the badge. "Existing patient" and "new caller" lead to
            // different opening lines, which is the whole point of a screen pop.
            'is_patient' => $call->customer_user_id !== null,
            'is_lead' => $call->customer_user_id === null && $call->lead_id !== null,
            'is_ambiguous' => $call->matching_status?->value === 'ambiguous',

            // Which of the clinic's numbers they rang.
            'exophone' => $call->virtual_number_normalized ?: $call->virtual_number,

            // Drives the card's wording. "Incoming call" and "Outgoing call"
            // are opposite instructions to whoever is reading it.
            'direction' => $call->direction?->value,

            // Whether the phone is still ringing. False means the call is
            // already over and the card is a prompt to log it, not to answer
            // it - the difference between a screen pop and a receipt.
            'is_live' => $call->call_status?->isInFlight() ?? false,

            'started_at' => $call->started_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
