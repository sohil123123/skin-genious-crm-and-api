<?php

declare(strict_types=1);

namespace App\Services\Lead;

use Generator;
use League\Csv\Reader;
use League\Csv\Statement;
use RuntimeException;

/**
 * Reads lead CSV files, whatever shape they actually arrive in.
 *
 * Meta's "CSV" exports are UTF-16LE with a byte-order mark and are separated by
 * tabs, not commas. Handing such a file to fgetcsv or to league/csv unprepared
 * yields one column of NUL-riddled binary, so encoding and delimiter are both
 * detected up front and the file is transcoded through a stream filter as it is
 * read. Nothing is ever loaded into memory whole: callers get a generator, and
 * chunk jobs seek to their own offset via Statement.
 */
class CsvReaderService
{
    /**
     * Byte-order marks in descending length order, so the 4-byte UTF-32 marks
     * are tested before the 2-byte UTF-16 marks they would otherwise shadow.
     */
    private const BOMS = [
        "\x00\x00\xFE\xFF" => 'UTF-32BE',
        "\xFF\xFE\x00\x00" => 'UTF-32LE',
        "\xEF\xBB\xBF" => 'UTF-8',
        "\xFE\xFF" => 'UTF-16BE',
        "\xFF\xFE" => 'UTF-16LE',
    ];

    /**
     * Inspect a file's format without importing anything.
     *
     * @return array{encoding: string, has_bom: bool, delimiter: string, enclosure: string, headers: array<int, string>}
     */
    public function inspect(string $absolutePath): array
    {
        $this->assertReadable($absolutePath);

        ['encoding' => $encoding, 'has_bom' => $hasBom] = $this->detectEncoding($absolutePath);
        $delimiter = $this->detectDelimiter($absolutePath, $encoding);

        return [
            'encoding' => $encoding,
            'has_bom' => $hasBom,
            'delimiter' => $delimiter,
            'enclosure' => (string) config('leads.csv.enclosure', '"'),
            'headers' => $this->headers($absolutePath, $encoding, $delimiter),
        ];
    }

    /**
     * Determine the file's character encoding.
     *
     * A byte-order mark is authoritative when present. Failing that, a UTF-16
     * file is still recognisable by its interleaved NUL bytes — ASCII text
     * encoded as UTF-16LE is "i\0d\0", which no UTF-8 document would contain.
     *
     * @return array{encoding: string, has_bom: bool}
     */
    public function detectEncoding(string $absolutePath): array
    {
        $handle = $this->open($absolutePath);
        $head = (string) fread($handle, 4096);
        fclose($handle);

        foreach (self::BOMS as $bom => $encoding) {
            if (str_starts_with($head, $bom)) {
                return ['encoding' => $encoding, 'has_bom' => true];
            }
        }

        if ($head === '') {
            return ['encoding' => 'UTF-8', 'has_bom' => false];
        }

        $sample = substr($head, 0, 512);
        $nulCount = substr_count($sample, "\x00");

        if ($nulCount > strlen($sample) * 0.25) {
            // Odd byte offsets holding the NULs means the low byte comes first.
            $evenNuls = 0;
            $oddNuls = 0;

            for ($i = 0, $length = strlen($sample); $i < $length; $i++) {
                if ($sample[$i] === "\x00") {
                    $i % 2 === 0 ? $evenNuls++ : $oddNuls++;
                }
            }

            return [
                'encoding' => $oddNuls >= $evenNuls ? 'UTF-16LE' : 'UTF-16BE',
                'has_bom' => false,
            ];
        }

        if (! mb_check_encoding($head, 'UTF-8')) {
            return ['encoding' => 'Windows-1252', 'has_bom' => false];
        }

        return ['encoding' => 'UTF-8', 'has_bom' => false];
    }

    /**
     * Score each candidate delimiter against the header line and pick a winner.
     *
     * Scoring the header rather than the whole file keeps this cheap and avoids
     * being misled by commas inside quoted answers, which Meta's questions are
     * full of ("not_sure,_please_recommend").
     */
    public function detectDelimiter(string $absolutePath, string $encoding): string
    {
        $line = $this->firstLine($absolutePath, $encoding);

        if ($line === '') {
            return (string) config('leads.csv.default_delimiter', ',');
        }

        $candidates = (array) config('leads.csv.delimiters', ["\t", ',', ';', '|']);
        $enclosure = (string) config('leads.csv.enclosure', '"');

        $best = (string) config('leads.csv.default_delimiter', ',');
        $bestScore = 0;

        foreach ($candidates as $candidate) {
            $fields = str_getcsv($line, $candidate, $enclosure, '');
            $score = count($fields);

            // A delimiter that does not actually appear splits the line into a
            // single field, which is never a real header row.
            if ($score < 2) {
                continue;
            }

            // Prefer the delimiter yielding the most non-empty columns, so a
            // stray character that happens to appear once cannot outrank a tab
            // that appears seventeen times.
            $populated = count(array_filter($fields, fn ($field): bool => trim((string) $field) !== ''));

            if ($populated > $bestScore) {
                $bestScore = $populated;
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Read the header row, cleaned of byte-order marks and blank columns.
     *
     * @return array<int, string>
     */
    public function headers(string $absolutePath, string $encoding, string $delimiter): array
    {
        $reader = $this->reader($absolutePath, $encoding, $delimiter);

        $header = $reader->fetchOne(0);

        if (! is_array($header)) {
            return [];
        }

        $headers = [];

        foreach ($header as $index => $value) {
            $value = $this->stripBomCharacters((string) $value);
            $value = trim($value);

            // A trailing empty column is an artefact of a line ending with the
            // delimiter and must not become a mappable field.
            if ($value === '') {
                continue;
            }

            $headers[$index] = $value;
        }

        return array_values($headers);
    }

    /**
     * Stream data rows as associative arrays keyed by header.
     *
     * @param  int  $offset  Zero-based data row offset, excluding the header.
     * @return Generator<int, array<string, string>>  Keyed by 1-based row number.
     */
    public function records(
        string $absolutePath,
        string $encoding,
        string $delimiter,
        int $offset = 0,
        ?int $limit = null,
        bool $sanitize = true,
    ): Generator {
        $reader = $this->reader($absolutePath, $encoding, $delimiter);
        $reader->setHeaderOffset(0);

        $statement = Statement::create()->offset($offset);

        if ($limit !== null && $limit > 0) {
            $statement = $statement->limit($limit);
        }

        $header = $this->normalizeHeaderRow($reader->getHeader());

        $rowNumber = $offset;

        foreach ($statement->process($reader, $header) as $record) {
            $rowNumber++;

            $row = [];

            foreach ($record as $key => $value) {
                $key = $this->stripBomCharacters((string) $key);

                if ($key === '') {
                    continue;
                }

                $value = (string) ($value ?? '');
                $row[$key] = $sanitize ? $this->sanitizeCell($value) : $value;
            }

            yield $rowNumber => $row;
        }
    }

    /**
     * Count data rows without materialising them.
     */
    public function countRows(string $absolutePath, string $encoding, string $delimiter): int
    {
        $reader = $this->reader($absolutePath, $encoding, $delimiter);
        $reader->setHeaderOffset(0);

        $count = 0;

        foreach ($reader->getRecords() as $ignored) {
            $count++;
        }

        return $count;
    }

    /**
     * Neutralise a cell that would execute as a formula in a spreadsheet.
     *
     * The check is deliberately narrow. Every Meta phone number begins with a
     * plus sign and every negative number begins with a minus, so a blanket
     * rule on the leading character would corrupt the most important column in
     * the file. A value is only treated as a formula when the leading character
     * is followed by something that cannot begin a number.
     */
    public function sanitizeCell(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $first = $value[0];

        if ($first === '=' || $first === '@') {
            return "'" . $value;
        }

        if (($first === '+' || $first === '-') && preg_match('/^[+\-]\s*[^\d\s]/', $value) === 1) {
            return "'" . $value;
        }

        if ($first === "\t" || $first === "\r") {
            return ltrim($value, "\t\r");
        }

        return $value;
    }

    /**
     * Escape a cell on the way out to a downloadable CSV.
     *
     * Unlike reading, the export rule is the strict OWASP one: anything with a
     * leading formula character is prefixed, because the file is about to be
     * opened in Excel where a phone number rendered as "'+919876543210" is a
     * cosmetic annoyance and a live formula is a security incident.
     */
    public function escapeForExport(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        $prefixes = (array) config('leads.csv.formula_injection_prefixes', ['=', '+', '-', '@', "\t", "\r"]);

        return in_array($value[0], $prefixes, true) ? "'" . $value : $value;
    }

    /**
     * Build a configured reader with transcoding already attached.
     */
    protected function reader(string $absolutePath, string $encoding, string $delimiter): Reader
    {
        $this->assertReadable($absolutePath);

        $reader = Reader::createFromPath($absolutePath, 'r');
        $reader->setDelimiter($delimiter);
        $reader->setEnclosure((string) config('leads.csv.enclosure', '"'));
        $reader->setEscape((string) config('leads.csv.escape', ''));

        $this->attachTranscoder($reader, $encoding);

        // After transcoding, any original mark has become a UTF-8 one.
        $reader->skipInputBOM();

        return $reader;
    }

    /**
     * Convert the stream to UTF-8 as it is read.
     *
     * This is what makes a 100k-row UTF-16 file cost the same memory as a 10-row
     * one: the conversion happens in the stream, never in a variable.
     */
    protected function attachTranscoder(Reader $reader, string $encoding): void
    {
        $encoding = strtoupper($encoding);

        if ($encoding === 'UTF-8' || $encoding === '') {
            return;
        }

        if (! $reader->supportsStreamFilterOnRead()) {
            throw new RuntimeException("The CSV stream cannot be transcoded from {$encoding}.");
        }

        $reader->appendStreamFilterOnRead("convert.iconv.{$encoding}/UTF-8");
    }

    /**
     * Read and decode the first physical line of the file.
     */
    protected function firstLine(string $absolutePath, string $encoding): string
    {
        $handle = $this->open($absolutePath);

        // UTF-16 uses two bytes per character, so a fixed byte budget must be
        // generous enough to still contain a full header line. The longest
        // header seen in production is 157 characters inside an 18-column row.
        $chunk = (string) fread($handle, 65536);
        fclose($handle);

        if ($chunk === '') {
            return '';
        }

        $decoded = $this->decode($chunk, $encoding);
        $decoded = $this->stripBomCharacters($decoded);

        $breakPosition = strcspn($decoded, "\r\n");

        return substr($decoded, 0, $breakPosition);
    }

    /**
     * Decode a raw byte string into UTF-8.
     */
    protected function decode(string $value, string $encoding): string
    {
        $encoding = strtoupper($encoding);

        if ($encoding === 'UTF-8' || $encoding === '') {
            return $value;
        }

        // Truncating a fixed byte count can slice a multi-byte character in
        // half, so the tail is trimmed to a whole number of code units before
        // conversion for the fixed-width UTF-16 and UTF-32 encodings.
        $unitSize = match (true) {
            str_starts_with($encoding, 'UTF-32') => 4,
            str_starts_with($encoding, 'UTF-16') => 2,
            default => 1,
        };

        if ($unitSize > 1) {
            $value = substr($value, 0, intdiv(strlen($value), $unitSize) * $unitSize);
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);

        return $converted === false ? $value : $converted;
    }

    /**
     * Remove byte-order marks left anywhere in a decoded string.
     *
     * iconv turns a UTF-16 mark into the UTF-8 byte sequence rather than
     * dropping it, so the first header would otherwise be "\u{FEFF}id".
     */
    protected function stripBomCharacters(string $value): string
    {
        return str_replace(["\xEF\xBB\xBF", "\u{FEFF}"], '', $value);
    }

    /**
     * Clean a raw header row so duplicate and blank columns cannot break the
     * associative mapping league/csv builds from it.
     *
     * @param  array<int, string>  $header
     * @return array<int, string>
     */
    protected function normalizeHeaderRow(array $header): array
    {
        $seen = [];
        $normalized = [];

        foreach ($header as $index => $value) {
            $value = trim($this->stripBomCharacters((string) $value));

            if ($value === '') {
                // league/csv requires a non-empty, unique key for every column,
                // so unnamed columns get a positional placeholder that the
                // mapper will later ignore.
                $value = '_column_' . ($index + 1);
            }

            if (isset($seen[$value])) {
                $seen[$value]++;
                $value .= '_' . $seen[$value];
            } else {
                $seen[$value] = 1;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @return resource
     */
    protected function open(string $absolutePath)
    {
        $this->assertReadable($absolutePath);

        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open the CSV file at {$absolutePath}.");
        }

        return $handle;
    }

    protected function assertReadable(string $absolutePath): void
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException("The CSV file at {$absolutePath} is missing or unreadable.");
        }
    }
}
