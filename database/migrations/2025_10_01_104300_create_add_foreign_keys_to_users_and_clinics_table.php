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
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('clinic_id')->references('id')->on('clinics')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
        });

        Schema::table('clinics', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
    }
};
