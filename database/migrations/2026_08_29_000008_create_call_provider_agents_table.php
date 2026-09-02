<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maps a provider's idea of an employee onto a CRM user.
 *
 * Necessary because the two rarely line up. Callyzer identifies an agent by the
 * SIM in the phone they are carrying, which is often a personal number that is
 * not in users.mobile, changes when they swap handsets, and is shared when a
 * clinic keeps one company phone on the reception desk. Exotel identifies an
 * agent by whichever number its flow dialled last.
 *
 * Matching on users.mobile alone would therefore attribute a real fraction of
 * calls to nobody, and agent performance figures built on that would be wrong
 * in a way nobody could see. An explicit mapping row makes the association
 * visible, correctable, and durable across handset changes.
 *
 * A row can map by number, by provider employee code, or both. Rows are also
 * historical: deactivating one stops it matching new calls without rewriting
 * the calls it already explained.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_provider_agents', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->string('provider', 30)->comment('Which provider this mapping applies to');

            $table->string('provider_employee_id', 191)->nullable()->comment('Provider internal employee identifier, where one exists');
            $table->string('provider_employee_code', 100)->nullable()->comment('Provider employee code, e.g. Callyzer emp_code');
            $table->string('provider_employee_number', 32)->nullable()->comment('Agent number exactly as the provider reports it');
            $table->string('provider_employee_key', 20)->nullable()->comment('Last N digits of that number; the column matching actually compares on');
            $table->string('provider_employee_name')->nullable()->comment('Agent name as the provider knows it, for recognising the row in a list');

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('CRM staff member this provider identity belongs to; nullable so an unrecognised agent can be recorded before anyone maps it');

            $table->foreignId('clinic_id')
                ->nullable()
                ->constrained('clinics')
                ->nullOnDelete()
                ->comment('Clinic to file the call against when the mapped user has none');

            $table->boolean('active')->default(true)->comment('Whether this mapping is used for new calls; deactivating preserves the calls it already resolved');

            // Set by the resolver rather than by hand. A row that appeared
            // because an unknown number placed a call is exactly what an
            // administrator needs to see and complete.
            $table->boolean('auto_discovered')->default(false)->comment('Whether the CRM created this row from an unrecognised agent number rather than a person adding it');

            $table->timestamp('last_seen_at')->nullable()->comment('When this identity last appeared on a call');
            $table->unsignedInteger('call_count')->default(0)->comment('How many calls this mapping has resolved');

            $table->json('metadata')->nullable()->comment('Provider employee fields with no column of their own, e.g. Callyzer emp_tags');

            $table->text('notes')->nullable()->comment('Why this mapping exists, for whoever inherits it');

            $table->timestamps();

            // One row per provider identity. Both keys are nullable and MySQL
            // permits repeated NULLs, so a mapping keyed only by code and one
            // keyed only by number can coexist.
            $table->unique(['provider', 'provider_employee_key'], 'uniq_provider_agent_key');
            $table->unique(['provider', 'provider_employee_code'], 'uniq_provider_agent_code');

            $table->index(['provider', 'active'], 'idx_provider_agent_active');
            $table->index('user_id', 'idx_provider_agent_user');
            $table->index('auto_discovered', 'idx_provider_agent_discovered');

            $table->comment('Provider employee identity to CRM user mapping, so agent attribution survives shared and changing handsets');
        });

        // Deferred to here: calls is created first, and the column is declared
        // there without a constraint to keep the two migrations independent.
        Schema::table('calls', function (Blueprint $table) {
            $table->foreign('call_provider_agent_id', 'fk_call_provider_agent')
                ->references('id')
                ->on('call_provider_agents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign('fk_call_provider_agent');
        });

        Schema::dropIfExists('call_provider_agents');
    }
};
