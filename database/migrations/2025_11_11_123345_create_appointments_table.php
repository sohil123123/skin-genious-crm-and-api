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

            $table->enum('type', ['consult', 'treatment', 'express'])
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
                ->comment('Linked treatment session, if any');

            $table->dateTime('start_datetime')
                ->index()
                ->comment('Appointment start datetime');

            $table->dateTime('end_datetime')
                ->index()
                ->comment('Appointment end datetime');

             $table->unsignedSmallInteger('duration_minutes')
                ->nullable()
                ->comment('Duration in minutes (stored explicitly)');

            $table->enum('status', [
                'pending',
                'confirmed',
                'in_progress',
                'completed',
                'cancelled',
                'no_show',
            ])->default('pending')
                ->comment('Current status of the appointment');

            $table->json('products_used')
                ->nullable()
                ->comment('List of products used during treatment in JSON format');

            $table->json('resources_used')
                ->nullable()
                ->comment('List of clinic resources used (e.g. room, equipment) in JSON format');

            $table->enum('source', [
                'front_desk',
                'central_team',
                'online',
                'system',
            ])
                ->default('front_desk')
                ->comment('Booking source');

            $table->text('notes')
                ->nullable()
                ->comment('Therapist or system notes about the appointment');

            $table->boolean('is_billable')
                ->default(true)
                ->comment('Whether this appointment can generate invoice');

            $table->boolean('is_billed')
                ->default(false)
                ->index()
                ->comment('Invoice generated or not');

            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate()
                ->comment('User who created the appointment');

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who last updated appointment');


            $table->softDeletes();
            $table->timestamps();

            $table->index(
                ['therapist_id', 'start_datetime', 'end_datetime'],
                'therapist_time_idx'
            );

            $table->index(
                ['clinic_id', 'start_datetime', 'end_datetime'],
                'clinic_time_idx'
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
