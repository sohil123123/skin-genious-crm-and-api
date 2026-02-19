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
        Schema::create('iv_session_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('iv_session_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->json('scoring_payload')->nullable();
            $table->json('generation_output')->nullable();
            $table->json('execution_output')->nullable();
            $table->json('constraints_snapshot')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iv_session_snapshots');
    }
};
