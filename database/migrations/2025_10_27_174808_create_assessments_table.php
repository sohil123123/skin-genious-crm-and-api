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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id()->comment('Primary key: Unique assessment ID');

            $table->foreignId('assessment_id')->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate()->comment('Parent assessment ID if applicable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate()->comment('The ID of the user whose assessment is to be created');
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate()->comment('Clinic associated with this assessment');
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete()->cascadeOnUpdate()->comment('Who created the assessment');

            $table->integer('age')->nullable()->comment('Patient age in years');
            $table->string('daily_sun_exposure_hours')->nullable()->comment('Average daily sun exposure in hours');
            $table->enum('social_event', ['yes', 'no'])->default('no')->comment('Whether the patient has recent or upcoming social events');
            $table->enum('upcoming_travel', ['yes', 'no'])->default('no')->comment('Whether the patient has upcoming travel plans');
            $table->json('medical_history')->nullable()->comment('Patient medical history in JSON format');
            $table->json('allergies')->nullable()->comment('List of patient allergies in JSON format');
            $table->boolean('is_pregnant')->default(false)->comment('Whether the user is pregnant (true/false)');
            $table->enum('breastfeeding', ['yes', 'no'])->nullable()->comment('Whether the patient is currently breastfeeding');
            $table->json('diagnosis')->nullable()->comment('Diagnosis details in JSON format');
            $table->json('parameters_with_abnormal_scores')->nullable()->comment('Parameters with abnormal scores (JSON array or object)');
            $table->enum('selected_plan_type', ['single', 'multiple'])->nullable()->index()->comment('Indicates which treatment plan type (single or multiple) was selected by the user');
            $table->string('total_time')->nullable()->comment('Total duration of selected plan (e.g., 12 weeks)');
            $table->json('recommended_full_plan')->nullable()->comment('Stores the reference recommended full plan only when user selects a single plan');
            $table->enum('status', ['in_progress', 'pending', 'completed', 'incomplete', 'cancelled', 'overdue'])->default('in_progress')->comment('Status of the assessment');
            $table->text('therapist_notes')->nullable()->comment('Additional notes from the therapist');

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('user_id', 'idx_assessments_user_id');
            $table->index('selected_plan_type', 'idx_assessments_selected_plan_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
