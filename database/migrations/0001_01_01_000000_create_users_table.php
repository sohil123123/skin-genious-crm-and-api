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
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('Primary key, auto-increment user ID');

            // Temporarily add clinic_id as a plain column (no foreign key yet)
            $table->unsignedBigInteger('clinic_id')->nullable()->comment('ID of the clinic the user is assigned to');

            // Basic Personal Information
            $table->string('first_name')->comment('User first name');
            $table->string('last_name')->comment('User last name');
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable()->comment('User gender selection');
            $table->date('date_of_birth')->nullable()->comment('User date of birth');

            // Contact Information
            $table->string('mobile')->unique()->comment('User mobile number');
            $table->string('email')->unique()->nullable()->comment('User email address');
            $table->string('occupation')->nullable()->comment('User occupation');

            // Address Information
            $table->text('address_line_1')->nullable()->comment('Primary address line');
            $table->text('address_line_2')->nullable()->comment('Secondary address line');
            $table->string('pincode', 10)->nullable()->comment('Postal/ZIP code');
            $table->string('city')->nullable()->comment('City name');

            // Referral and Marketing Information
            $table->string('referral_code')->unique()->nullable()->comment('Unique referral code for this user to share with others');
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate()->comment('User ID who referred this user (self-join)');

            $table->boolean('opt_for_loyalty')->default(false)->comment('Whether user opted for loyalty program (true/false)');
            $table->enum('how_did_you_hear', [
                'Skin Genius',
                'Social Media',
                'Friend Referral',
                'Google Search',
                'Practo/Lybrate',
                'By Doctor',
                'Other'
            ])->nullable()->comment('How user heard about our skincare website');

            // Referral Statistics
            $table->integer('total_referrals')->default(0)->comment('Total number of successful referrals');
            $table->decimal('referral_earnings', 10, 2)->default(0)->comment('Total earnings from referrals');
            $table->decimal('pending_referral_earnings', 10, 2)->default(0)->comment('Pending referral earnings');

            // Authentication
            $table->timestamp('email_verified_at')->nullable()->comment('Timestamp when email was verified');
            $table->string('password')->comment('Encrypted password for user authentication');
            $table->rememberToken()->comment('Remember token for "keep me logged in" functionality');

            // Account Status
            $table->boolean('is_active')->default(true)->comment('Whether user account is active (true/false)');

            // Timestamps
            $table->timestamps();
            $table->softDeletes()->comment('Soft delete timestamp for user account deletion');

            // Indexes for better performance
            $table->index('mobile');
            $table->index('email');
            $table->index('referred_by');
            $table->index('opt_for_loyalty');
            $table->index('is_active');

            $table->comment('Main users table for skincare website - stores customer information and authentication details');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
