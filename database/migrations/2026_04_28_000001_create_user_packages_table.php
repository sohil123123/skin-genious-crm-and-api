<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_name');
            $table->foreignId('clinic_id')->nullable()->constrained('clinics')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('service_snapshot')->nullable(); // Historical snapshot of the service at purchase time
            $table->text('notes')->nullable();
            $table->unsignedInteger('quantity');          // Total sessions purchased
            $table->unsignedInteger('used_sessions')->default(0);

            // Pricing
            $table->decimal('price_per_unit', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2);       // Pre-discount total

            // Discount
            $table->enum('discount_type', ['flat', 'percentage'])->default('flat');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0); // Calculated
            $table->decimal('final_amount', 10, 2);                // After discount

            // Status & validity
            $table->boolean('is_active')->default(true);
            $table->date('expired_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_packages');
    }
};
