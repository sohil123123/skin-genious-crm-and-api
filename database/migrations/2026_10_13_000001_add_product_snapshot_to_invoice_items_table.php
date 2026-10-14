<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->json('product_snapshot')->nullable()->after('product_id')
                ->comment('Historical snapshot of the product when the line was invoiced (same shape as user_package_items.service_snapshot)');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('product_snapshot');
        });
    }
};
