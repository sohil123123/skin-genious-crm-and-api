<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Throwable;

/**
 * One lead's answer to one dynamic question.
 */
class LeadFieldValue extends Model
{
    protected $fillable = [
        'lead_id',
        'lead_custom_field_id',
        'value',
        'value_json',
        'value_normalized',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
        ];
    }

    /**
     * The shapes an answer must match before it is read as a date at all.
     *
     * Deliberately strict: a budget of "5999" or a year "2026" handed to a
     * loose parser comes back as a valid date, which would let the action
     * queue score a lead on a number that has nothing to do with a visit.
     */
    private const ISO_DATE_ONLY = '/^\d{4}-\d{2}-\d{2}$/';

    private const ISO_DATE_TIME = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/';

    private const WRITTEN_DATE_TIME = '/^(?<month>[A-Za-z]{3,9})\s+(?<day>\d{1,2}),\s*(?<year>\d{4})'
        . '\s+at\s+(?<hour>\d{1,2}):(?<minute>\d{2})(?::(?<second>\d{2}))?'
        . '\s*(?<meridiem>AM|PM)?'
        . '\s*(?<zone>GMT\s*[+-]\s*\d{1,2}:?\d{2}|UTC|[A-Za-z]{2,5})?$/i';

    /**
     * Read a scheduling answer as a moment in the clinic's timezone.
     *
     * Public because the lead action engine scores leads on the visit date they
     * asked for, and it must read that answer exactly the way the lead detail
     * screen displays it. A second parser living in the service would drift
     * from this one, and the queue would start disagreeing with the card it is
     * built from.
     */
    public static function parseAnswerDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return static::parseIsoAnswer($value) ?? static::parseWrittenAnswer($value);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function customField(): BelongsTo
    {
        return $this->belongsTo(LeadCustomField::class, 'lead_custom_field_id');
    }

    /**
     * Every answer as a list, so single and multi-answer questions render the
     * same way in the UI.
     *
     * @return array<int, string>
     */
    public function getDisplayValuesAttribute(): array
    {
        if (is_array($this->value_json) && $this->value_json !== []) {
            return array_map(
                fn ($value): string => static::presentAnswer((string) $value)
                    ?? LeadCustomField::humanizeValue((string) $value),
                $this->value_json
            );
        }

        // Detection runs against the raw value rather than the normalised one,
        // because the normalised column is truncated for indexing and a long
        // timestamp could arrive here already clipped.
        $presented = static::presentAnswer((string) $this->value);

        if ($presented !== null) {
            return [$presented];
        }

        $normalized = (string) ($this->value_normalized ?? $this->value);

        return $normalized === '' ? [] : [$normalized];
    }

    /**
     * Render an answer that is really a date or timestamp in clinic-local form.
     *
     * Meta does not settle on one shape for a scheduling answer. The same
     * question can arrive as any of:
     *
     *   2026-08-23T14:00:00+0530
     *   Aug 23, 2026 at 2:00 PM IST
     *   Aug 12, 2026 at 2:05 PM GMT+5:30
     *
     * All three are the same kind of value and must read the same way on the
     * lead. Only the display is localised: the value column keeps the exact
     * original so filtering, matching and re-export are unaffected.
     *
     * Returns null when the answer is not a date, which is the common case, so
     * the caller falls back to its normal handling.
     */
    protected static function presentAnswer(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return static::presentIsoAnswer($value) ?? static::presentWrittenAnswer($value);
    }

    /**
     * The machine shape: 2026-08-23T14:00:00+0530, or a bare date.
     */
    protected static function presentIsoAnswer(string $value): ?string
    {
        $parsed = static::parseIsoAnswer($value);

        if ($parsed === null) {
            return null;
        }

        // A date with no time of day must not gain a misleading "12:00 AM".
        return preg_match(self::ISO_DATE_ONLY, $value)
            ? $parsed->format('d M Y')
            : $parsed->format(app_datetime_format());
    }

    /**
     * The machine shape, as a moment rather than a formatted string.
     */
    protected static function parseIsoAnswer(string $value): ?Carbon
    {
        // Deliberately strict. A loose parse would turn "5999" into a year and
        // a budget answer would silently become a date.
        $isDateOnly = (bool) preg_match(self::ISO_DATE_ONLY, $value);

        if (! $isDateOnly && ! preg_match(self::ISO_DATE_TIME, $value)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        // Shifting a bare date between timezones could move it a day either
        // way, so it is pinned to midnight in the clinic's own zone instead.
        return $isDateOnly
            ? Carbon::parse($parsed->format('Y-m-d'), app_timezone())
            : $parsed->timezone(app_timezone());
    }

    /**
     * The written shape: "Aug 23, 2026 at 2:00 PM IST".
     *
     * Parsed from its parts rather than handed to a loose parser, so a stray
     * answer that merely opens with a month name cannot be mistaken for a date.
     */
    protected static function presentWrittenAnswer(string $value): ?string
    {
        return static::parseWrittenAnswer($value)?->format(app_datetime_format());
    }

    /**
     * The written shape, as a moment rather than a formatted string.
     */
    protected static function parseWrittenAnswer(string $value): ?Carbon
    {
        if (! preg_match(self::WRITTEN_DATE_TIME, $value, $m)) {
            return null;
        }

        // An optional group that did not participate comes back as an empty
        // string rather than absent, so ?? never fires — and "2:00:" is not a
        // time Carbon will accept.
        $second = ($m['second'] ?? '') !== '' ? $m['second'] : '00';

        $stamp = sprintf(
            '%s %s %s %s:%s:%s %s',
            $m['month'],
            $m['day'],
            $m['year'],
            $m['hour'],
            $m['minute'],
            $second,
            $m['meridiem'] ?? '',
        );

        try {
            $parsed = Carbon::parse(trim($stamp), static::resolveZone($m['zone'] ?? null));
        } catch (Throwable) {
            return null;
        }

        return $parsed->timezone(app_timezone());
    }

    /**
     * Work out which timezone a written answer was expressed in.
     *
     * The clinic's own timezone wins whenever the abbreviation matches it,
     * because abbreviations are not unique — PHP reads "IST" as Israel Standard
     * Time, which would drag every Indian appointment back by three and a half
     * hours.
     */
    protected static function resolveZone(?string $zone): DateTimeZone
    {
        $appZone = new DateTimeZone(app_timezone());
        $zone = trim((string) $zone);

        if ($zone === '') {
            return $appZone;
        }

        // "GMT+5:30" is an offset wearing a name.
        if (preg_match('/^GMT\s*(?<sign>[+-])\s*(?<hours>\d{1,2}):?(?<minutes>\d{2})$/i', $zone, $m)) {
            return new DateTimeZone(sprintf('%s%02d:%s', $m['sign'], (int) $m['hours'], $m['minutes']));
        }

        if (strcasecmp($zone, 'UTC') === 0 || strcasecmp($zone, 'GMT') === 0) {
            return new DateTimeZone('UTC');
        }

        // Prefer the clinic's zone when the abbreviation is its own.
        if (strcasecmp($zone, Carbon::now($appZone)->format('T')) === 0) {
            return $appZone;
        }

        $resolved = timezone_name_from_abbr(strtolower($zone));

        try {
            return $resolved === false ? $appZone : new DateTimeZone($resolved);
        } catch (Throwable) {
            return $appZone;
        }
    }
}
