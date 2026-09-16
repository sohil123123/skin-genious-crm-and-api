<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a call told the CRM, in a shape the action engines can query.
 *
 * call_analyses already holds the model's reading of a conversation, but it
 * holds it one call at a time: the objection is a sentence in a text column,
 * and the interesting question is never about one call. It is "every lead with
 * an open price objection in the last month", asked across thousands of rows by
 * an engine that runs every morning. A text column cannot answer that, and JSON
 * cannot be indexed for it.
 *
 * So each analysis fans out into rows here, one per thing the model recognised,
 * drawn from a closed vocabulary. The subject — patient or lead — is copied
 * down from the call rather than joined through it, because both engines filter
 * by subject first and a join to calls on every scoring pass is the difference
 * between a query and a table scan.
 *
 * Signals are never updated, only inserted and superseded. An objection raised
 * in March stays true of March; the engines decide what it is worth today by
 * looking at occurred_at, which is why that column is the call's own start time
 * and not the moment the analysis happened to run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_insight_signals', function (Blueprint $table): void {
            $table->id()->comment('Primary key');

            $table->foreignId('call_analysis_id')
                ->constrained('call_analyses')
                ->cascadeOnDelete()
                ->comment('The analysis that produced this signal; goes when the analysis does');

            // Denormalised from the analysis. Worth the duplication: it lets a
            // signal be traced to its conversation without a second join, and
            // the pair is immutable once written.
            $table->foreignId('call_id')
                ->constrained('calls')
                ->cascadeOnDelete()
                ->comment('The call the signal was heard on');

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete()
                ->comment('Which branch owns the conversation; both engines are clinic-scoped');

            /*
             * The subject, copied down from the call.
             *
             * Exactly one is normally set — a call is with a patient or with a
             * lead — but neither is required, because an unmatched call still
             * produces signals worth keeping. They become attributable the
             * moment matching succeeds, and rematching backfills them.
             */
            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Patient this signal is about, when the call was matched to one');

            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete()
                ->comment('Lead this signal is about, when the call was matched to one');

            $table->string('signal_type', 30)
                ->comment('Family: engagement, intent, objection, sentiment, follow_up, risk, opportunity');

            $table->string('signal_key', 60)
                ->comment('The signal itself, from the CallSignalKey vocabulary');

            $table->string('value', 255)
                ->nullable()
                ->comment('Optional detail, e.g. the treatment named by a treatment_interest signal');

            $table->decimal('confidence', 5, 4)
                ->nullable()
                ->comment('How sure the model was, 0-1; the engines refuse to act below a threshold');

            $table->dateTime('occurred_at')
                ->comment('When the conversation happened, not when it was analysed — this is what decays');

            $table->json('metadata')
                ->nullable()
                ->comment('Anything the signal carried that has no column, e.g. the quoted sentence');

            $table->timestamps();

            // The two questions the engines actually ask, one per subject kind.
            $table->index(['customer_user_id', 'occurred_at'], 'idx_signal_patient_time');
            $table->index(['lead_id', 'occurred_at'], 'idx_signal_lead_time');

            // "Everyone with this signal recently", for a rule that starts from
            // the signal rather than from a subject.
            $table->index(['signal_key', 'occurred_at'], 'idx_signal_key_time');
            $table->index(['clinic_id', 'signal_type', 'occurred_at'], 'idx_signal_clinic_type_time');
            $table->index('call_id', 'idx_signal_call');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_insight_signals');
    }
};
