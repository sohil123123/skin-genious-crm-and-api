<?php

declare(strict_types=1);

use App\Enums\PhoneStatus;
use App\Services\Lead\CsvReaderService;
use App\Services\Lead\PhoneNormalizerService;

beforeEach(function (): void {
    $this->phone = app(PhoneNormalizerService::class);
});

it('strips the Meta p: prefix and normalises the clean majority', function (): void {
    $result = $this->phone->normalize('p:+916367518162');

    expect($result->value)->toBe('+916367518162')
        ->and($result->status)->toBe(PhoneStatus::Valid)
        ->and($result->raw)->toBe('p:+916367518162');
});

it('adds the country code to a bare national number', function (): void {
    expect($this->phone->normalize('p:9887127755')->value)->toBe('+919887127755');
});

it('drops a leading trunk zero', function (): void {
    expect($this->phone->normalize('09887127755')->value)->toBe('+919887127755');
});

it('ignores whitespace inside the number', function (): void {
    expect($this->phone->normalize('p:+91 98871 27755')->value)->toBe('+919887127755');
});

it('salvages two numbers typed into one field', function (): void {
    // This exact value appears in New Leads Ad_Leads_2026-07-24_2026-08-05.csv.
    $result = $this->phone->normalize('p:+9196362378507976709545');

    expect($result->value)->toBe('+919636237850')
        ->and($result->status)->toBe(PhoneStatus::NeedsReview)
        ->and($result->reason)->toContain('22 digits')
        ->and($result->isUsable())->toBeTrue();
});

it('salvages a number with trailing junk', function (): void {
    $result = $this->phone->normalize('p:+918269214285  5');

    expect($result->value)->toBe('+918269214285')
        ->and($result->status)->toBe(PhoneStatus::NeedsReview);
});

it('salvages a doubled plus sign', function (): void {
    $result = $this->phone->normalize('p:+91+96891747393');

    expect($result->value)->toBe('+919689174739')
        ->and($result->status)->toBe(PhoneStatus::NeedsReview);
});

it('rejects a number that is too short to recover', function (): void {
    $result = $this->phone->normalize('p:9685868');

    expect($result->value)->toBeNull()
        ->and($result->status)->toBe(PhoneStatus::Invalid)
        ->and($result->reason)->toContain('Only 7 digits')
        ->and($result->isUsable())->toBeFalse();
});

it('rejects a ten digit number that cannot be a mobile', function (): void {
    // Indian mobile numbers start 6-9; 5555555555 is the right length but wrong shape.
    expect($this->phone->normalize('5555555555')->status)->toBe(PhoneStatus::Invalid);
});

it('rejects an empty value', function (): void {
    expect($this->phone->normalize('')->status)->toBe(PhoneStatus::Invalid)
        ->and($this->phone->normalize(null)->status)->toBe(PhoneStatus::Invalid);
});

it('builds a match key that ignores formatting differences', function (): void {
    // Patient records predate normalisation, so matching happens on the last
    // national digits rather than the full string.
    expect($this->phone->matchKey('+919887127755'))->toBe('9887127755')
        ->and($this->phone->matchKey('919887127755'))->toBe('9887127755')
        ->and($this->phone->matchKey('9887127755'))->toBe('9887127755')
        ->and($this->phone->matchKey('098871 27755'))->toBe('9887127755');
});

it('produces the expected outcome distribution across all real exports', function (): void {
    $reader = app(CsvReaderService::class);
    $counts = [PhoneStatus::Valid->value => 0, PhoneStatus::NeedsReview->value => 0, PhoneStatus::Invalid->value => 0];

    foreach (glob(base_path('tests/Fixtures/leads/*.csv')) ?: [] as $path) {
        foreach ($reader->records($path, 'UTF-16LE', "\t") as $row) {
            $counts[$this->phone->normalize($row['phone'] ?? null)->status->value]++;
        }
    }

    // 223 rows in total: only one number in the whole corpus is unrecoverable,
    // which is the point of salvaging rather than rejecting.
    expect(array_sum($counts))->toBe(223)
        ->and($counts[PhoneStatus::Valid->value])->toBe(217)
        ->and($counts[PhoneStatus::NeedsReview->value])->toBe(5)
        ->and($counts[PhoneStatus::Invalid->value])->toBe(1);
});
