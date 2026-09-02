<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_field_values', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete()
                ->comment('Lead this answer belongs to');

            $table->foreignId('lead_custom_field_id')
                ->constrained('lead_custom_fields')
                ->cascadeOnDelete()
                ->comment('Question this answer responds to');

            $table->text('value')
                ->nullable()
                ->comment('Raw answer exactly as exported, e.g. "dullness_/_tanning"');

            $table->json('value_json')
                ->nullable()
                ->comment('Pipe-separated multi-answer values split into an array; NULL for single answers');

            $table->string('value_normalized', 255)
                ->nullable()
                ->comment('Humanised, searchable form of the answer, e.g. "Dullness / Tanning"; truncated to keep the index narrow');

            $table->timestamps();

            $table->unique(['lead_id', 'lead_custom_field_id'], 'uniq_lead_field_value');
            $table->index(['lead_custom_field_id', 'value_normalized'], 'idx_lead_field_value_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_field_values');
    }
};
