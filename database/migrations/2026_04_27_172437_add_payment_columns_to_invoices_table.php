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
            $table->string('invoice_number')->unique()->after('id');
            $table->decimal('amount_paid', 12, 2)->default(0)->after('grand_total');
            // amount_due will be calculated in the model or we can store it. For now let's store it to make querying easier.
            $table->decimal('amount_due', 12, 2)->default(0)->after('amount_paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['invoice_number', 'amount_paid', 'amount_due']);
        });
    }
};
