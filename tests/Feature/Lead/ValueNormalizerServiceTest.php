<?php

declare(strict_types=1);

use App\DTOs\Lead\ImportSettingsDto;
use App\Services\Lead\ValueNormalizerService;

beforeEach(function (): void {
    $this->normalizer = app(ValueNormalizerService::class);
    $this->settings = ImportSettingsDto::fromArray([]);
});

it('cleans the name shapes that appear in real exports', function (): void {
    expect($this->normalizer->normalizeName('Saini...Poonam'))->toBe('Saini Poonam')
        ->and($this->normalizer->normalizeName('saraswati'))->toBe('Saraswati')
        ->and($this->normalizer->normalizeName('priya meena'))->toBe('Priya Meena')
        ->and($this->normalizer->normalizeName('PRADHUMAN VASHISHTHA'))->toBe('Pradhuman Vashishtha');
});

it('leaves deliberately mixed-case names alone', function (): void {
    // Recasing "McDonald" to "Mcdonald" would be worse than leaving it.
    expect($this->normalizer->normalizeName('McDonald'))->toBe('McDonald')
        ->and($this->normalizer->normalizeName('Deshna Agrawal'))->toBe('Deshna Agrawal');
});

it('splits a name into first and last parts', function (): void {
    expect($this->normalizer->splitName('Saini...Poonam'))->toBe(['first' => 'Saini', 'last' => 'Poonam'])
        ->and($this->normalizer->splitName('saraswati'))->toBe(['first' => 'Saraswati', 'last' => null])
        ->and($this->normalizer->splitName('a b c'))->toBe(['first' => 'A', 'last' => 'B C']);
});

it('converts a Meta timestamp from its own offset into UTC', function (): void {
    // Meta stamps created_time in the ad account timezone (US Pacific here).
    expect($this->normalizer->normalizeDateTime('2026-08-05T00:58:53-07:00'))->toBe('2026-08-05 07:58:53')
        ->and($this->normalizer->normalizeDateTime('2026-08-05T04:46:40-07:00'))->toBe('2026-08-05 11:46:40');
});

it('interprets an offset-less value in the clinic timezone, not the server one', function (): void {
    $original = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    try {
        // Asia/Kolkata midnight is 18:30 UTC the previous day.
        expect($this->normalizer->normalizeDateTime('2026-08-05'))->toBe('2026-08-04 18:30:00');
    } finally {
        date_default_timezone_set($original);
    }
});

it('returns null rather than guessing at an unparseable date', function (): void {
    expect($this->normalizer->normalizeDateTime('garbage'))->toBeNull()
        ->and($this->normalizer->normalizeDateTime(''))->toBeNull();
});

it('reads the boolean spellings a spreadsheet produces', function (): void {
    // Meta writes the literal string "false" in is_organic.
    expect($this->normalizer->normalizeBoolean('false'))->toBeFalse()
        ->and($this->normalizer->normalizeBoolean('true'))->toBeTrue()
        ->and($this->normalizer->normalizeBoolean('yes'))->toBeTrue()
        ->and($this->normalizer->normalizeBoolean('0'))->toBeFalse()
        ->and($this->normalizer->normalizeBoolean('maybe'))->toBeNull();
});

it('drops an email that is not an address rather than failing the row', function (): void {
    expect($this->normalizer->normalizeEmail('  Priya@Example.COM '))->toBe('priya@example.com')
        ->and($this->normalizer->normalizeEmail('not-an-email'))->toBeNull()
        ->and($this->normalizer->normalizeEmail(''))->toBeNull();
});

it('humanises snake_cased answers while fixing AI and the pronoun I', function (): void {
    expect($this->normalizer->humanize('dullness_/_tanning'))->toBe('Dullness / tanning')
        ->and($this->normalizer->humanize('i_want_an_ai_skin_analysis_first'))->toBe('I want an AI skin analysis first')
        ->and($this->normalizer->humanize('yes,_i’m_comfortable'))->toBe('Yes, I’m comfortable');
});

it('splits pipe-separated multiple-choice answers', function (): void {
    // This exact value appears in New Leads Ad_Leads_2026-07-24_2026-08-05.csv.
    $parts = $this->normalizer->splitMultiValue(
        'i_want_an_ai_skin_analysis_first|dryness_/_sensitivity|open_pores_/_texture|acne_/_acne_marks|pigmentation_/_tanning|dullness_/_glow'
    );

    expect($parts)->toHaveCount(6)
        ->and($parts[0])->toBe('i_want_an_ai_skin_analysis_first');
});

it('treats a single answer as a single answer', function (): void {
    expect($this->normalizer->splitMultiValue('pigmentation'))->toBeNull();
});

it('builds a searchable form that keeps every choice', function (): void {
    $searchable = $this->normalizer->normalizeAnswerForSearch(
        'open_pores_/_texture|acne_/_acne_marks',
        $this->settings
    );

    expect($searchable)->toBe('Open pores / texture, Acne / acne marks');
});

it('preserves the rupee sign and en dash', function (): void {
    expect($this->normalizer->normalizeAnswerForSearch('express_ai_facial_–_₹3,800', $this->settings))
        ->toBe('Express AI facial – ₹3,800');
});
