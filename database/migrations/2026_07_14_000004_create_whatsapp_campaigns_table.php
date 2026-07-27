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
        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('whatsapp_templates')->nullOnDelete();
            $table->json('template_variables')->nullable(); // mapping of variable placeholders to user fields
            $table->string('audience_type', 30)->default('all'); // all, specific, filter
            $table->json('audience_filter')->nullable(); // {clinic_id, gender, date_from, date_to, etc.}
            $table->json('audience_user_ids')->nullable(); // specific user IDs
            $table->string('status', 30)->default('draft'); // draft, scheduled, sending, completed, cancelled, paused
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule')->nullable(); // cron expression or descriptive rule
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
