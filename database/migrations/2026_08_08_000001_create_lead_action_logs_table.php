<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Next Best Action queue for imported Meta leads.
 *
 * Deliberately a sibling of ai_action_logs rather than an extension of it: that
 * table's user_id is NOT NULL with a foreign key to users, and a lead is not a
 * patient. Keeping them apart means the working patient engine is untouched,
 * and the two queues stay independently scoped, filtered and regenerated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_action_logs', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Clinic that imported the lead; actions are always scoped to it');

            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete()
                ->comment('The imported lead this action targets');

            $table->foreignId('matched_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Existing patient this lead appears to be; context for the script, never a merge');

            $table->string('action_category', 30)
                ->comment('Broad action group: conversion or retention for lead work');

            $table->string('action_trigger', 60)
                ->comment('Specific rule: lead_hot_intent, lead_never_contacted, lead_existing_patient, lead_stalled');

            $table->unsignedTinyInteger('priority_score')
                ->default(0)
                ->comment('Computed priority 0-100; a same-day visit intent scores near the ceiling');

            $table->string('recommended_channel', 20)
                ->default('call')
                ->comment('Recommended contact channel: call, whatsapp or sms');

            $table->string('recommended_time', 50)
                ->nullable()
                ->comment('Best time window to make contact');

            $table->text('reason')
                ->comment('Human-readable explanation of why this lead was selected today');

            $table->text('suggested_message')
                ->nullable()
                ->comment('Draft WhatsApp message or call opening, built from the lead form answers');

            $table->string('goal')
                ->nullable()
                ->comment('Action objective, e.g. Book a consultation this week');

            $table->text('avoid_notes')
                ->nullable()
                ->comment('What staff should not do on this contact');

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Staff member responsible for executing this action');

            $table->dateTime('expires_at')
                ->nullable()
                ->comment('When this action goes stale; hot leads expire within hours');

            $table->string('staff_outcome', 30)
                ->nullable()
                ->comment('Staff-recorded outcome, mirroring the patient queue options');

            $table->text('outcome_notes')
                ->nullable()
                ->comment('Free-text staff notes about what happened');

            $table->dateTime('outcome_at')
                ->nullable()
                ->comment('Timestamp the outcome was recorded');

            $table->foreignId('outcome_appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete()
                ->comment('Appointment booked as a direct result of this action');

            $table->date('generated_date')
                ->comment('The date this action was generated for');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this action is still actionable by staff');

            $table->timestamps();

            // One action per lead per day. This is what makes regeneration
            // idempotent: pressing Regenerate repeatedly cannot multiply the
            // queue, and an outcome already recorded survives the next run.
            $table->unique(['lead_id', 'generated_date'], 'uniq_lead_action_per_day');

            $table->index(['clinic_id', 'generated_date', 'is_active'], 'idx_lead_action_clinic_date_active');
            $table->index(['action_category', 'priority_score'], 'idx_lead_action_category_priority');
            $table->index('assigned_to', 'idx_lead_action_assigned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_action_logs');
    }
};
