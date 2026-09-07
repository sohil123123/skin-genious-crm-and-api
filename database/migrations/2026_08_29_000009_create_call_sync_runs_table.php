<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per provider synchronisation run.
 *
 * Application errors already go to the log files. What this adds is the state a
 * log cannot answer without someone reading it: when the last successful sync
 * was, how many calls it imported, where the incremental cursor got to, and
 * whether the run that failed had already written half its work.
 *
 * That last point is why this exists at all. An integration that silently stops
 * syncing looks identical to a quiet week — no errors, no alerts, just calls
 * that never arrive. A visible "last successful sync" is the only thing that
 * makes the failure obvious before someone notices a month of missing calls.
 *
 * cursor_to is deliberately advanced only on a completed run, so a partial run
 * is re-attempted over the same window rather than skipping the calls it did
 * not reach.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sync_runs', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->string('provider', 30)->comment('Provider that was synchronised');
            $table->string('trigger', 20)->default('scheduled')->comment('scheduled, manual, backfill');

            $table->foreignId('triggered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Who pressed Sync Now, for manual runs');

            $table->string('status', 20)->default('running')->comment('running, completed, partial, failed');

            $table->timestamp('window_from')->nullable()->comment('Start of the period requested from the provider');
            $table->timestamp('window_to')->nullable()->comment('End of the period requested');
            $table->timestamp('cursor_to')->nullable()->comment('How far the incremental cursor advanced; only written on a completed run so a partial run repeats its window');

            $table->unsignedInteger('pages_fetched')->default(0)->comment('API pages retrieved');
            $table->unsignedInteger('records_received')->default(0)->comment('Call records the provider returned');
            $table->unsignedInteger('calls_created')->default(0)->comment('New calls written');
            $table->unsignedInteger('calls_updated')->default(0)->comment('Existing calls updated');
            $table->unsignedInteger('calls_skipped')->default(0)->comment('Records deliberately ignored, e.g. unchanged since last sync');
            $table->unsignedInteger('calls_failed')->default(0)->comment('Records that could not be processed');
            $table->unsignedInteger('recordings_queued')->default(0)->comment('Recording downloads dispatched');

            $table->unsignedInteger('rate_limit_waits')->default(0)->comment('Times the run paused for the provider rate limit; a high number means the window is too large for the schedule');
            $table->unsignedSmallInteger('last_http_status')->nullable()->comment('HTTP status of the last provider response');
            $table->text('last_error')->nullable()->comment('Most recent error, kept here so the health screen needs no log access');

            $table->json('parameters')->nullable()->comment('Filters the run was started with, so a result can be reproduced');
            $table->json('metrics')->nullable()->comment('Anything else worth recording about the run');

            $table->timestamp('started_at')->nullable()->comment('When the run began');
            $table->timestamp('finished_at')->nullable()->comment('When it ended');
            $table->unsignedInteger('duration_seconds')->nullable()->comment('How long it took');

            $table->timestamps();

            $table->index(['provider', 'status', 'started_at'], 'idx_sync_provider_status_started');
            $table->index(['provider', 'started_at'], 'idx_sync_provider_started');
            $table->index('status', 'idx_sync_status');

            $table->comment('Provider synchronisation history; the evidence behind the integration health screen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sync_runs');
    }
};
