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
        Schema::table('assessments', function (Blueprint $table) {
            $table->json('iv_inputs')->nullable()->after('breastfeeding');
            $table->string('assessment_type')->nullable()->default('normal')->after('iv_inputs');
            $table->json('iv_treatment_plan')->nullable()->after('recommended_full_plan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn('iv_inputs');
            $table->dropColumn('assessment_type');
            $table->dropColumn('iv_treatment_plan');
        });
    }
};
