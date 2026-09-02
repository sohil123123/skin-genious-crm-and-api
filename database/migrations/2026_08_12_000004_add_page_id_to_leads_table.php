<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the Facebook Page id alongside the name.
     *
     * Every other Meta identifier already lives on the lead — campaign_id,
     * adset_id, ad_id, form_id — and the Page was the one exception, carrying
     * only a name. A name is not a stable key: Pages get renamed, and two
     * clinics can run Pages with similar names. Storing the id keeps the lead
     * able to answer "which Page produced this?" on its own, without walking
     * back through the sync log.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('page_id', 64)
                ->nullable()
                ->after('page_name')
                ->comment('Facebook Page id; present on leads received through the webhook, absent on older CSV imports');

            $table->index('page_id', 'idx_lead_page_id');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('idx_lead_page_id');
            $table->dropColumn('page_id');
        });
    }
};
