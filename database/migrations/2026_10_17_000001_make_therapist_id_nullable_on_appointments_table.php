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
        Schema::table('appointments', function (Blueprint $table) {
            // Consult appointments can be booked without a therapist
            $table->unsignedBigInteger('therapist_id')
                ->nullable()
                ->comment('Therapist assigned to the appointment (optional for consult)')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedBigInteger('therapist_id')
                ->nullable(false)
                ->comment('Therapist assigned to the appointment')
                ->change();
        });
    }
};
