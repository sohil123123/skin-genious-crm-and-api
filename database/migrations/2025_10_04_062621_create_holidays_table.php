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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id()->comment('Primary key: Unique holiday ID');

            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Therapist who requested the holiday');
            $table->foreignId('clinic_id')->nullable()->constrained()->comment('Optional: Clinic associated with this holiday request');
            $table->date('start_date')->comment('Holiday start date');
            $table->date('end_date')->comment('Holiday end date');
            $table->text('reason')->nullable()->comment('Reason for holiday request');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->comment('Approval status of holiday request');
            $table->foreignId('approved_by')->nullable()->constrained('users')->comment('Clinic manager who approved/rejected the holiday');
            $table->enum('type', ['paid', 'unpaid', 'sick', 'other'])->default('paid')->comment('Type of holiday');
            $table->integer('days')->default(1)->comment('Number of days for the holiday'); // Calculated days for the holiday (e.g., end_date - start_date + 1)

            $table->index('user_id', 'idx_holidays_user_id'); // Faster lookups by user
            $table->index('clinic_id', 'idx_holidays_clinic_id'); // Faster clinic-based filters
            $table->index(['start_date', 'end_date'], 'idx_holidays_date_range'); // Common for date range queries
            $table->index('approved_by', 'idx_holidays_approved_by');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
