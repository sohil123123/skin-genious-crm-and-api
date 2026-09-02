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
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->string('message_type', 20)->default('template')->after('description');
            $table->foreignId('media_library_id')->nullable()->after('template_variables')
                ->constrained('whatsapp_media_library')->nullOnDelete();
            $table->text('media_caption')->nullable()->after('media_library_id');
            $table->text('header_image_path')->nullable()->after('media_caption');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropForeign(['media_library_id']);
            $table->dropColumn(['message_type', 'media_library_id', 'media_caption', 'header_image_path']);
        });
    }
};
