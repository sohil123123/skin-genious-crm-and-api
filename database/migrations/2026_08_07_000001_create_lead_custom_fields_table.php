<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_custom_fields', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Owning clinic; NULL means the field is shared across all clinics');

            $table->string('key', 191)
                ->comment('Machine slug derived from the label, truncated with a hash suffix when the label is long');

            $table->text('label')
                ->comment('Original question text from the lead form; Meta questions reach 157+ characters so this cannot be a varchar');

            $table->string('type', 20)
                ->default('text')
                ->comment('Field type: text, textarea, number, date, boolean, select, multiselect');

            $table->json('options')
                ->nullable()
                ->comment('Distinct answer options collected from imports, used for filters and validation');

            $table->text('description')
                ->nullable()
                ->comment('Optional staff-facing note about what this question captures');

            $table->boolean('is_active')
                ->default(true)
                ->comment('Inactive fields are hidden from mapping suggestions and lead detail screens');

            $table->unsignedInteger('usage_count')
                ->default(0)
                ->comment('How many lead answers reference this field; drives merge and cleanup decisions');

            $table->unsignedInteger('sort_order')
                ->default(0)
                ->comment('Display order on the lead detail screen');

            $table->timestamps();

            $table->unique(['clinic_id', 'key'], 'uniq_lead_custom_field_key');
            $table->index(['clinic_id', 'is_active'], 'idx_lead_custom_field_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_custom_fields');
    }
};
