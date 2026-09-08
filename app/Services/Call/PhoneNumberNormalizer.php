<?php

declare(strict_types=1);

namespace App\Services\Call;

use App\DTOs\Call\PhoneNumber;
use App\Services\Lead\PhoneNormalizerService;

/**
 * The one place a call's phone numbers are interpreted.
 *
 * Deliberately more permissive than the lead importer. A lead's number must
 * look like a reachable Indian mobile or the lead is worthless, so
 * PhoneNormalizerService rejects anything that fails that test. A call leg is
 * different: the number may be an Exophone, a landline, a toll-free line or an
 * international caller, and refusing to normalise those would silently drop
 * real conversations from the CRM.
 *
 * What it does *not* do differently is decide who two numbers belong to. The
 * match key is delegated to the lead importer's service, so "the same person"
 * means exactly the same thing on a call as it does on an imported lead. Two
 * definitions of identity in one CRM is how a patient ends up with two
 * histories.
 */
class PhoneNumberNormalizer
{
    public function __construct(
        protected PhoneNormalizerService $leadNormalizer,
    ) {}

    /**
     * Interpret a raw number, optionally with a country code the provider sent
     * separately — Callyzer supplies emp_country_code and client_country_code
     * as their own fields rather than as part of the number.
     */
    public function normalize(?string $raw, ?string $countryCode = null): PhoneNumber
    {
        $original = $raw !== null ? trim($raw) : null;

        if (blank($original)) {
            return PhoneNumber::empty();
        }

        $digits = $this->digitsOnly($original);

        if ($digits === '') {
            // A number with no digits at all is still worth keeping: "anonymous"
            // and "private" arrive in this field from real providers, and that
            // is a fact about the call rather than an error.
            return new PhoneNumber($original, null, null, null);
        }

        $defaultCode = (string) config('calls.phone.default_country_code', '91');
        $suppliedCode = $this->digitsOnly((string) $countryCode);

        $nationalLength = (int) config('calls.phone.national_number_length', 10);

        // ─── Strip whatever prefix convention this number arrived with ───
        // Order matters: the international prefix is removed before the trunk
        // zero, because "00919876543210" carries both.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        $code = null;

        if ($suppliedCode !== '' && ! str_starts_with($digits, $suppliedCode)) {
            // The provider gave the country code as a separate field and the
            // number itself is national.
            $code = $suppliedCode;
            $national = ltrim($digits, '0');
        } elseif ($suppliedCode !== '' && str_starts_with($digits, $suppliedCode) && strlen($digits) > strlen($suppliedCode)) {
            $code = $suppliedCode;
            $national = substr($digits, strlen($suppliedCode));
        } elseif (str_starts_with($digits, $defaultCode) && strlen($digits) === strlen($defaultCode) + $nationalLength) {
            $code = $defaultCode;
            $national = substr($digits, strlen($defaultCode));
        } elseif (strlen($digits) === $nationalLength + 1 && str_starts_with($digits, '0')) {
            // A national number carrying a trunk zero, e.g. an Exophone written
            // as 08047122334.
            $code = $defaultCode;
            $national = substr($digits, 1);
        } elseif (strlen($digits) === $nationalLength) {
            $code = $defaultCode;
            $national = $digits;
        } else {
            // Anything else — a short code, an international number, a caller
            // ID this application has no rules for. Keep the digits as they
            // are rather than guessing at a country.
            $national = $digits;
        }

        $normalized = $code !== null && $national !== ''
            ? sprintf((string) config('calls.phone.store_format', '+%s%s'), $code, $national)
            : '+' . $digits;

        return new PhoneNumber(
            original: $original,
            normalized: $normalized,
            key: $this->matchKey($digits),
            countryCode: $code,
        );
    }

    /**
     * The digits two numbers are compared on.
     *
     * Delegated to the lead importer so that a call, an imported lead and a
     * patient record all agree on what makes two numbers the same person.
     * Short numbers — IVR short codes, internal extensions — are returned whole
     * rather than padded, so they can never accidentally match a subscriber
     * number ending in the same digits.
     */
    public function matchKey(?string $value): ?string
    {
        $digits = $this->digitsOnly((string) $value);

        if ($digits === '') {
            return null;
        }

        $length = (int) config('calls.phone.match_key_length', 10);

        return strlen($digits) >= $length
            ? $this->leadNormalizer->matchKey($digits)
            : $digits;
    }

    /**
     * Whether a number is long enough to identify a person.
     *
     * A four-digit short code matches thousands of people and must never be
     * used to attach a call to a patient.
     */
    public function isMatchable(?string $key): bool
    {
        return filled($key)
            && strlen((string) $key) >= (int) config('calls.phone.match_key_length', 10);
    }

    protected function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
