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
        Schema::create('clinics', function (Blueprint $table) {
            $table->id()->comment('Primary key, auto-increment user ID');

            // $table->foreignId('manager_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete()->comment('User ID of the clinic owner');
            // $table->unsignedBigInteger('manager_id')->nullable()->comment('ID of the user who is the manager of this clinic');
            $table->string('slug')->unique()->comment('Unique slug for the clinic e.g. b-bliss-clinic');
            $table->string('name')->comment('Clinic name e.g. B Bliss Clinic');
            $table->string('phone')->nullable()->comment('Primary phone number for the clinic');
            $table->string('email')->nullable()->comment('Primary email address for the clinic');
            $table->string('address_line1')->comment('First line of the clinic address');
            $table->string('address_line2')->nullable()->comment('Second line of the clinic address (optional)');
            $table->string('pincode')->comment('Postal pincode of the clinic location');
            $table->string('city')->comment('City where the clinic is located');
            $table->string('gst_number')->nullable()->comment('GST registration number for the clinic');
            // $table->text('description')->nullable()->comment('Detailed description of the clinic services and specialties');
            // $table->decimal('first_sale_share', 5, 2)->default(0.00)->comment('Percentage share for the first sale');
            $table->decimal('sale_share', 5, 2)->default(0.00)->comment('Percentage share for subsequent sales');
            $table->string('google_map_link')->nullable()->comment('Embeddable Google Maps link for the clinic location');
            $table->string('logo')->nullable()->comment('File path to the clinic logo image');
            $table->string('website')->nullable()->comment('Website URL for the clinic');
            $table->time('start_time')->nullable()->default('08:00:00')->comment('Clinic opening time');
            $table->time('end_time')->nullable()->default('22:00:00')->comment('Clinic closing time');
            $table->integer('number_of_beds')->default(1)->comment('Number of beds available in the clinic');
            $table->boolean('is_active')->default(true)->comment('Whether user account is active (true/false)');
            $table->softDeletes()->comment('Soft delete timestamp for user account deletion');
            $table->timestamps();

            // Indexes for better performance
            $table->index('name');
            $table->index('is_active');

            $table->comment('Main users table for skincare website - stores customer information and authentication details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};
