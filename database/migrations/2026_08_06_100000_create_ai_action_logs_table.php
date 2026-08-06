<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_logs', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Clinic this action belongs to');

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->comment('The client/patient targeted by this action');

            $table->string('action_category', 30)
                ->comment('Broad action group: rescue, conversion, retention, capacity');

            $table->string('action_trigger', 60)
                ->comment('Specific trigger rule e.g. cancelled_not_rebooked, scan_no_treatment, package_overdue');

            $table->unsignedTinyInteger('priority_score')
                ->default(0)
                ->comment('Computed priority score 0-100, higher = more urgent');

            $table->string('recommended_channel', 20)
                ->default('whatsapp')
                ->comment('Recommended contact channel: call, whatsapp, sms');

            $table->string('recommended_time', 50)
                ->nullable()
                ->comment('Best time window to contact e.g. 12:30 - 2:00 PM');

            $table->text('reason')
                ->comment('Human-readable explanation of why this patient was selected today');

            $table->text('suggested_message')
                ->nullable()
                ->comment('Draft WhatsApp message or call opening script');

            $table->string('goal')
                ->nullable()
                ->comment('Action objective e.g. Secure a facial appointment this week');

            $table->json('slots_to_offer')
                ->nullable()
                ->comment('JSON array of available appointment slots to offer the patient');

            $table->text('avoid_notes')
                ->nullable()
                ->comment('What staff should NOT do e.g. Do not offer discount');

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Staff member assigned to execute this action');

            $table->foreignId('related_appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete()
                ->comment('Source appointment that triggered this action (cancelled/no-show)');

            $table->foreignId('related_package_id')
                ->nullable()
                ->constrained('user_packages')
                ->nullOnDelete()
                ->comment('Source package that triggered this action (overdue/exhausting)');

            $table->foreignId('related_assessment_id')
                ->nullable()
                ->constrained('assessments')
                ->nullOnDelete()
                ->comment('Source assessment/scan that triggered this action');

            $table->dateTime('expires_at')
                ->nullable()
                ->comment('When this action becomes stale and should no longer be shown');

            $table->string('staff_outcome', 30)
                ->nullable()
                ->comment('Staff-recorded outcome: called, no_answer, whatsapp_sent, booked, not_interested, call_later, wrong_recommendation, do_not_contact, other');

            $table->text('outcome_notes')
                ->nullable()
                ->comment('Free-text staff notes about the outcome');

            $table->dateTime('outcome_at')
                ->nullable()
                ->comment('Timestamp when staff recorded the outcome');

            $table->foreignId('outcome_appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete()
                ->comment('New appointment booked as a direct result of this action');

            $table->date('generated_date')
                ->comment('The date this action was generated for (morning report date)');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Whether this action is still actionable by staff');

            $table->timestamps();

            // Indexes for dashboard queries
            $table->index(['clinic_id', 'generated_date', 'is_active'], 'idx_ai_clinic_date_active');
            $table->index(['user_id', 'generated_date'], 'idx_ai_user_date');
            $table->index(['action_category', 'priority_score'], 'idx_ai_category_priority');
            $table->index('assigned_to', 'idx_ai_assigned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_logs');
    }
};
