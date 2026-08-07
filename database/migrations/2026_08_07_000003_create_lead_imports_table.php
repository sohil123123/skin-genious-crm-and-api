<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_imports', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Clinic the imported leads belong to');

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('User who uploaded the file');

            $table->foreignId('lead_mapping_template_id')
                ->nullable()
                ->constrained('lead_mapping_templates')
                ->nullOnDelete()
                ->comment('Mapping template applied to this import, if any');

            // ─── File ───────────────────────────────────────────────
            $table->string('original_filename')
                ->comment('Filename as uploaded, shown in the import history');

            $table->string('label')
                ->nullable()
                ->comment('Friendly label parsed from the filename, e.g. the ad name and export date range');

            $table->string('stored_path')
                ->comment('Path to the stored original on the private disk');

            $table->string('disk', 50)
                ->default('local')
                ->comment('Filesystem disk holding the original upload');

            $table->unsignedBigInteger('file_size')
                ->default(0)
                ->comment('Size of the uploaded file in bytes');

            $table->string('file_hash', 64)
                ->nullable()
                ->comment('SHA256 of the upload, used to warn when the same file is uploaded twice');

            // ─── Detected format ────────────────────────────────────
            $table->string('encoding', 20)
                ->default('UTF-8')
                ->comment('Detected character encoding; Meta exports are UTF-16LE');

            $table->string('delimiter', 5)
                ->default(',')
                ->comment('Detected field delimiter; Meta exports are tab separated despite the .csv extension');

            $table->string('enclosure', 5)
                ->default('"')
                ->comment('Detected field enclosure character');

            $table->boolean('has_bom')
                ->default(false)
                ->comment('Whether the file begins with a byte-order mark');

            // ─── Configuration ──────────────────────────────────────
            $table->string('status', 30)
                ->default('pending')
                ->comment('Lifecycle: pending, analyzing, mapping, ready, queued, processing, completed, completed_with_errors, failed, cancelled');

            $table->json('detected_headers')
                ->nullable()
                ->comment('Header row exactly as read from the file');

            $table->json('analysis')
                ->nullable()
                ->comment('Cached preview payload: sample rows, inferred types, distinct values and row statistics');

            $table->json('column_mapping')
                ->nullable()
                ->comment('Confirmed CSV column => target descriptor (core:field, custom:key or ignore)');

            $table->json('settings')
                ->nullable()
                ->comment('Import settings such as trim, phone normalisation, blank row handling and prefix stripping');

            $table->string('duplicate_strategy', 20)
                ->default('skip')
                ->comment('Behaviour when a duplicate is found: skip, update, merge or create_duplicate');

            $table->json('duplicate_match_fields')
                ->nullable()
                ->comment('Fields compared to detect duplicates, e.g. ["fb_lead_id","phone"]');

            // ─── Execution ──────────────────────────────────────────
            $table->uuid('batch_id')
                ->nullable()
                ->comment('Laravel bus batch id when the import was fanned out into chunk jobs');

            $table->unsignedInteger('total_rows')->default(0)->comment('Data rows detected in the file, excluding the header');
            $table->unsignedInteger('processed_rows')->default(0)->comment('Rows processed so far; drives the progress bar');
            $table->unsignedInteger('imported_rows')->default(0)->comment('Rows that created a new lead');
            $table->unsignedInteger('updated_rows')->default(0)->comment('Rows that updated or merged into an existing lead');
            $table->unsignedInteger('skipped_rows')->default(0)->comment('Rows skipped as duplicates or as blank rows');
            $table->unsignedInteger('failed_rows')->default(0)->comment('Rows that could not be imported and were recorded as failures');

            $table->dateTime('started_at')->nullable()->comment('When processing began');
            $table->dateTime('finished_at')->nullable()->comment('When processing ended');
            $table->unsignedInteger('duration_seconds')->nullable()->comment('Wall-clock processing duration in seconds');

            $table->string('failed_export_path')->nullable()->comment('Path to the generated failed-rows CSV, if any');
            $table->text('error_message')->nullable()->comment('Fatal error that aborted the import');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'status'], 'idx_lead_import_clinic_status');
            $table->index('uploaded_by', 'idx_lead_import_uploader');
            $table->index('created_at', 'idx_lead_import_created');
            $table->index('file_hash', 'idx_lead_import_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_imports');
    }
};
