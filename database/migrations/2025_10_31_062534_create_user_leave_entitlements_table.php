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
        Schema::create('user_leave_entitlements', function (Blueprint $table) {
            $table->id()->comment('Primary key: Unique leave entitlement ID');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate()->comment('The ID of the clinic_manager/therapist');
            $table->integer('year')->default(date('Y'))->comment('Leave entitlement year');
            $table->enum('leave_type', ['paid', 'unpaid', 'sick', 'emergency', 'other'])->comment('Type of leave entitlement');
            $table->decimal('total_allowed', 5, 2)->default(0)->comment('Total allowed days');
            $table->decimal('remaining', 5, 2)->default(0)->comment('Remaining days');
            $table->decimal('used', 5, 2)->default(0)->comment('Days used');

            $table->timestamps();

            $table->unique(['user_id', 'year', 'leave_type']); // Prevent duplicates
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_leave_entitlements');
    }
};
