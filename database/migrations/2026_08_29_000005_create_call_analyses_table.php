<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What AI concluded about a call.
 *
 * Kept entirely away from the calls table. Everything here is an inference that
 * will be re-run as prompts and models improve, and mixing regenerable opinion
 * into the record of what the phone system actually did makes it impossible to
 * tell later which is which.
 *
 * Versioned rather than overwritten: analysis_version plus is_current means a
 * v2 pass can be compared against v1 before it is trusted, and a bad prompt can
 * be rolled back by moving a flag instead of re-analysing a year of calls.
 *
 * The columns are the fields the clinic will actually filter and report on;
 * everything else the model returns stays in structured_result, so a richer
 * prompt does not need a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_analyses', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('call_id')
                ->constrained('calls')
                ->cascadeOnDelete()
                ->comment('The call that was analysed');

            $table->foreignId('call_transcription_id')
                ->nullable()
                ->constrained('call_transcriptions')
                ->nullOnDelete()
                ->comment('The transcript this analysis read; null if it ran on metadata alone');

            $table->string('analysis_version', 40)->default('v1')->comment('Prompt and pipeline version, so results are comparable across changes');
            $table->string('provider', 60)->nullable()->comment('AI service used');
            $table->string('model', 100)->nullable()->comment('Model identifier');

            $table->string('status', 20)->default('pending')->comment('pending, processing, completed, failed, not_available');
            $table->boolean('is_current')->default(true)->comment('Whether this is the analysis the CRM should display');

            // ─── What the call was about ────────────────────────────────────
            $table->text('summary')->nullable()->comment('Short readable account of the conversation');
            $table->string('customer_intent', 120)->nullable()->comment('What the customer wanted');
            $table->string('call_reason', 120)->nullable()->comment('Why the call happened');
            $table->string('outcome', 120)->nullable()->comment('How it ended');

            // ─── How it went ────────────────────────────────────────────────
            $table->string('sentiment', 20)->nullable()->comment('positive, neutral, negative, mixed, unknown');
            $table->decimal('sentiment_score', 5, 4)->nullable()->comment('Sentiment on a -1 to 1 scale, for trending');
            $table->string('urgency', 20)->nullable()->comment('How soon this needs acting on');

            // ─── Commercial signals, the ones NBA will consume ──────────────
            $table->string('lead_temperature', 20)->nullable()->comment('hot, warm, cold');
            $table->unsignedTinyInteger('purchase_intent')->nullable()->comment('Likelihood of buying, 0-100');
            $table->text('objection')->nullable()->comment('What is stopping them');
            $table->string('product_interest')->nullable()->comment('Products discussed');
            $table->string('treatment_interest')->nullable()->comment('Treatments discussed');

            // Nullable, and deliberately without a default. These are a model's
            // reading of a transcript, and "the transcript does not say" is a
            // real answer that must not collapse into "no" - a default of false
            // makes every unanswered question look like a confident denial.
            $table->boolean('price_discussed')->nullable()->default(null)->comment('Whether price came up; null if the transcript does not say');
            $table->boolean('appointment_discussed')->nullable()->default(null)->comment('Whether an appointment was discussed; null if the transcript does not say');
            $table->boolean('appointment_booked')->nullable()->default(null)->comment('Whether a specific date/time was agreed on the call; null if the transcript does not say');

            $table->boolean('follow_up_required')->nullable()->default(null)->comment('Whether the call needs a callback; null if the transcript does not say');
            $table->text('follow_up_reason')->nullable()->comment('Why');

            $table->text('next_best_action')->nullable()->comment('Suggested next step; consumed by the action engine, never executed here');
            $table->unsignedTinyInteger('next_best_action_priority')->nullable()->comment('Suggested priority 0-100, on the same scale as ai_action_logs');

            $table->decimal('ai_confidence', 5, 4)->nullable()->comment('How sure the model was about the whole analysis');

            $table->json('structured_result')->nullable()->comment('The full model response, so a richer prompt needs no migration');

            $table->text('error_message')->nullable()->comment('Why the most recent attempt failed');
            $table->unsignedInteger('input_tokens')->nullable()->comment('Prompt tokens consumed, for cost tracking');
            $table->unsignedInteger('output_tokens')->nullable()->comment('Completion tokens consumed, for cost tracking');

            $table->timestamp('started_at')->nullable()->comment('When analysis began');
            $table->timestamp('completed_at')->nullable()->comment('When analysis finished');

            $table->timestamps();

            $table->index(['call_id', 'is_current'], 'idx_analysis_call_current');
            $table->index(['sentiment', 'created_at'], 'idx_analysis_sentiment_created');
            $table->index(['lead_temperature', 'created_at'], 'idx_analysis_temperature_created');
            $table->index('follow_up_required', 'idx_analysis_followup');
            $table->index('status', 'idx_analysis_status');
            $table->index('analysis_version', 'idx_analysis_version');

            $table->comment('AI-derived intelligence about calls; regenerable, versioned, never mixed into the call record');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_analyses');
    }
};
