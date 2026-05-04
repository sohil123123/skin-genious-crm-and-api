<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Setting key identifier');
            $table->text('value')->nullable()->comment('Setting value');
            $table->string('group')->default('general')->comment('Settings group for categorization');
            $table->string('description')->nullable()->comment('Human-readable description');
            $table->timestamps();
        });

        // Seed default loyalty settings
        DB::table('settings')->insert([
            [
                'key'         => 'loyalty_points_rate',
                'value'       => '5',
                'group'       => 'loyalty',
                'description' => 'Percentage of invoice payment amount earned as loyalty points',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'loyalty_min_redeem_points',
                'value'       => '1000',
                'group'       => 'loyalty',
                'description' => 'Minimum accumulated points required before redemption is allowed',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'loyalty_otp_expiry_minutes',
                'value'       => '5',
                'group'       => 'loyalty',
                'description' => 'Number of minutes before a loyalty OTP expires',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
