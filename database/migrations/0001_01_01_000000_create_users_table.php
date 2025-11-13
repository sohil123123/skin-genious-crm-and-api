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

            // Loyalty Program Information
            $table->integer('loyalty_points')->default(0)->comment('User loyalty points');

            // ==================== MEDICAL BACKGROUND ====================
            $table->boolean('has_diabetes')->default(false)->comment('Diabetes medical condition');
            $table->boolean('has_high_bp')->default(false)->comment('High Blood Pressure medical condition');
            $table->boolean('has_cholesterol')->default(false)->comment('High Cholesterol medical condition');
            $table->boolean('has_asthma')->default(false)->comment('Asthma medical condition');
            $table->boolean('has_heart_disease')->default(false)->comment('Heart Disease medical condition');
            $table->boolean('has_anaemia')->default(false)->comment('Anaemia medical condition');
            $table->boolean('has_pcos')->default(false)->comment('PCOS (Polycystic Ovary Syndrome) medical condition');
            $table->boolean('has_thyroid')->default(false)->comment('Thyroid medical condition');
            $table->text('other_diseases')->nullable()->comment('Other medical conditions not listed');
            $table->text('current_medications')->nullable()->comment('Current medications being taken');
            $table->text('allergies')->nullable()->comment('Known allergies to medications or products');

            // ==================== SKIN PROFILE ====================
            $table->enum('skin_type', [
                'Normal',
                'Dry',
                'Oily',
                'Combination',
                'Sensitive'
            ])->nullable()->comment('User skin type for personalized recommendations');

            $table->text('facials_history')->nullable()->comment('History of previous facials and treatments');
            $table->enum('skin_quality', ['Poor', 'Fair', 'Good', 'Excellent'])->nullable()->comment('Overall skin quality assessment');

            // ==================== AESTHETIC GOALS - "I Want To Look" ====================
            $table->boolean('goal_less_tired')->default(false)->comment('Aesthetic goal: Look less tired');
            $table->boolean('goal_less_angry')->default(false)->comment('Aesthetic goal: Look less angry');
            $table->boolean('goal_less_sad')->default(false)->comment('Aesthetic goal: Look less sad');
            $table->boolean('goal_less_saggy')->default(false)->comment('Aesthetic goal: Look less saggy');
            $table->boolean('goal_youthful')->default(false)->comment('Aesthetic goal: Look more youthful');
            $table->boolean('goal_attractive')->default(false)->comment('Aesthetic goal: Look more attractive');
            $table->boolean('goal_soft_features')->default(false)->comment('Aesthetic goal: Have softer features');
            $table->boolean('goal_slim_face')->default(false)->comment('Aesthetic goal: Have slimmer face');

            $table->enum('skin_improvement', ['Hydration', 'Smoothness', 'Elasticity'])->nullable()->comment('Skin improvement goals');

            // Authentication
            $table->timestamp('email_verified_at')->nullable()->comment('Timestamp when email was verified');
            $table->string('password')->comment('Encrypted password for user authentication');
            $table->rememberToken()->comment('Remember token for "keep me logged in" functionality');

            // Account Status
            $table->boolean('is_active')->default(true)->comment('Whether user account is active (true/false)');

            // Timestamps
            $table->softDeletes()->comment('Soft delete timestamp for user account deletion');
            $table->timestamps();

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
