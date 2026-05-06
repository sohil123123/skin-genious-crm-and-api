<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete()->comment('Invoice ID');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->comment('Product ID');
            $table->integer('quantity')->default(1)->comment('Quantity');
            $table->decimal('unit_price', 10, 2)->comment('Unit Price'); // GST Inclusive
            $table->string('discount_type')->nullable()->default('flat')->comment('Discount Type'); // flat or percentage
            $table->decimal('discount_value', 10, 2)->default(0)->comment('Discount Value');
            $table->decimal('valid_discount_amount', 10, 2)->default(0)->comment('Actual calculated discount amount'); // Actual calculated discount amount
            $table->decimal('gst_percentage', 5, 2)->default(0)->comment('GST Percentage');
            $table->decimal('gst_amount', 10, 2)->default(0)->comment('GST Amount');
            $table->decimal('line_total', 10, 2)->comment('Line Total');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
