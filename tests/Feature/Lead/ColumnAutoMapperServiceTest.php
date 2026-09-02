<?php

declare(strict_types=1);

use App\DTOs\Lead\ColumnMappingDto;
use App\Enums\CrmLeadField;
use App\Enums\LeadFieldType;
use App\Services\Lead\ColumnAutoMapperService;
use App\Services\Lead\CsvReaderService;
use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->mapper = app(ColumnAutoMapperService::class);
    // An empty registry keeps these tests off the database: what is under test
    // is the matching logic, not the lookup.
    $this->noExistingFields = new Collection();
});

function mapFixtureColumn(string $header, array $samples = []): ColumnMappingDto
{
    return app(ColumnAutoMapperService::class)->mapColumn($header, new Collection(), $samples);
}

it('maps every Meta metadata column with no manual intervention', function (): void {
    $expected = [
        'id' => CrmLeadField::FbLeadId,
        'created_time' => CrmLeadField::FbCreatedTime,
        'ad_id' => CrmLeadField::AdId,
        'ad_name' => CrmLeadField::AdName,
        'adset_id' => CrmLeadField::AdsetId,
        'adset_name' => CrmLeadField::AdsetName,
        'campaign_id' => CrmLeadField::CampaignId,
        'campaign_name' => CrmLeadField::CampaignName,
        'form_id' => CrmLeadField::FormId,
        'form_name' => CrmLeadField::FormName,
        'is_organic' => CrmLeadField::IsOrganic,
        'platform' => CrmLeadField::Platform,
        'full_name' => CrmLeadField::FullName,
        'phone' => CrmLeadField::Phone,
        'lead_status' => CrmLeadField::FbLeadStatus,
    ];

    foreach ($expected as $header => $field) {
        $dto = mapFixtureColumn($header);

        expect($dto->isCore())->toBeTrue($header)
            ->and($dto->coreField())->toBe($field, $header)
            ->and($dto->confidence)->toBe(100.0, $header);
    }
});

it('maps the hand-written header variants from the brief', function (): void {
    $expected = [
        'Customer Name' => CrmLeadField::FullName,
        'Your Name' => CrmLeadField::FullName,
        'Mobile Number' => CrmLeadField::Phone,
        'WhatsApp' => CrmLeadField::Phone,
        'Phone Number' => CrmLeadField::Phone,
        'Email Address' => CrmLeadField::Email,
        'Mail' => CrmLeadField::Email,
        'Location' => CrmLeadField::City,
        'Town' => CrmLeadField::City,
    ];

    foreach ($expected as $header => $field) {
        expect(mapFixtureColumn($header)->coreField())->toBe($field, $header);
    }
});

it('turns an unrecognised question into a custom field', function (): void {
    $dto = mapFixtureColumn('what_is_your_main_skin_concern?', ['pigmentation', 'dullness_/_tanning']);

    expect($dto->isCustom())->toBeTrue()
        ->and($dto->customKey())->toBe('what_is_your_main_skin_concern')
        ->and($dto->resolvedCustomLabel())->toBe('what_is_your_main_skin_concern?');
});

it('detects a closed answer set as a single choice field', function (): void {
    // Every question in the sample exports has six or fewer distinct answers,
    // which is what makes them filterable rather than dead free text.
    $type = $this->mapper->inferType([
        'dullness_/_tanning', 'pigmentation', 'open_pores_/_texture',
        'just_want_glow_before_an_event', 'acne_/_acne_marks', 'dryness_/_sensitivity',
    ]);

    expect($type)->toBe(LeadFieldType::Select);
});

it('detects a pipe-separated answer as multiple choice', function (): void {
    $type = $this->mapper->inferType([
        'pigmentation',
        'i_want_an_ai_skin_analysis_first|dryness_/_sensitivity|open_pores_/_texture',
    ]);

    expect($type)->toBe(LeadFieldType::MultiSelect);
});

it('does not mistake numeric answers for dates', function (): void {
    expect($this->mapper->inferType(['3000', '5000', '8000']))->toBe(LeadFieldType::Number);
});

it('normalises headers so wording and punctuation do not matter', function (): void {
    expect($this->mapper->normalizeHeader('what_is_your_main_skin_concern?'))
        ->toBe('what is your main skin concern')
        ->and($this->mapper->normalizeHeader('What Is Your Main Skin Concern?'))
        ->toBe('what is your main skin concern');
});

it('scores a contained header as a strong match', function (): void {
    // "skin concern" inside "what is your main skin concern" is a real match
    // that raw character similarity would score far too low.
    expect($this->mapper->similarity('skin concern', 'what is your main skin concern'))
        ->toBeGreaterThanOrEqual(80.0);
});

it('ranks the two wordings of the skin concern question as similar', function (): void {
    // These two headers both appear across the real exports and are the same
    // question. The mapper must rate them close enough to suggest folding.
    $score = $this->mapper->similarity(
        $this->mapper->normalizeHeader('what_is_your_main_skin_concern?'),
        $this->mapper->normalizeHeader('what_is_your_main_skin_concern_right_now?'),
    );

    expect($score)->toBeGreaterThanOrEqual(
        (float) config('leads.auto_mapping.custom_field_similarity_threshold')
    );
});

it('does not let two columns claim the same CRM field', function (): void {
    // A file with both "phone" and "Mobile Number" must not have the second
    // silently overwrite the first during import.
    $mapping = $this->mapper->map(['phone', 'Mobile Number'], null, [], $this->noExistingFields);

    expect($mapping['phone']->isCore())->toBeTrue()
        ->and($mapping['phone']->coreField())->toBe(CrmLeadField::Phone)
        ->and($mapping['Mobile Number']->isCustom())->toBeTrue();
});

it('maps a whole real export end to end', function (): void {
    $reader = app(CsvReaderService::class);
    $path = base_path('tests/Fixtures/leads/New Leads Ad_Leads_2026-07-24_2026-08-05.csv');
    $headers = $reader->headers($path, 'UTF-16LE', "\t");

    $core = 0;
    $custom = 0;

    foreach ($headers as $header) {
        $dto = mapFixtureColumn($header);
        $dto->isCore() ? $core++ : ($dto->isCustom() ? $custom++ : null);
    }

    // 14 Meta/contact columns map automatically; the 4 form questions become
    // dynamic fields. Nothing is left unhandled.
    expect($core)->toBe(14)
        ->and($custom)->toBe(4)
        ->and($core + $custom)->toBe(count($headers));
});
