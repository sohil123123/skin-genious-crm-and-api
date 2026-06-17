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
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('hsn_sac_code')->nullable()->after('product_id')->comment('Snapshot of HSN/SAC code');
            $table->decimal('taxable_value', 10, 2)->default(0)->after('valid_discount_amount')->comment('Taxable value after discount, before GST');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_sac_code', 'taxable_value']);
        });
    }
};
