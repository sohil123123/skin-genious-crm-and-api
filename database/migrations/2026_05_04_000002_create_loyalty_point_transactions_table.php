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
        Schema::create('loyalty_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Client who owns the points');
            $table->foreignId('invoice_payment_id')->nullable()->constrained()->nullOnDelete()->comment('Related payment record');
            $table->enum('type', ['earn', 'redeem', 'reverse'])->comment('Transaction type');
            $table->integer('points')->comment('Number of points (positive for earn, negative for redeem/reverse)');
            $table->integer('balance_after')->default(0)->comment('Running balance after this transaction');
            $table->string('description')->nullable()->comment('Human-readable description');
            $table->json('metadata')->nullable()->comment('Additional context data');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Staff member who processed this');
            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_point_transactions');
    }
};
