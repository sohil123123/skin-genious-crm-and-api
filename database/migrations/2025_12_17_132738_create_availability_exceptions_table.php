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
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            /**
             * Polymorphic target:
             * - User (therapist / clinic_manager)
             * - Clinic (clinic holiday / closure)
             */
            $table->morphs('exceptionable', 'excp_morph_idx');
            // exceptionable_type
            // exceptionable_id

            /**
             * Clinic context (always required)
             */
            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Clinic context for this exception');

            /**
             * Exception type
             */
            $table->enum('type', [
                'leave_full_day',
                'leave_partial',
                'extra_hours',
                'override_hours',
                'blocked_hours',
            ])->index()->comment('Availability exception type');

            /**
             * Effect on availability
             */
            $table->enum('effect', ['block', 'add', 'override'])
                ->index()
                ->nullable()
                ->comment('How this exception affects availability');

            /**
             * Date range
             */
            $table->date('start_date')
                ->index()
                ->comment('Start date of exception');

            $table->date('end_date')
                ->index()
                ->comment('End date of exception');

            /**
             * Time range (15-min aligned)
             * NULL + NULL = full day
             */
            $table->time('start_time')
                ->nullable()
                ->comment('Start time (NULL = full day)');

            $table->time('end_time')
                ->nullable()
                ->comment('End time (NULL = full day)');

            /**
             * Leave accounting (only for leave_* types)
             */
            $table->enum('leave_type', [
                'paid',
                'unpaid',
                'sick',
                'emergency',
                'other',
            ])->nullable()
              ->comment('Leave entitlement type');

            $table->decimal('leave_days', 5, 2)
                ->nullable()
                ->comment('Consumed leave days (0.5, 1, 2, etc)');

            /**
             * Notes & reason
             */
            $table->text('reason')
                ->nullable()
                ->comment('Reason for exception');

            $table->text('notes')
                ->nullable()
                ->comment('Internal notes');

            /**
             * Approval & audit
             */
            $table->enum('status', ['pending', 'approved', 'rejected'])
                ->default('pending')
                ->index();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();

            /**
             * Performance index for availability engine
             */
            $table->index(
                ['exceptionable_type', 'exceptionable_id', 'clinic_id', 'start_date', 'end_date'],
                'availability_exception_lookup_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
