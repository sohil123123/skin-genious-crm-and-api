<?php

declare(strict_types=1);

namespace App\Services\Lead;

/**
 * Removes the short type prefixes Meta stamps onto every identifier it exports.
 *
 * A real export row looks like this:
 *
 *   id          l:1356926202681733
 *   ad_id       ag:23859437033060797
 *   adset_id    as:23859437033070797
 *   campaign_id c:23859437033080797
 *   form_id     f:1488330259267953
 *   phone       p:+916367518162
 *
 * The prefix carries no information the column name does not already give, and
 * leaving it in place would break phone normalisation, duplicate matching and
 * every join back to the Meta Ads API. Stripping is its own step rather than
 * being folded into the phone parser because six different columns need it.
 */
class MetaPrefixStripper
{
    /**
     * Strip a known prefix from a single value.
     *
     * Only the documented prefixes are removed, and only when followed by a
     * plausible identifier. A free-text answer such as "c: section scar" keeps
     * its colon, and a stray "http://..." is never mistaken for a prefix.
     */
    public function strip(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || ! str_contains($value, ':')) {
            return $value;
        }

        $prefixes = (array) config('leads.meta_prefixes', ['l', 'ag', 'as', 'c', 'f', 'p']);
        $pattern = '/^(' . implode('|', array_map('preg_quote', $prefixes)) . '):(?=[+\d])/i';

        return (string) preg_replace($pattern, '', $value, 1);
    }

    /**
     * Strip prefixes across a whole row.
     *
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    public function stripRow(array $row): array
    {
        return array_map(fn ($value): string => $this->strip(is_string($value) ? $value : (string) $value), $row);
    }

    /**
     * Whether a value still carries a Meta prefix.
     */
    public function hasPrefix(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && $this->strip($value) !== $value;
    }
}
