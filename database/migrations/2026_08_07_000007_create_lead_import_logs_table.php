<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_import_logs', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('lead_import_id')
                ->constrained('lead_imports')
                ->cascadeOnDelete()
                ->comment('Import this log entry belongs to');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who triggered the event; NULL for queue-driven events');

            $table->string('level', 20)
                ->default('info')
                ->comment('Severity: debug, info, warning or error');

            $table->string('event', 40)
                ->comment('Lifecycle event: uploaded, analyzed, mapped, queued, chunk_started, chunk_completed, duplicate_action, completed, failed, retried or cancelled');

            $table->text('message')
                ->comment('Human-readable description of what happened');

            $table->json('context')
                ->nullable()
                ->comment('Structured detail such as row numbers, counters or the duplicate decision taken');

            $table->timestamps();

            $table->index(['lead_import_id', 'level'], 'idx_lead_import_log_level');
            $table->index(['lead_import_id', 'event'], 'idx_lead_import_log_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_import_logs');
    }
};
