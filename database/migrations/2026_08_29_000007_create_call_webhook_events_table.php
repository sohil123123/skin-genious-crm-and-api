<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The idempotency ledger. One row per distinct provider event.
 *
 * Both providers retry. Exotel re-fires a Passthru whose response was slow;
 * Callyzer redelivers on any non-2xx; and the Callyzer API sync will hand back
 * calls that already arrived by webhook hours earlier. Without a claim staked
 * before any work happens, a retried delivery becomes a second call record, and
 * a duplicated call quietly corrupts every count, every average and every
 * engagement score built on top of it.
 *
 * The mechanism is the unique index on (provider, event_key). A delivery
 * inserts first and works second: if the insert fails, the event has been seen
 * and the request returns success without touching anything. That ordering
 * matters — checking then inserting leaves a window in which two concurrent
 * retries both pass the check.
 *
 * event_key is the identity of the *event*, not the call: a call legitimately
 * generates several events (ringing, answered, completed, recording ready) and
 * each must be allowed through exactly once to update the same call row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_webhook_events', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->string('provider', 30)->comment('Provider that delivered the event');

            // Built by the provider adapter from the most stable identifying
            // fields it has — call id plus event type plus status, falling back
            // to a hash of the payload when the provider names nothing.
            $table->string('event_key', 191)->comment('Deterministic identity of this event; the unique key that makes redelivery a no-op');

            $table->string('event_id', 191)->nullable()->comment('Provider event id, when the provider supplies one of its own');
            $table->string('provider_call_id', 191)->nullable()->comment('Call the event refers to');
            $table->string('event_type', 60)->nullable()->comment('Provider event name');

            // A retry that is byte-identical is noise. A retry whose payload
            // differs is a genuine update that happens to reuse an id, and this
            // is how the two are told apart.
            $table->string('payload_hash', 64)->nullable()->comment('SHA-256 of the payload, to distinguish an identical retry from a changed redelivery');

            $table->foreignId('call_id')
                ->nullable()
                ->constrained('calls')
                ->nullOnDelete()
                ->comment('The call this event created or updated');

            $table->timestamp('received_at')->comment('When the event arrived');
            $table->timestamp('processed_at')->nullable()->comment('When processing finished');
            $table->string('processing_status', 20)->default('pending')->comment('pending, processing, processed, duplicate, ignored, failed');
            $table->unsignedTinyInteger('attempts')->default(0)->comment('Processing attempts made');
            $table->text('error_message')->nullable()->comment('Why processing failed');

            $table->unsignedInteger('duplicate_count')->default(0)->comment('How many times this event has been redelivered; retries are normal, not errors');
            $table->timestamp('last_duplicate_at')->nullable()->comment('When it was last redelivered');

            $table->timestamps();

            // The constraint the whole no-duplicates guarantee rests on.
            $table->unique(['provider', 'event_key'], 'uniq_webhook_event_key');

            $table->index(['provider', 'provider_call_id'], 'idx_webhook_provider_call');
            $table->index(['provider', 'received_at'], 'idx_webhook_provider_received');
            $table->index('processing_status', 'idx_webhook_status');
            $table->index('call_id', 'idx_webhook_call');
            $table->index('received_at', 'idx_webhook_received');

            $table->comment('Idempotency ledger: one row per distinct provider event, so a retry can never create a second call');
        });

        // Deferred to here because call_provider_payloads is created first and
        // the two reference each other.
        Schema::table('call_provider_payloads', function (Blueprint $table) {
            $table->foreign('call_webhook_event_id', 'fk_payload_webhook_event')
                ->references('id')
                ->on('call_webhook_events')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('call_provider_payloads', function (Blueprint $table) {
            $table->dropForeign('fk_payload_webhook_event');
        });

        Schema::dropIfExists('call_webhook_events');
    }
};
