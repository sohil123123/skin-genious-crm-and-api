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
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('state', 5)->nullable()->after('city')->comment('GST State Code e.g. MH, GJ, DL');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('state', 5)->nullable()->after('city')->comment('GST State Code e.g. MH, GJ, DL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn('state');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
