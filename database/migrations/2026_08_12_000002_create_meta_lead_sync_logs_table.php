<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_lead_sync_logs', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            // Meta redelivers a webhook whenever it does not see a prompt 200,
            // so the same leadgen_id genuinely arrives more than once. This
            // unique index is the first of the four duplicate defences and the
            // only one that stops a redelivery before it reaches the queue.
            $table->string('leadgen_id', 64)
                ->unique()
                ->comment('Meta lead id from the webhook; unique so a redelivered notification cannot be queued twice');

            $table->foreignId('meta_page_id')
                ->nullable()
                ->constrained('meta_pages')
                ->nullOnDelete()
                ->comment('Page the lead came from; NULL when the webhook named a Page the CRM does not know');

            $table->foreignId('lead_id')
                ->nullable()
                ->constrained('leads')
                ->nullOnDelete()
                ->comment('CRM lead this produced; NULL until processing succeeds, and again if that lead is later deleted');

            $table->string('status', 20)
                ->default('pending')
                ->comment('pending, processing, success, skipped when the lead already existed, or failed');

            $table->unsignedInteger('attempts')
                ->default(0)
                ->comment('Processing attempts so far, including automatic job retries');

            $table->text('error_message')
                ->nullable()
                ->comment('Why the last attempt failed; shown on the sync log screen for troubleshooting');

            $table->json('payload')
                ->nullable()
                ->comment('The webhook change value exactly as received, kept so a failed lead can be replayed');

            $table->timestamp('processed_at')
                ->nullable()
                ->comment('When processing finished, successfully or not');

            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_meta_sync_status_created');
            $table->index('lead_id', 'idx_meta_sync_lead');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_lead_sync_logs');
    }
};
