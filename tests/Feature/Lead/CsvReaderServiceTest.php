<?php

declare(strict_types=1);

use App\Services\Lead\CsvReaderService;

/**
 * These run against the real Meta exports in tests/Fixtures/leads.
 *
 * Synthetic fixtures would not catch what actually breaks here: the files are
 * UTF-16LE with a byte-order mark, tab-separated despite the .csv extension,
 * and contain currency symbols, en dashes and curly apostrophes.
 */
function fixturePath(string $name): string
{
    return base_path('tests/Fixtures/leads/' . $name);
}

function allFixtures(): array
{
    return glob(base_path('tests/Fixtures/leads/*.csv')) ?: [];
}

beforeEach(function (): void {
    $this->reader = app(CsvReaderService::class);
});

it('detects UTF-16LE with a BOM on every real export', function (): void {
    expect(allFixtures())->not->toBeEmpty();

    foreach (allFixtures() as $path) {
        $encoding = $this->reader->detectEncoding($path);

        expect($encoding['encoding'])->toBe('UTF-16LE', basename($path))
            ->and($encoding['has_bom'])->toBeTrue(basename($path));
    }
});

it('detects the tab delimiter despite the .csv extension', function (): void {
    foreach (allFixtures() as $path) {
        expect($this->reader->detectDelimiter($path, 'UTF-16LE'))->toBe("\t", basename($path));
    }
});

it('reads headers without a leading byte-order mark', function (): void {
    $headers = $this->reader->headers(
        fixturePath('New Leads Ad_Leads_2026-07-24_2026-08-05.csv'),
        'UTF-16LE',
        "\t"
    );

    // "id" would otherwise arrive as "\u{FEFF}id" and never match an alias.
    expect($headers[0])->toBe('id')
        ->and($headers)->toContain('full_name', 'phone', 'campaign_name', 'created_time')
        ->and($headers)->toHaveCount(18);
});

it('counts data rows excluding the header', function (): void {
    $counts = [
        'New Leads Ad_Leads_2026-07-24_2026-08-05.csv' => 51,
        'High conversion by concern_Leads_2026-07-16_2026-08-05.csv' => 44,
        'Video  Concern  Pores Blackheads_Leads_2026-07-10_2026-08-05.csv' => 1,
        'Static  Skin Report  Proof_Leads_2026-06-30_2026-08-05.csv' => 2,
    ];

    foreach ($counts as $file => $expected) {
        expect($this->reader->countRows(fixturePath($file), 'UTF-16LE', "\t"))->toBe($expected, $file);
    }
});

it('preserves unicode through transcoding', function (): void {
    $values = [];

    foreach ($this->reader->records(
        fixturePath('High conversion by concern_Leads_2026-07-16_2026-08-05.csv'),
        'UTF-16LE',
        "\t"
    ) as $row) {
        $values[$row['which_session_are_you_interested_in?']] = true;
    }

    // Rupee sign and en dash must survive the UTF-16 to UTF-8 conversion.
    expect(array_keys($values))->toContain('express_ai_facial_–_₹3,800');
});

it('windows rows by offset so chunks tile the file exactly', function (): void {
    $path = fixturePath('New Leads Ad_Leads_2026-07-24_2026-08-05.csv');

    $seen = [];

    for ($offset = 0; $offset < 51; $offset += 10) {
        foreach ($this->reader->records($path, 'UTF-16LE', "\t", $offset, 10) as $rowNumber => $row) {
            $seen[$rowNumber] = $row['id'];
        }
    }

    // Every row exactly once: no gaps between chunks, no rows counted twice.
    expect($seen)->toHaveCount(51)
        ->and(array_keys($seen))->toBe(range(1, 51))
        ->and(array_unique(array_values($seen)))->toHaveCount(51);
});

it('numbers rows from their true position in the file', function (): void {
    $rows = iterator_to_array($this->reader->records(
        fixturePath('New Leads Ad_Leads_2026-07-24_2026-08-05.csv'),
        'UTF-16LE',
        "\t",
        offset: 48,
        limit: 3,
    ));

    expect(array_keys($rows))->toBe([49, 50, 51]);
});

it('neutralises formulas without corrupting phone numbers', function (): void {
    // The narrow rule matters: every Meta phone number begins with a plus, so a
    // blanket leading-character rule would corrupt the most important column.
    expect($this->reader->sanitizeCell('+916367518162'))->toBe('+916367518162')
        ->and($this->reader->sanitizeCell('-500'))->toBe('-500')
        ->and($this->reader->sanitizeCell('=cmd|\'/c calc\'!A1'))->toBe('\'=cmd|\'/c calc\'!A1')
        ->and($this->reader->sanitizeCell('@SUM(A1)'))->toBe('\'@SUM(A1)')
        ->and($this->reader->sanitizeCell('normal text'))->toBe('normal text');
});

it('escapes every formula prefix on export', function (): void {
    // Exports use the strict rule, since the file is about to be opened in Excel.
    expect($this->reader->escapeForExport('+916367518162'))->toBe('\'+916367518162')
        ->and($this->reader->escapeForExport('=1+1'))->toBe('\'=1+1')
        ->and($this->reader->escapeForExport('Priya Meena'))->toBe('Priya Meena');
});

it('reports the same format for every fixture through inspect', function (): void {
    foreach (allFixtures() as $path) {
        $info = $this->reader->inspect($path);

        expect($info['encoding'])->toBe('UTF-16LE')
            ->and($info['delimiter'])->toBe("\t")
            ->and($info['headers'])->not->toBeEmpty()
            ->and($info['headers'])->toContain('phone', 'full_name');
    }
});
