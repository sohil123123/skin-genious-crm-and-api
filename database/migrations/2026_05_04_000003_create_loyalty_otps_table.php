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
        Schema::create('loyalty_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->comment('Client for whom OTP is generated');
            $table->string('otp', 6)->comment('6-digit OTP code');
            $table->string('purpose')->default('loyalty_redeem')->comment('Purpose of the OTP');
            $table->timestamp('expires_at')->comment('When the OTP expires');
            $table->timestamp('used_at')->nullable()->comment('When the OTP was consumed');
            $table->timestamps();

            $table->index(['user_id', 'otp', 'purpose']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_otps');
    }
};
