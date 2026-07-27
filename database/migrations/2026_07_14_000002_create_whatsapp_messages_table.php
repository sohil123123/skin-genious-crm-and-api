<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message_id')->nullable()->index();
            $table->string('direction', 20)->default('outgoing')->index(); // incoming, outgoing
            $table->string('type', 30)->default('text'); // text, image, video, document, audio, template, reaction, location, contact, sticker
            $table->json('content')->nullable(); // flexible: {body, caption, latitude, longitude, etc.}
            $table->string('template_name')->nullable();
            $table->json('template_variables')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, sent, delivered, read, failed
            $table->timestamp('meta_timestamp')->nullable(); // timestamp from Meta
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('media_id')->nullable(); // Meta media ID
            $table->string('media_url')->nullable();
            $table->string('media_mime_type')->nullable();
            $table->string('media_filename')->nullable();
            $table->string('media_sha256')->nullable();
            $table->string('local_media_path')->nullable(); // local storage path after download
            $table->string('context_message_id')->nullable(); // reply-to message ID
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
