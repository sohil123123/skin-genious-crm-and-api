<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('cloudflare_tunnel_url')->nullable()->after('number_of_beds');
            $table->string('device_ip')->nullable()->after('cloudflare_tunnel_url');
            $table->string('agent_api_key')->nullable()->after('device_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['cloudflare_tunnel_url', 'device_ip', 'agent_api_key']);
        });
    }
};
