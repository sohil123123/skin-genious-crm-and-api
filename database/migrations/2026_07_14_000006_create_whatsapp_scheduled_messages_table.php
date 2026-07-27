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
        Schema::create('whatsapp_scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // target user
            $table->string('phone_number');
            $table->string('type', 30)->default('template'); // template, text, media
            $table->string('template_name')->nullable();
            $table->json('template_variables')->nullable();
            $table->text('content')->nullable(); // text body for text type
            $table->foreignId('media_library_id')->nullable()->constrained('whatsapp_media_library')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('timezone')->default('Asia/Kolkata');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule')->nullable(); // cron expression
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->string('status', 20)->default('pending'); // pending, processing, completed, cancelled, failed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('next_run_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_scheduled_messages');
    }
};
