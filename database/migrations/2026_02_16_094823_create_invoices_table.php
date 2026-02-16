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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete()->comment('Clinic ID');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('User ID');
            $table->dateTime('invoice_date')->comment('Invoice Date');
            $table->string('payment_mode')->nullable()->comment('Payment Mode');
            $table->string('source_note')->nullable()->comment('Source Note');
            $table->decimal('subtotal', 10, 2)->default(0)->comment('Subtotal');
            $table->decimal('discount_total', 10, 2)->default(0)->comment('Discount Total');
            $table->decimal('taxable_value', 10, 2)->default(0)->comment('Taxable Value');
            $table->decimal('gst_total', 10, 2)->default(0)->comment('GST Total');
            $table->decimal('grand_total', 10, 2)->default(0)->comment('Grand Total');
            $table->string('status')->default('draft')->comment('Status');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate()->comment('User who created the invoice');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who last updated invoice');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
