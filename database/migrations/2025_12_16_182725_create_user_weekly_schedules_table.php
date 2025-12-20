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
        Schema::create('user_weekly_schedules', function (Blueprint $table) {
            $table->id();
             /**
             * Therapist (user with role = therapist)
             */
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete()->comment('Clinic where therapist works');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Therapist user ID');

            /**
             * ISO-8601 day of week
             * 1 = Monday, 7 = Sunday
             */
            $table->unsignedTinyInteger('day_of_week')->comment('Day of week (1=Mon … 7=Sun)');

            /**
             * Shift timing
             * Must align to 15-minute boundaries
             */
            $table->time('start_time')->comment('Shift start time (15-min aligned)');
            $table->time('end_time')->comment('Shift end time (15-min aligned)');

            /**
             * Overlap handling
             */
            $table->boolean('allows_overlap')->default(true)->comment('Whether overlapping shifts are allowed');
            $table->boolean('has_overlap')->default(false)->comment('System flag: this shift overlaps another shift');

            /**
             * Operational fields
             */
            $table->boolean('is_active')->default(true)->comment('Whether this weekly schedule is active');

            $table->text('notes')->nullable()->comment('Internal notes');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who created the schedule');

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who last updated the schedule');

            $table->timestamps();

            /**
             * Performance & integrity
             */
            $table->index(
                ['user_id', 'clinic_id', 'day_of_week'],
                'therapist_day_idx'
            );

            $table->index(
                ['clinic_id', 'day_of_week', 'start_time', 'end_time'],
                'clinic_day_time_idx'
            );

            $table->unique(
                ['clinic_id', 'user_id', 'day_of_week', 'start_time', 'end_time'],
                'unique_weekly_shift'
            );

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_weekly_schedules');
    }
};
