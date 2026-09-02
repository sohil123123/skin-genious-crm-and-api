<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The one table every CRM feature reads calls from.
 *
 * Provider-independent on purpose. Nothing downstream — the customer timeline,
 * engagement scoring, Next Best Action — should ever need to know whether a
 * conversation reached the clinic through Exotel or Callyzer, so both are
 * normalised into these columns and their private vocabulary is kept beside it
 * rather than in it.
 *
 * Four things are stored side by side and never allowed to overwrite each
 * other, because they answer to different owners:
 *
 *   provider_*   what the telephony system said. Never edited by the CRM.
 *   crm_*        what a human here decided. Never overwritten by a re-sync.
 *   ai_*         what analysis inferred. Regenerated freely, trusted least.
 *   derived      what this application calculated and can recalculate.
 *
 * Anything the providers send that has no column lands in provider_data, and
 * the untouched original is in call_provider_payloads. A field this schema
 * failed to anticipate is therefore recoverable without a migration and
 * without asking the provider to resend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            // A stable public identifier. Recording storage paths are built
            // from it, so it must not change and must not be guessable from
            // the row id.
            $table->uuid('uuid')->unique()->comment('Stable public identifier; also the recording storage folder name');

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete()
                ->comment('Clinic the call belongs to, resolved from the agent or the customer; nullable because an unrecognised caller has no clinic yet');

            // ─── Provider identity ──────────────────────────────────────────
            $table->string('provider', 30)->comment('Telephony system this call came from: exotel, callyzer, manual');

            $table->string('provider_call_id', 191)
                ->nullable()
                ->comment('The provider primary key for this call: Exotel CallSid, Callyzer id. Unique per provider, NOT globally');

            $table->string('provider_reference_id', 191)
                ->nullable()
                ->comment('Secondary provider identifier where one exists, e.g. an Exotel flow/call reference');

            $table->string('provider_parent_call_id', 191)
                ->nullable()
                ->comment('Parent call for transfers and second legs, so a legged call can be reassembled');

            $table->string('provider_event_id', 191)
                ->nullable()
                ->comment('Identifier of the most recent provider event applied to this row');

            $table->string('source', 20)->default('webhook')->comment('How this record reached the CRM: webhook, api_sync, manual, import');

            // ─── Direction and outcome ──────────────────────────────────────
            // Direction and status are deliberately independent columns. Both
            // providers conflate them ("missed" is a call type in Callyzer),
            // but "a call we received" and "nobody answered it" are different
            // questions and every report here needs to ask them separately.
            $table->string('direction', 20)->default('unknown')->comment('incoming, outgoing, internal, unknown');
            $table->string('call_status', 30)->default('unknown')->comment('Unified lifecycle status: ringing, completed, missed, no_answer, ...');

            $table->boolean('is_connected')->default(false)->comment('Whether the two parties actually spoke; the connection status every report is built on');

            $table->string('provider_direction', 60)->nullable()->comment('Direction word exactly as the provider sent it');
            $table->string('provider_call_status', 60)->nullable()->comment('Status word exactly as the provider sent it, including values this schema does not recognise');

            $table->string('disposition', 60)->nullable()->comment('Provider hangup/disposition reason, e.g. Exotel leg Cause');
            $table->string('hangup_cause_code', 30)->nullable()->comment('Provider numeric hangup cause where supplied');

            // ─── Customer side ──────────────────────────────────────────────
            $table->foreignId('customer_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('The patient on this call, when one was identified');

            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete()
                ->comment('The lead on this call, when one was identified and no patient was');

            $table->string('client_name')->nullable()->comment('Customer name as the provider knew it; never overwrites the CRM record');
            $table->string('client_country_code', 8)->nullable()->comment('Customer country code as supplied by the provider');
            $table->string('client_phone', 32)->nullable()->comment('Customer number exactly as the provider sent it; never destroyed');
            $table->string('client_phone_normalized', 32)->nullable()->comment('Customer number in +<country><national> form');
            $table->string('client_phone_key', 20)->nullable()->comment('Last N digits of the customer number; the only column matching ever compares on');

            // ─── Agent side ─────────────────────────────────────────────────
            $table->foreignId('agent_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('CRM staff member who handled the call, resolved through call_provider_agents or by phone');

            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Staff member responsible for following this call up; starts as the agent and can be reassigned');

            $table->unsignedBigInteger('call_provider_agent_id')
                ->nullable()
                ->comment('Mapping row that resolved the agent; FK added by the call_provider_agents migration to avoid an ordering cycle');

            $table->string('employee_name')->nullable()->comment('Agent name as the provider knew it');
            $table->string('employee_code', 60)->nullable()->comment('Provider employee code, e.g. Callyzer emp_code');
            $table->string('employee_country_code', 8)->nullable()->comment('Agent country code as supplied by the provider');
            $table->string('employee_phone', 32)->nullable()->comment('Agent number exactly as the provider sent it');
            $table->string('employee_phone_normalized', 32)->nullable()->comment('Agent number in +<country><national> form');
            $table->string('employee_phone_key', 20)->nullable()->comment('Last N digits of the agent number, used to resolve the CRM user');

            // ─── The clinic's own number ────────────────────────────────────
            $table->string('virtual_number', 32)->nullable()->comment('The clinic-side number involved: the Exophone dialled on an incoming call');
            $table->string('virtual_number_normalized', 32)->nullable()->comment('Virtual number in +<country><national> form; maps to a clinic');

            // ─── Matching ───────────────────────────────────────────────────
            $table->string('matching_status', 30)->default('unmatched')->comment('matched, unmatched, ambiguous, manually_matched');
            $table->string('matching_method', 30)->default('unknown')->comment('How the customer was identified: phone, alternate_phone, provider_lead_id, manual, unknown');
            $table->timestamp('matched_at')->nullable()->comment('When the customer was attached');

            $table->foreignId('matched_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Staff member who attached the customer by hand, for manual matches');

            // An ambiguous call is useless without knowing who it might have
            // been; this is what lets the resolution screen offer a choice
            // instead of a search box.
            $table->json('match_candidates')->nullable()->comment('Records the number matched when there was more than one, so a human can choose');

            // ─── Timing ─────────────────────────────────────────────────────
            $table->dateTime('started_at')->nullable()->comment('When the call began; the sort key for every timeline in the CRM');
            $table->dateTime('answered_at')->nullable()->comment('When it was picked up, where the provider says');
            $table->dateTime('ended_at')->nullable()->comment('When it finished');

            $table->unsignedInteger('duration_seconds')->nullable()->comment('Total call length as the provider reports it');
            $table->unsignedInteger('ring_duration_seconds')->nullable()->comment('Time spent ringing before answer or abandonment');
            $table->unsignedInteger('talk_duration_seconds')->nullable()->comment('Time the two parties were actually speaking');
            $table->unsignedInteger('hold_duration_seconds')->nullable()->comment('Time on hold, where the provider reports it');
            $table->unsignedInteger('wait_duration_seconds')->nullable()->comment('Time in queue or IVR before reaching an agent');

            $table->string('timezone', 64)->nullable()->comment('Timezone the provider expressed its timestamps in, so they can be re-read correctly later');

            // The raw strings, not re-parsed values. When a timestamp looks
            // wrong the question is always what the provider actually sent.
            $table->string('provider_started_at_raw', 60)->nullable()->comment('Start time exactly as the provider wrote it');
            $table->string('provider_answered_at_raw', 60)->nullable()->comment('Answer time exactly as the provider wrote it');
            $table->string('provider_ended_at_raw', 60)->nullable()->comment('End time exactly as the provider wrote it');

            // ─── Notes and outcome, kept in separate lanes ──────────────────
            $table->text('provider_note')->nullable()->comment('Note typed into the provider app by the agent; read-only in the CRM');
            $table->text('crm_note')->nullable()->comment('Note written here by CRM staff; never touched by a re-sync');
            $table->text('ai_summary')->nullable()->comment('Latest AI summary, denormalised from call_analyses for list rendering');

            $table->string('provider_crm_status', 60)->nullable()->comment('The provider CRM status, e.g. Callyzer crm_status "Interested"; never overwritten by CRM edits');
            $table->string('crm_outcome', 60)->nullable()->comment('Outcome recorded here by staff; overrides nothing, replaces nothing');

            $table->string('provider_lead_id', 191)->nullable()->comment('Lead identifier the provider carried, used as a matching signal');
            $table->dateTime('provider_reminder_at')->nullable()->comment('Reminder the agent set in the provider app, from reminder_date + reminder_time');

            // ─── Follow-up, surfaced for the existing action queues ──────────
            $table->boolean('follow_up_required')->default(false)->comment('Whether this call still needs a callback');
            $table->dateTime('follow_up_at')->nullable()->comment('When the follow-up is due');
            $table->string('follow_up_reason')->nullable()->comment('Why a follow-up is needed');
            $table->timestamp('follow_up_completed_at')->nullable()->comment('When the follow-up was done');

            // ─── Pipeline flags, denormalised for filtering ─────────────────
            // These are derived and rebuildable. They exist so the call list
            // can filter on "has a recording" without a subquery per row.
            $table->boolean('has_recording')->default(false)->comment('Whether any recording is attached');
            $table->unsignedTinyInteger('recording_count')->default(0)->comment('How many recordings are attached; a legged call can have more than one');
            $table->string('recording_status', 30)->nullable()->comment('Rolled-up storage status across this call recordings');
            $table->string('transcription_status', 30)->default('pending')->comment('Rolled-up transcription state for the call');
            $table->string('analysis_status', 30)->default('pending')->comment('Rolled-up AI analysis state for the call');

            // ─── Provider extras ────────────────────────────────────────────
            $table->string('call_method', 40)->nullable()->comment('Callyzer call_method, preserved verbatim');
            $table->string('call_mode', 40)->nullable()->comment('Callyzer call_mode, preserved verbatim');

            // The escape hatch that keeps this schema from needing a migration
            // every time a provider adds a field.
            $table->json('provider_data')->nullable()->comment('Every provider field without a column of its own, keyed as the provider sent it');

            // ─── Sync and processing metadata ───────────────────────────────
            $table->timestamp('first_seen_at')->nullable()->comment('When the CRM first heard about this call');
            $table->timestamp('last_event_at')->nullable()->comment('When the most recent provider event for it was applied');
            $table->unsignedInteger('event_count')->default(0)->comment('How many provider events have touched this row');
            $table->string('last_source', 20)->nullable()->comment('Which path wrote the most recent update: webhook or api_sync');
            $table->string('last_processing_status', 30)->nullable()->comment('Outcome of the most recent processing attempt');
            $table->text('last_error')->nullable()->comment('Most recent processing error, kept on the row so failures are visible without reading logs');

            $table->dateTime('provider_synced_at')->nullable()->comment('Callyzer synced_at, preserved');
            $table->dateTime('provider_modified_at')->nullable()->comment('Callyzer modified_at; drives incremental sync');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who created the record, for manually entered calls');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who last edited the record');

            $table->timestamps();
            $table->softDeletes();

            // ─── Constraints and indexes ────────────────────────────────────

            // The rule the whole system rests on: one real call is one row.
            // Scoped by provider because provider_call_id is only unique within
            // its own provider — an Exotel CallSid and a Callyzer id could
            // collide in principle and must not be allowed to merge two calls.
            // MySQL permits repeated NULLs here, which is what lets a manually
            // entered call exist with no provider id.
            $table->unique(['provider', 'provider_call_id'], 'uniq_call_provider_id');

            $table->index(['customer_user_id', 'started_at'], 'idx_call_customer_started');
            $table->index(['lead_id', 'started_at'], 'idx_call_lead_started');
            $table->index(['agent_user_id', 'started_at'], 'idx_call_agent_started');
            $table->index(['assigned_user_id', 'follow_up_required'], 'idx_call_assigned_followup');
            $table->index(['clinic_id', 'started_at'], 'idx_call_clinic_started');
            $table->index(['client_phone_key', 'started_at'], 'idx_call_client_key_started');
            $table->index(['direction', 'started_at'], 'idx_call_direction_started');
            $table->index(['call_status', 'started_at'], 'idx_call_status_started');
            $table->index(['provider', 'started_at'], 'idx_call_provider_started');
            $table->index(['matching_status', 'started_at'], 'idx_call_matching_started');
            $table->index(['is_connected', 'started_at'], 'idx_call_connected_started');
            $table->index('started_at', 'idx_call_started');
            $table->index('ended_at', 'idx_call_ended');
            $table->index('created_at', 'idx_call_created');
            $table->index('provider_modified_at', 'idx_call_provider_modified');
            $table->index('employee_phone_key', 'idx_call_employee_key');
            $table->index('virtual_number_normalized', 'idx_call_virtual_number');
            $table->index('has_recording', 'idx_call_has_recording');
            $table->index('transcription_status', 'idx_call_transcription_status');
            $table->index('analysis_status', 'idx_call_analysis_status');

            $table->comment('Unified, provider-independent call records for the whole CRM');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
