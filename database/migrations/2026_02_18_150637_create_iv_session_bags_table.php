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
        Schema::create('iv_session_bags', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iv_session_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('bag_label')->nullable();
            $table->string('carrier')->nullable();
            $table->unsignedInteger('volume_ml')->nullable();
            $table->unsignedInteger('min_duration_minutes')->nullable();
            $table->json('rate_profile')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iv_session_bags');
    }
};
