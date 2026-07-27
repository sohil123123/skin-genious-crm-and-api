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
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('meta_template_id')->nullable()->index();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->string('variable_type')->default('number');
            $table->string('language')->default('en_US');
            $table->json('components')->nullable();
            $table->string('header_type')->nullable()->default('none');
            $table->text('header_content')->nullable();
            $table->text('body_text')->nullable();
            $table->string('footer_text', 60)->nullable();
            $table->json('buttons')->nullable();
            $table->json('variable_samples')->nullable();
            $table->string('status')->default('APPROVED');
            $table->string('rejected_reason')->nullable();
            $table->string('quality_score')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
