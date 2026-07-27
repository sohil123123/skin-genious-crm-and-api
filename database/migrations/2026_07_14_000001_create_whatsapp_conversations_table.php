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
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number')->index();
            $table->string('contact_name')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->text('last_message_preview')->nullable();
            $table->boolean('is_window_open')->default(false);
            $table->timestamp('window_expires_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->boolean('is_starred')->default(false);
            $table->json('labels')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_muted')->default(false);
            $table->timestamps();

            $table->unique('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
