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
        Schema::create('iv_session_ingredients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iv_session_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('iv_session_bag_id')
                ->nullable()
                ->constrained('iv_session_bags')
                ->nullOnDelete();

            $table->string('ingredient_name');
            $table->decimal('dose_value', 10, 2)->nullable();
            $table->string('dose_unit')->nullable();
            $table->boolean('is_hero')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iv_session_ingredients');
    }
};
