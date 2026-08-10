<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_import_failures', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('lead_import_id')
                ->constrained('lead_imports')
                ->cascadeOnDelete()
                ->comment('Import this failed row belongs to');

            $table->unsignedInteger('row_number')
                ->comment('1-based data row number in the source file, excluding the header');

            $table->string('reason_code', 30)
                ->comment('Failure category: validation, duplicate, missing_required, transform or database');

            $table->text('reason')
                ->comment('Human-readable explanation shown in the failures table');

            $table->json('errors')
                ->nullable()
                ->comment('Field-keyed validation messages');

            $table->json('raw_row')
                ->comment('Original CSV row, so the failure can be exported and retried without the source file');

            $table->json('mapped_row')
                ->nullable()
                ->comment('Row after mapping and normalisation, when the failure occurred later in the pipeline');

            $table->boolean('is_resolved')
                ->default(false)
                ->comment('Set once the row has been successfully re-imported');

            $table->dateTime('retried_at')->nullable()->comment('When this row was last retried');
            $table->dateTime('resolved_at')->nullable()->comment('When this row was successfully imported');

            $table->timestamps();

            $table->index(['lead_import_id', 'reason_code'], 'idx_lead_failure_import_reason');
            $table->index(['lead_import_id', 'is_resolved'], 'idx_lead_failure_import_resolved');
            $table->index(['lead_import_id', 'row_number'], 'idx_lead_failure_import_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_import_failures');
    }
};
