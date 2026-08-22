<?php

use App\Models\LeadCustomField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preserve the question as the customer was actually asked it.
     *
     * label serves two masters: it is the wording shown throughout the CRM, and
     * it was also the only record of what the lead form asked. The edit screen
     * openly invites tidying that wording, so the moment anyone does, the
     * original question is gone — and the lead detail page starts attributing a
     * question to someone who was never asked it.
     *
     * source_label is written once, when the question is first seen, and never
     * edited. label stays free to be rewritten for readability.
     */
    public function up(): void
    {
        Schema::table('lead_custom_fields', function (Blueprint $table) {
            $table->text('source_label')
                ->nullable()
                ->after('label')
                ->comment('The question exactly as the lead form asked it; set on first sight and never edited, unlike label');
        });

        $this->backfill();
    }

    /**
     * Recover the original wording for questions that already exist.
     *
     * The key is a slug of the original label, and every lead keeps the
     * untouched payload it arrived in — so for a question whose label was
     * already rewritten, the true wording can usually be found by slugging the
     * raw question names and looking for the one that produces this key.
     */
    protected function backfill(): void
    {
        foreach (DB::table('lead_custom_fields')->get() as $field) {
            $recovered = $this->recoverLabel((string) $field->key, (int) $field->id);

            DB::table('lead_custom_fields')
                ->where('id', $field->id)
                // Falls back to the current label: for a question nobody
                // renamed, that is the original wording anyway.
                ->update(['source_label' => $recovered ?? $field->label]);
        }
    }

    protected function recoverLabel(string $key, int $fieldId): ?string
    {
        $payloads = DB::table('lead_field_values')
            ->join('leads', 'leads.id', '=', 'lead_field_values.lead_id')
            ->where('lead_field_values.lead_custom_field_id', $fieldId)
            ->whereNotNull('leads.raw_payload')
            ->limit(25)
            ->pluck('leads.raw_payload');

        foreach ($payloads as $payload) {
            $decoded = json_decode((string) $payload, true);

            if (! is_array($decoded)) {
                continue;
            }

            foreach ($this->questionNames($decoded) as $name) {
                if (LeadCustomField::makeKey($name) === $key) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * The question names in a payload, whichever way the lead arrived.
     *
     * A webhook lead carries Meta's field_data list; a CSV import carries the
     * original row, where the headers are the array keys.
     *
     * @param  array<mixed>  $payload
     * @return array<int, string>
     */
    protected function questionNames(array $payload): array
    {
        if (isset($payload['field_data']) && is_array($payload['field_data'])) {
            return array_values(array_filter(array_map(
                fn ($entry): string => is_array($entry) ? trim((string) ($entry['name'] ?? '')) : '',
                $payload['field_data'],
            )));
        }

        return array_values(array_filter(array_map(
            fn ($header): string => trim((string) $header),
            array_keys($payload),
        )));
    }

    public function down(): void
    {
        Schema::table('lead_custom_fields', function (Blueprint $table) {
            $table->dropColumn('source_label');
        });
    }
};
