<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_map', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 40)->comment('Old entity: clinic, staff, client, product, sale, sale_product, sale_receipt, appointment, loyalty_opening');
            $table->unsignedBigInteger('old_id')->comment('Primary key in the old CRM database');
            $table->unsignedBigInteger('new_id')->nullable()->comment('Primary key of the row in this database');
            $table->string('new_table', 64)->nullable()->comment('Table that new_id points to');
            $table->enum('action', ['created', 'matched', 'skipped'])->comment('created = inserted by the import, matched = linked to an existing row, skipped = intentionally not imported');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['entity', 'old_id']);
            $table->index(['new_table', 'new_id']);
            $table->index(['entity', 'action']);
            $table->comment('Old CRM -> new CRM id map written by php artisan legacy:import');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_map');
    }
};
