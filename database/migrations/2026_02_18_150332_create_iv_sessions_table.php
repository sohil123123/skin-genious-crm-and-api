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
        Schema::create('iv_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('treatment_session_id')
                ->nullable()
                ->constrained('treatment_sessions')
                ->nullOnDelete();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('selected_protocol_id')->nullable();
            $table->enum('selected_option_type', [
                'single_session_option_1',
                'single_session_option_2',
                'plan_option',
                'budget_option'
            ])->nullable();

            $table->boolean('is_plan')->default(false);
            $table->unsignedTinyInteger('plan_week_index')->nullable();

            $table->enum('status', ['pending', 'scheduled', 'in_progress', 'completed', 'cancelled'])->default('pending')->comment('Current status of the treatment session');

            $table->json('engine_versions')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iv_sessions');
    }
};
