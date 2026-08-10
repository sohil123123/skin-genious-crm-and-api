<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('clinic_id')
                ->constrained('clinics')
                ->cascadeOnDelete()
                ->comment('Clinic that owns this lead; all duplicate matching is scoped to it');

            $table->foreignId('lead_import_id')
                ->nullable()
                ->constrained('lead_imports')
                ->nullOnDelete()
                ->comment('Import batch this lead arrived in; NULL for manually created leads');

            // ─── Identity ───────────────────────────────────────────
            $table->string('full_name')->nullable()->comment('Cleaned display name, e.g. "Saini...Poonam" stored as "Saini Poonam"');
            $table->string('first_name')->nullable()->comment('First token of the cleaned name');
            $table->string('last_name')->nullable()->comment('Remaining tokens of the cleaned name');

            $table->string('phone', 20)->nullable()->comment('Normalised number in +<country><national> form');
            $table->string('phone_raw', 100)->nullable()->comment('Original phone value exactly as exported, including the Meta p: prefix');
            $table->string('phone_status', 20)->default('valid')->comment('valid, needs_review when salvaged from a malformed value, or invalid');

            $table->string('email')->nullable()->comment('Normalised lowercase email; Meta lead forms in this account do not collect one');
            $table->string('email_raw')->nullable()->comment('Original email value exactly as exported');

            $table->string('city')->nullable()->comment('City, when the lead form collects it');
            $table->string('state')->nullable()->comment('State, when the lead form collects it');
            $table->string('pincode', 20)->nullable()->comment('Postal code, when the lead form collects it');

            // ─── Pipeline ───────────────────────────────────────────
            $table->string('source', 30)->default('facebook')->comment('Where the lead came from: facebook, instagram, manual, referral, other');
            $table->string('status', 30)->default('new')->comment('Pipeline stage: new, contacted, qualified, unqualified, junk, lost, won');

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Staff member responsible for following up');

            $table->foreignId('matched_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->comment('Existing patient whose phone or email matches this lead; flagged, never merged automatically');

            $table->text('notes')->nullable()->comment('Free-text staff notes');

            // ─── Facebook metadata ──────────────────────────────────
            $table->string('fb_lead_id', 64)->nullable()->comment('Meta lead id with the l: prefix stripped; unique per clinic and the default duplicate key');
            $table->dateTime('fb_created_time')->nullable()->comment('Meta submission time converted to UTC from the ad account timezone');
            $table->string('campaign_name')->nullable()->comment('Meta campaign name');
            $table->string('campaign_id', 64)->nullable()->comment('Meta campaign id with the c: prefix stripped');
            $table->string('adset_name')->nullable()->comment('Meta ad set name');
            $table->string('adset_id', 64)->nullable()->comment('Meta ad set id with the as: prefix stripped');
            $table->string('ad_name')->nullable()->comment('Meta ad name');
            $table->string('ad_id', 64)->nullable()->comment('Meta ad id with the ag: prefix stripped');
            $table->string('form_name')->nullable()->comment('Meta lead form name');
            $table->string('form_id', 64)->nullable()->comment('Meta lead form id with the f: prefix stripped');
            $table->string('page_name')->nullable()->comment('Facebook page name, when present in the export');
            $table->string('platform', 20)->nullable()->comment('Originating platform: ig or fb');
            $table->boolean('is_organic')->default(false)->comment('Whether the lead came from an organic post rather than a paid ad');
            $table->string('fb_lead_status', 30)->nullable()->comment('Meta lead_status column, present in some exports only');

            // ─── Provenance ─────────────────────────────────────────
            $table->json('raw_payload')->nullable()->comment('Untouched original CSV row, kept so any mapping mistake can be replayed');
            $table->string('row_hash', 64)->nullable()->comment('Hash of the normalised identity fields, used for fast in-file duplicate detection');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who created the record');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who last updated the record');

            $table->timestamps();
            $table->softDeletes();

            // MySQL allows repeated NULLs in a unique index, so leads without a
            // Meta id (manual entries) are unaffected by this constraint.
            $table->unique(['clinic_id', 'fb_lead_id'], 'uniq_lead_clinic_fb_id');

            $table->index(['clinic_id', 'phone'], 'idx_lead_clinic_phone');
            $table->index(['clinic_id', 'email'], 'idx_lead_clinic_email');
            $table->index(['clinic_id', 'status'], 'idx_lead_clinic_status');
            $table->index(['clinic_id', 'created_at'], 'idx_lead_clinic_created');
            $table->index('campaign_name', 'idx_lead_campaign');
            $table->index('form_name', 'idx_lead_form');
            $table->index('lead_import_id', 'idx_lead_import');
            $table->index('assigned_to', 'idx_lead_assigned');
            $table->index('matched_user_id', 'idx_lead_matched_user');
            $table->index('phone_status', 'idx_lead_phone_status');
            $table->index('row_hash', 'idx_lead_row_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
