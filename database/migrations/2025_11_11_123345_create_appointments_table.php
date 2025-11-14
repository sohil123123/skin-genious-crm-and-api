<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id()->comment('Primary key of the appointment');

            $table->enum('type', ['consult', 'treatment'])
                ->default('consult')
                ->comment('Type of appointment: consult or treatment');

            // Foreign key columns
            $table->foreignId('clinic_id')
                ->constrained('clinics', indexName: 'appointments_clinic_id_index')
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
                ->comment('Clinic where the appointment takes place');

            $table->foreignId('user_id')
                ->constrained('users', indexName: 'appointments_user_id_index')
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
                ->comment('Client who booked the appointment');

            $table->foreignId('therapist_id')
                ->constrained('users', indexName: 'appointments_therapist_id_index')
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
                ->comment('Therapist assigned to the appointment');

            $table->foreignId('assessment_id')
                ->nullable()
                ->constrained('assessments', indexName: 'appointments_assessment_id_index')
                ->nullOnDelete()
                ->cascadeOnUpdate()
                ->comment('Linked assessment record, if any');

            $table->foreignId('treatment_session_id')
                ->nullable()
                ->constrained('treatment_sessions', indexName: 'appointments_treatment_session_id_index')
                ->nullOnDelete()
                ->cascadeOnUpdate()
                ->comment('Linked treatment plan, if any');

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
                ->comment('User who created the appointment');

            $table->dateTime('appointment_datetime')
                ->index()
                ->comment('Date and time of the appointment');

            $table->enum('status', [
                'scheduled',
                'confirmed',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('scheduled')
                ->comment('Current status of the appointment');

            $table->json('products_used')
                ->nullable()
                ->comment('List of products used during treatment in JSON format');

            $table->json('resources_used')
                ->nullable()
                ->comment('List of clinic resources used (e.g. room, equipment) in JSON format');

            $table->text('notes')
                ->nullable()
                ->comment('Therapist or system notes about the appointment');

            $table->timestamp('billed_at')
                ->nullable()
                ->comment('Timestamp when the appointment was billed');

            $table->softDeletes();
            $table->timestamps();

            // Prevent double bookings
            $table->unique(
                ['therapist_id', 'clinic_id', 'appointment_datetime'],
                'unique_therapist_slot'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
