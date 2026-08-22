<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Publish the date presentation settings.
     *
     * The Settings screen renders whatever rows exist, grouped, so inserting
     * these is all it takes for them to become editable — no bespoke UI.
     *
     * @var array<string, array{value: string, description: string}>
     */
    protected array $settings = [
        'display_date_format' => [
            'value' => 'd M Y',
            'description' => 'How a date without a time reads, e.g. 22 Aug 2026. Uses PHP date() characters.',
        ],
        'display_datetime_format' => [
            'value' => 'd M Y, h:i A',
            'description' => 'How a date with a time reads, e.g. 22 Aug 2026, 03:55 AM. Uses PHP date() characters.',
        ],
        'display_time_format' => [
            'value' => 'h:i A',
            'description' => 'How a time on its own reads, e.g. 03:55 AM. Uses PHP date() characters.',
        ],
        'display_timezone' => [
            'value' => 'Asia/Kolkata',
            'description' => 'Timezone for values stored as UTC (Meta leads). Appointment times are stored already local and are not converted.',
        ],
    ];

    public function up(): void
    {
        foreach ($this->settings as $key => $setting) {
            // Never clobber a value someone has already set.
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('settings')->insert([
                'key' => $key,
                'value' => $setting['value'],
                'group' => 'display',
                'description' => $setting['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys($this->settings))->delete();
    }
};
