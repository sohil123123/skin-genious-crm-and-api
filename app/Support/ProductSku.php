<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * SKU built from the product name: initials of the name's words, the type letter and the
 * product id — "derma decode hair growth serum" (product #6) => DDHGS-P-0006.
 *
 * The id makes it unique, so two products with the same initials never clash. It is
 * assigned once (when the product is created) and does not change on rename.
 */
class ProductSku
{
    private const TYPE_LETTER = ['product' => 'P', 'service' => 'S', 'iv_product' => 'I'];

    // Words that carry no meaning in a code ("A (Peel)" from the old CRM, "and", ...).
    private const SKIP = ['a', 'an', 'the', 'and', 'of', 'for', 'with', 'in', 'on', 'to', 'by'];

    private const MAX_INITIALS = 6;

    public static function make(?string $name, ?string $type, int $id): string
    {
        return sprintf('%s-%s-%04d', self::initials($name), self::TYPE_LETTER[$type] ?? 'X', $id);
    }

    /** True for SKUs this class produced (used to skip products already converted). */
    public static function isGenerated(?string $sku, int $id): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{1,' . self::MAX_INITIALS . '}-[A-Z]-0*' . $id . '$/', (string) $sku);
    }

    private static function initials(?string $name): string
    {
        $name = (string) $name;
        // old CRM marks deleted products in the name: "PDRN (deleted at 2025-09-17 18:28:45)"
        $name = preg_replace('/\(\s*deleted at[^)]*\)/i', ' ', $name);
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::ascii($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = '';
        foreach ($words as $word) {
            if (in_array(strtolower($word), self::SKIP, true)) {
                continue;
            }
            // numbers stay whole ("10 Sessions" -> 10S, "30ml" -> 30), words give their initial
            $letters .= preg_match('/^\d+/', $word, $m) ? $m[0] : strtoupper($word[0]);
        }

        return substr($letters, 0, self::MAX_INITIALS) ?: 'X';
    }
}
