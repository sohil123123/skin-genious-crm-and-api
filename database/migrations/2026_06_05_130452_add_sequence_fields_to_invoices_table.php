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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('state_code', 5)->nullable()->after('clinic_id')->comment('State Code at time of generation');
            $table->unsignedInteger('sequence_number')->nullable()->after('state_code');
            $table->string('old_invoice_number')->nullable()->after('sequence_number')->comment('Backup before migration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['state_code', 'sequence_number', 'old_invoice_number']);
        });
    }
};
