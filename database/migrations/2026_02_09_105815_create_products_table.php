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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['product', 'service', 'iv_product']);
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->nullable();
            $table->decimal('sell_price', 10, 2);
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->decimal('gst', 10, 2)->nullable();
            $table->string('unit')->nullable(); // ml, mg
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
