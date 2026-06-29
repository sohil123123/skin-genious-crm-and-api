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
        Schema::table('treatment_sessions', function (Blueprint $table) {
            $table->json('post_feature_packet')->nullable()->comment('Stores OpenCV feature packet of the scans taken after the session');
            $table->json('post_diagnosis')->nullable()->comment('Stores AI reassessment comparison results after the session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treatment_sessions', function (Blueprint $table) {
            $table->dropColumn(['post_feature_packet', 'post_diagnosis']);
        });
    }
};
