<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every payload either provider ever sent, exactly as it arrived.
 *
 * This is the table that makes the rest of the design safe to get wrong. A
 * normalised column is an interpretation, and interpretations are discovered to
 * be wrong months later — a duration read from the wrong field, a timestamp
 * parsed in the wrong timezone, a status mapped to the wrong enum. If the
 * original bytes are gone, so is any chance of fixing history; if they are
 * here, a corrected mapper can be replayed over them.
 *
 * It also settles arguments. When a clinic says a call is missing and the
 * provider says it was delivered, this is the record of what actually landed.
 *
 * Nothing writes to a row here after it is created. It is append-only by
 * intent, and the payload column is never edited, migrated or cleaned up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_provider_payloads', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            // Nullable because the payload is stored before anything is
            // understood about it. A payload the CRM could not turn into a call
            // is precisely the one worth keeping.
            $table->foreignId('call_id')
                ->nullable()
                ->constrained('calls')
                ->nullOnDelete()
                ->comment('The call this payload produced, once one was created; null while unprocessed or unusable');

            $table->foreignId('call_webhook_event_id')
                ->nullable()
                ->comment('The webhook event that carried this payload; FK added by the webhook events migration to avoid an ordering cycle');

            $table->string('provider', 30)->comment('Which provider sent it');
            $table->string('source', 20)->default('webhook')->comment('webhook, api_sync, manual, import');
            $table->string('event_type', 60)->nullable()->comment('Provider event name, where one is given');
            $table->string('provider_call_id', 191)->nullable()->comment('Provider call id read out of the payload, so payloads are findable before they are processed');

            $table->string('request_id', 100)->nullable()->comment('Correlation id for the HTTP request that delivered this');

            $table->json('payload')->comment('The original body or query string, unmodified. Never edited');
            $table->json('headers')->nullable()->comment('Request headers with credentials stripped, for debugging delivery problems');

            $table->string('ip_address', 45)->nullable()->comment('Where the request came from');
            $table->string('http_method', 10)->nullable()->comment('GET for Exotel Passthru, POST for JSON callbacks');

            $table->timestamp('received_at')->comment('When the CRM received it');
            $table->timestamp('processed_at')->nullable()->comment('When processing finished');
            $table->string('processing_status', 20)->default('pending')->comment('pending, processing, processed, duplicate, ignored, failed');
            $table->text('processing_error')->nullable()->comment('Why processing failed');

            $table->timestamps();

            $table->index(['call_id', 'received_at'], 'idx_payload_call_received');
            $table->index(['provider', 'provider_call_id'], 'idx_payload_provider_call');
            $table->index(['provider', 'received_at'], 'idx_payload_provider_received');
            $table->index('processing_status', 'idx_payload_status');
            $table->index('call_webhook_event_id', 'idx_payload_webhook_event');
            $table->index('received_at', 'idx_payload_received');

            $table->comment('Append-only archive of raw provider payloads; the source of truth behind every normalised field');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_provider_payloads');
    }
};
