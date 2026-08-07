<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\DTOs\Lead\PhoneNormalizationResult;

/**
 * Turns whatever Meta exported into one canonical phone number.
 *
 * The clean cases are the easy ones. What this class exists for is the dirt
 * that appears in every real export:
 *
 *   p:+916367518162            199 rows — the well-formed majority
 *   p:9887127755                18 rows — no country code
 *   p:+918269214285  5           3 rows — whitespace and a stray digit
 *   p:+9196362378507976709545    1 row  — two numbers typed into one field
 *   p:+9178510079097752          1 row  — 16 digits
 *   p:9685868                    1 row  — 7 digits, unrecoverable
 *
 * Rejecting everything malformed would throw away real, contactable leads that
 * a human can fix in seconds. So anything containing a recognisable subscriber
 * number is salvaged and flagged NeedsReview, and only the genuinely
 * unrecoverable values fail. The original string is always preserved in
 * phone_raw so no information is destroyed by the guess.
 */
class PhoneNormalizerService
{
    public function __construct(
        protected MetaPrefixStripper $prefixStripper,
    ) {}

    public function normalize(?string $raw): PhoneNormalizationResult
    {
        $original = (string) $raw;
        $value = trim($original);

        if ($value === '') {
            return PhoneNormalizationResult::invalid($original, 'The phone number is empty.');
        }

        $countryCode = (string) config('leads.phone.default_country_code', '91');
        $nationalLength = (int) config('leads.phone.national_number_length', 10);
        $pattern = (string) config('leads.phone.national_number_pattern', '/^[6-9]\d{9}$/');

        // "p:+916367518162" -> "+916367518162"
        $value = $this->prefixStripper->strip($value);

        // Keep only digits. The leading plus carries no information once the
        // country code is being resolved explicitly.
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return PhoneNormalizationResult::invalid($original, 'The phone number contains no digits.');
        }

        $hadNoise = $digits !== preg_replace('/\D+/', '', trim($original));

        // ─── Exactly the national number ────────────────────────────────
        if (strlen($digits) === $nationalLength) {
            if (preg_match($pattern, $digits) === 1) {
                return PhoneNormalizationResult::valid($this->format($countryCode, $digits), $original);
            }

            return PhoneNormalizationResult::invalid(
                $original,
                "\"{$digits}\" is {$nationalLength} digits but does not look like a valid mobile number."
            );
        }

        // ─── Country code plus the national number ──────────────────────
        if (strlen($digits) === strlen($countryCode) + $nationalLength && str_starts_with($digits, $countryCode)) {
            $national = substr($digits, strlen($countryCode));

            if (preg_match($pattern, $national) === 1) {
                return PhoneNormalizationResult::valid($this->format($countryCode, $national), $original);
            }
        }

        // ─── A single leading trunk zero ────────────────────────────────
        if (strlen($digits) === $nationalLength + 1 && str_starts_with($digits, '0')) {
            $national = substr($digits, 1);

            if (preg_match($pattern, $national) === 1) {
                return PhoneNormalizationResult::valid($this->format($countryCode, $national), $original);
            }
        }

        // ─── Too short to recover ───────────────────────────────────────
        if (strlen($digits) < $nationalLength) {
            return PhoneNormalizationResult::invalid(
                $original,
                sprintf('Only %d digits found; a valid number needs at least %d.', strlen($digits), $nationalLength)
            );
        }

        // ─── Too long: salvage the first plausible number ───────────────
        $maxSalvage = (int) config('leads.phone.max_salvage_digits', 30);

        if (strlen($digits) > $maxSalvage) {
            return PhoneNormalizationResult::invalid(
                $original,
                sprintf('%d digits found, which is too many to interpret as a phone number.', strlen($digits))
            );
        }

        $salvaged = $this->salvage($digits, $countryCode, $nationalLength, $pattern);

        if ($salvaged !== null) {
            return PhoneNormalizationResult::salvaged(
                $this->format($countryCode, $salvaged),
                $original,
                sprintf(
                    '%d digits found; kept the first valid %d-digit number ("%s"). Confirm this is the right one.',
                    strlen($digits),
                    $nationalLength,
                    $salvaged
                )
            );
        }

        return PhoneNormalizationResult::invalid(
            $original,
            sprintf('%d digits found but no valid %d-digit mobile number could be identified.', strlen($digits), $nationalLength)
        );
    }

    /**
     * Slide a window across the digits looking for the first valid subscriber
     * number, preferring one that begins right after the country code.
     *
     * "9196362378507976709545" is two numbers concatenated; the window finds
     * "6362378507" immediately after the leading "91" and stops there.
     */
    protected function salvage(string $digits, string $countryCode, int $nationalLength, string $pattern): ?string
    {
        if (str_starts_with($digits, $countryCode)) {
            $candidate = substr($digits, strlen($countryCode), $nationalLength);

            if (strlen($candidate) === $nationalLength && preg_match($pattern, $candidate) === 1) {
                return $candidate;
            }
        }

        $limit = strlen($digits) - $nationalLength;

        for ($offset = 0; $offset <= $limit; $offset++) {
            $candidate = substr($digits, $offset, $nationalLength);

            if (preg_match($pattern, $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    protected function format(string $countryCode, string $national): string
    {
        return sprintf((string) config('leads.phone.store_format', '+%s%s'), $countryCode, $national);
    }

    /**
     * Reduce a number to the digits used for duplicate matching.
     *
     * Comparing on the national number means "+919876543210", "919876543210"
     * and "9876543210" are recognised as the same person even when a historical
     * record was stored before normalisation existed.
     */
    public function matchKey(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $nationalLength = (int) config('leads.phone.national_number_length', 10);

        return strlen($digits) >= $nationalLength ? substr($digits, -$nationalLength) : $digits;
    }
}
