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
        Schema::create('treatment_sessions', function (Blueprint $table) {
            $table->id()->comment('Primary key: Unique treatment plan ID');

            $table->foreignId('assessment_id')->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate()->comment('Assessment ID if applicable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate()->comment('The ID of the user whose assessment is to be created');

            $table->enum('plan_type', ['single', 'multiple'])->index()->comment('Indicates if the user selected a single or multiple treatment plan');

            $table->unsignedTinyInteger('session_number')->index()->comment('Sequential number of the session within the plan');
            $table->string('title')->comment('Title of the session/treatment');
            $table->string('treatment_time')->nullable()->comment('Duration of each treatment session, e.g., 75 mins');
            $table->unsignedTinyInteger('week')->nullable()->index()->comment('Week number in which this session occurs');

            // JSON columns for complex nested data
            $table->json('preparations_checklist_for_therapist')->nullable()->comment('List of therapist preparation steps');
            $table->json('concerns_addressed')->nullable()->comment('Skin concerns addressed in this treatment');
            $table->json('steps')->nullable()->comment('Detailed steps with ingredients, duration, and how-to instructions');

            $table->timestamps();

            // Indexes for better query performance
            $table->index(['assessment_id', 'user_id', 'plan_type']);
            $table->index(['plan_type', 'week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatment_sessions');
    }
};
