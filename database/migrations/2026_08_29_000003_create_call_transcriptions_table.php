<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The text of a recorded conversation.
 *
 * A separate table because a transcript is large and almost never wanted: the
 * call list renders hundreds of rows and needs none of it. Putting a LONGTEXT
 * on calls would drag it through every query that touches a call.
 *
 * More than one transcription per recording is expected rather than tolerated —
 * re-running a call through a better model, or transcribing a Gujarati call a
 * second time with the language pinned, are both normal. is_current marks the
 * one the CRM should show.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_transcriptions', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('call_id')
                ->constrained('calls')
                ->cascadeOnDelete()
                ->comment('The call this transcript belongs to');

            $table->foreignId('call_recording_id')
                ->nullable()
                ->constrained('call_recordings')
                ->cascadeOnDelete()
                ->comment('The audio this was transcribed from; nullable so a transcript supplied by a provider can exist without one');

            $table->string('provider', 60)->comment('Speech-to-text service used, e.g. openai');
            $table->string('model', 100)->nullable()->comment('Model identifier, so results can be compared across model upgrades');
            $table->string('version', 40)->nullable()->comment('Internal pipeline version, for reprocessing decisions');

            $table->string('language', 60)->nullable()->comment('Detected or requested language name');
            $table->string('language_code', 12)->nullable()->comment('BCP-47 code, e.g. gu, hi, en');

            $table->longText('transcript')->nullable()->comment('Plain-text transcript, the form humans read');
            $table->json('transcript_json')->nullable()->comment('Full provider response including word timings, kept so segments can be rebuilt without re-transcribing');

            $table->decimal('confidence', 5, 4)->nullable()->comment('Overall confidence 0-1, where the provider reports one');
            $table->unsignedInteger('duration_seconds')->nullable()->comment('Audio length the provider actually processed');
            $table->unsignedInteger('word_count')->nullable()->comment('Word count, a cheap proxy for whether anything was really said');

            $table->string('status', 20)->default('pending')->comment('pending, processing, completed, failed, not_available');
            $table->text('error_message')->nullable()->comment('Why the most recent attempt failed');

            // Exactly one row per call is flagged current. Everything in the
            // CRM reads that one; the rest are history.
            $table->boolean('is_current')->default(true)->comment('Whether this is the transcript the CRM should display for the call');

            $table->timestamp('started_at')->nullable()->comment('When transcription began');
            $table->timestamp('completed_at')->nullable()->comment('When transcription finished');
            $table->timestamp('purge_after')->nullable()->comment('Retention date; null means keep indefinitely');

            $table->timestamps();

            $table->index(['call_id', 'is_current'], 'idx_transcription_call_current');
            $table->index('call_recording_id', 'idx_transcription_recording');
            $table->index('status', 'idx_transcription_status');
            $table->index('purge_after', 'idx_transcription_purge_after');

            $table->comment('Speech-to-text output for call recordings');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcriptions');
    }
};
