<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A transcript broken into who-said-what-when.
 *
 * The flat transcript is what a human reads; this is what analysis needs. "Did
 * the agent quote a price before the customer asked?" and "how much of this
 * call did the agent talk through?" are unanswerable from a wall of text and
 * trivial from ordered, attributed segments.
 *
 * The provider's own speaker label is kept next to the interpreted one because
 * diarisation returns "SPEAKER_00" — stable inside one recording, meaningless
 * across two. Deciding that SPEAKER_00 is the agent is an inference, and
 * inferences are stored separately from what was actually returned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_transcript_segments', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('call_transcription_id')
                ->constrained('call_transcriptions')
                ->cascadeOnDelete()
                ->comment('The transcription this segment belongs to');

            // Denormalised from the transcription so the whole conversation for
            // a call can be read without a join, which is what the call detail
            // page and every analysis query actually do.
            $table->foreignId('call_id')
                ->constrained('calls')
                ->cascadeOnDelete()
                ->comment('The call, denormalised so segments can be read without joining transcriptions');

            $table->unsignedInteger('sequence')->default(0)->comment('Position in the conversation; segments are ordered on this, not on time, so equal timestamps stay stable');

            $table->string('speaker', 60)->nullable()->comment('Speaker label exactly as the provider returned it, e.g. SPEAKER_00');
            $table->string('speaker_type', 20)->default('unknown')->comment('Interpreted side: agent, customer, system, unknown');

            $table->decimal('start_seconds', 10, 3)->nullable()->comment('Segment start offset from the beginning of the audio');
            $table->decimal('end_seconds', 10, 3)->nullable()->comment('Segment end offset');

            $table->text('text')->nullable()->comment('What was said in this segment');
            $table->decimal('confidence', 5, 4)->nullable()->comment('Segment confidence 0-1, where reported');

            $table->json('metadata')->nullable()->comment('Word-level timings and any other provider segment fields');

            $table->timestamps();

            $table->index(['call_transcription_id', 'sequence'], 'idx_segment_transcription_sequence');
            $table->index(['call_id', 'sequence'], 'idx_segment_call_sequence');
            $table->index('speaker_type', 'idx_segment_speaker_type');

            $table->comment('Speaker- and time-attributed pieces of a call transcript');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_transcript_segments');
    }
};
