<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_mapping_templates', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Owning clinic; NULL means the template is shared across all clinics');

            $table->string('name')
                ->comment('Human name for the saved mapping, e.g. "AI Facial Session Recommendation form"');

            $table->text('description')
                ->nullable()
                ->comment('Optional note describing which lead form this template belongs to');

            $table->string('signature', 40)
                ->comment('SHA1 of the sorted normalised header list; lets a re-upload of the same form auto-suggest this template');

            $table->json('header_columns')
                ->comment('The exact CSV headers this template was built from, used to warn on drift');

            $table->json('mapping')
                ->comment('CSV column => target descriptor (core:field, custom:key or ignore)');

            $table->json('settings')
                ->nullable()
                ->comment('Saved import settings such as trim, phone normalisation and blank row handling');

            $table->string('duplicate_strategy', 20)
                ->default('skip')
                ->comment('Saved duplicate behaviour: skip, update, merge or create_duplicate');

            $table->json('duplicate_match_fields')
                ->nullable()
                ->comment('Saved duplicate match keys, e.g. ["fb_lead_id"]');

            $table->boolean('is_default')
                ->default(false)
                ->comment('Pre-selected when no signature match is found for an upload');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who saved this template');

            $table->unsignedInteger('usage_count')
                ->default(0)
                ->comment('How many imports have used this template');

            $table->dateTime('last_used_at')
                ->nullable()
                ->comment('When this template was last applied to an import');

            $table->timestamps();

            $table->unique(['clinic_id', 'name'], 'uniq_lead_mapping_template_name');
            $table->index('signature', 'idx_lead_mapping_template_signature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_mapping_templates');
    }
};
