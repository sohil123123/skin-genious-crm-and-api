<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_pages', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->string('page_id', 64)
                ->unique()
                ->comment('Facebook Page id; the only thing a leadgen webhook gives us to identify the sender, so it routes the lead to a clinic');

            $table->string('page_name')
                ->nullable()
                ->comment('Human-readable Page name, resolved from the Graph API and refreshed on connect');

            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Clinic that owns leads from this Page; webhooks carry no auth context so this mapping is the only source of clinic_id');

            $table->text('access_token')
                ->nullable()
                ->comment('Page access token with leads_retrieval, stored encrypted; a lead cannot be fetched without it');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Inactive pages keep their history but stop accepting new webhook leads');

            $table->timestamp('subscribed_at')
                ->nullable()
                ->comment('When this Page was last subscribed to the leadgen field via the subscribed_apps edge');

            $table->timestamp('last_lead_at')
                ->nullable()
                ->comment('Last time a lead arrived from this Page; a stale value is the first sign a subscription has lapsed');

            $table->timestamps();

            $table->index(['clinic_id', 'is_active'], 'idx_meta_page_clinic_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_pages');
    }
};
