<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\DTOs\Lead\ImportSettingsDto;
use App\Enums\CrmLeadField;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cleans individual values on their way from a CSV cell into the database.
 *
 * Every transformation here is driven by something present in real exports:
 * names arriving as "Saini...Poonam" or "saraswati", timestamps stamped in the
 * ad account's US Pacific timezone, booleans arriving as the string "false",
 * and answers snake_cased by Meta ("dullness_/_tanning").
 */
class ValueNormalizerService
{
    /**
     * Date formats tried in order before falling back to Carbon's parser.
     *
     * The ISO-8601 form with an offset is what Meta actually emits; the rest
     * cover hand-assembled sheets.
     */
    private const DATE_FORMATS = [
        'Y-m-d\TH:i:sP',
        'Y-m-d\TH:i:s.uP',
        'Y-m-d\TH:i:s\Z',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
        'm/d/Y H:i:s',
        'm/d/Y',
        'd-m-Y H:i:s',
        'd-m-Y',
        'd M Y',
        'd F Y',
        'M d, Y',
    ];

    public function __construct(
        protected PhoneNormalizerService $phoneNormalizer,
    ) {}

    /**
     * Apply the normalisation a given CRM field declares for itself.
     */
    public function normalizeForField(CrmLeadField $field, ?string $value, ImportSettingsDto $settings): mixed
    {
        $value = $settings->trimSpaces ? trim((string) $value) : (string) $value;

        if ($value === '') {
            return null;
        }

        return match ($field->normalizer()) {
            'phone' => $settings->normalizePhone
                ? $this->phoneNormalizer->normalize($value)->value
                : $value,
            'email' => $settings->normalizeEmail ? $this->normalizeEmail($value) : $value,
            'name' => $this->normalizeName($value),
            'datetime' => $settings->convertDateFormats ? $this->normalizeDateTime($value) : $value,
            'boolean' => $this->normalizeBoolean($value),
            'identifier' => $this->normalizeIdentifier($value),
            'text' => $this->normalizeText($value),
            default => $this->normalizeText($value),
        };
    }

    /**
     * Lowercase and trim an address, returning null when it is not an address.
     *
     * Invalid values are dropped rather than stored, because a lead with a
     * junk email is more useful than a lead that fails validation over one.
     */
    public function normalizeEmail(?string $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? null : $value;
    }

    /**
     * Tidy a personal name without destroying it.
     *
     * Real values include "Saini...Poonam", "saraswati" and "priya meena".
     * Runs of punctuation become a single space and casing is normalised, but
     * the name itself is never truncated or reordered.
     */
    public function normalizeName(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // "Saini...Poonam" and "priya_meena" both become two clean words.
        $value = preg_replace('/[._]{1,}/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        // Only recase when the input is uniformly cased. A name the person
        // typed as "McDonald" or "DeSouza" is left exactly as they wrote it.
        if (mb_strtolower($value) === $value || mb_strtoupper($value) === $value) {
            $value = mb_convert_case(mb_strtolower($value), MB_CASE_TITLE, 'UTF-8');
        }

        return $value;
    }

    /**
     * Split a display name into first and last parts.
     *
     * @return array{first: ?string, last: ?string}
     */
    public function splitName(?string $fullName): array
    {
        $fullName = $this->normalizeName($fullName);

        if ($fullName === null) {
            return ['first' => null, 'last' => null];
        }

        $parts = preg_split('/\s+/u', $fullName) ?: [];

        if (count($parts) === 1) {
            return ['first' => $parts[0], 'last' => null];
        }

        $first = array_shift($parts);

        return ['first' => $first, 'last' => implode(' ', $parts)];
    }

    /**
     * Parse a timestamp into UTC.
     *
     * Meta stamps created_time in the ad account's timezone with an explicit
     * offset ("2026-08-05T00:58:53-07:00"). Storing the offset-aware instant as
     * UTC is what lets the UI render it correctly in clinic local time.
     */
    public function normalizeDateTime(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // A value carrying its own UTC offset (which is what Meta exports) keeps
        // that offset. A value without one is interpreted in the clinic's
        // timezone rather than the server's php.ini default, so the same file
        // imports identically on a developer machine and in production.
        $fallbackZone = new DateTimeZone((string) app_timezone());

        foreach (self::DATE_FORMATS as $format) {
            // DateTimeImmutable rather than Carbon: Carbon runs in strict mode
            // in this application and throws on a non-matching format, which
            // would abort the loop on its first miss instead of trying the
            // remaining candidates.
            // The leading "!" zeroes every field the format does not specify,
            // so a date-only value becomes midnight instead of inheriting the
            // current clock time.
            $parsed = DateTimeImmutable::createFromFormat('!' . $format, $value, $fallbackZone);

            // Requiring the round trip to reproduce the input exactly rejects
            // partial matches, e.g. a date-only string against a format that
            // also expects a time.
            if ($parsed instanceof DateTimeImmutable && $parsed->format($format) === $value) {
                return CarbonImmutable::instance($parsed)->utc()->toDateTimeString();
            }
        }

        try {
            return CarbonImmutable::parse($value, $fallbackZone)->utc()->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Interpret the many ways a spreadsheet spells yes and no.
     *
     * Meta writes the literal strings "true" and "false" in is_organic.
     */
    public function normalizeBoolean(?string $value): ?bool
    {
        $value = Str::lower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        return match ($value) {
            'true', '1', 'yes', 'y', 'on' => true,
            'false', '0', 'no', 'n', 'off' => false,
            default => null,
        };
    }

    /**
     * Clean an external identifier down to a safe, storable token.
     */
    public function normalizeIdentifier(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^A-Za-z0-9_\-]/', '', $value) ?? $value;

        return $value === '' ? null : Str::limit($value, 64, '');
    }

    /**
     * Collapse whitespace in a free-text value.
     */
    public function normalizeText(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value === '' ? null : $value;
    }

    /**
     * Split a custom-field answer into its individual choices.
     *
     * Meta joins multiple selections with a pipe, so one cell can hold six
     * answers. Returns null for single answers so the caller can leave
     * value_json empty rather than storing a one-element array everywhere.
     *
     * @return array<int, string>|null
     */
    public function splitMultiValue(?string $value): ?array
    {
        $value = (string) $value;
        $separator = (string) config('leads.csv.multi_value_separator', '|');

        if ($value === '' || ! str_contains($value, $separator)) {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode($separator, $value)),
            fn (string $part): bool => $part !== ''
        ));

        return count($parts) > 1 ? $parts : null;
    }

    /**
     * Turn a raw answer into its readable form.
     *
     * "dullness_/_tanning" reads as "Dullness / Tanning" in the UI while the
     * untouched original stays in the value column so exact matching, filters
     * and re-exports all keep working.
     */
    public function humanize(?string $value): string
    {
        $value = trim(str_replace('_', ' ', (string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if ($value === '') {
            return '';
        }

        $value = Str::ucfirst($value);

        // Sentence case leaves "ai facial" and the pronoun "i" looking wrong,
        // and these appear in almost every answer this clinic's forms collect.
        return (string) preg_replace_callback(
            '/\b(ai|i)\b/u',
            fn (array $matches): string => mb_strtoupper($matches[1]),
            $value
        );
    }

    /**
     * Build the searchable form of a custom-field answer.
     *
     * Multi-answer cells are joined back together so a single indexed column
     * can still match any one of the choices with a LIKE.
     */
    public function normalizeAnswerForSearch(?string $value, ImportSettingsDto $settings): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $parts = $this->splitMultiValue($value);

        if ($parts !== null) {
            $value = implode(', ', array_map(
                fn (string $part): string => $settings->humanizeAnswers ? $this->humanize($part) : $part,
                $parts
            ));
        } elseif ($settings->humanizeAnswers) {
            $value = $this->humanize($value);
        }

        // The column is indexed, so it is deliberately narrow; the untruncated
        // text always remains available in the value column.
        return Str::limit($value, 250, '');
    }
}
