<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use Illuminate\Support\HtmlString;

/**
 * A stat description drawn as badges rather than a run-on sentence.
 *
 * "5 today · 4 yesterday · 62 this month" is three separate facts printed as one
 * line, and the eye has to parse the separators to find any of them. As badges
 * each figure is its own object and the important one can carry a colour.
 *
 * Shared so the Leads and Calls overviews stay visually identical: staff read
 * both as the same kind of card, and two implementations would drift the first
 * time either was tuned.
 */
trait RendersStatBadges
{
    /**
     * The icon a period badge carries, keyed by the word it uses.
     *
     * Filled in automatically so "today" looks the same on the Leads overview
     * and the Calls one — the alternative is every call site repeating the same
     * string, which is how two screens end up disagreeing about what a calendar
     * means.
     *
     * Chosen to be told apart at badge size, which rules out the obvious set:
     * heroicon-m-calendar and heroicon-m-calendar-days are near-identical at
     * twelve pixels, so "today" takes a sun instead and "yesterday" an arrow
     * pointing back. Three distinct silhouettes, readable without squinting.
     */
    protected const PERIOD_ICONS = [
        'today' => 'heroicon-m-sun',
        'yesterday' => 'heroicon-m-arrow-uturn-left',
        'this month' => 'heroicon-m-calendar-days',
    ];

    /**
     * Returned as an HtmlString because Filament prints the description with
     * {{ }}, which escapes a plain string and leaves an Htmlable alone. This is
     * the supported way to put markup there without replacing the widget view;
     * returning a string would print the literal tags on screen.
     *
     * An item may set its own `icon` — including null, to have none. Only the
     * period words above get one by default; anything else is left bare rather
     * than guessed at.
     *
     * @param  array<int, array{label: string, value: string, color?: string, icon?: ?string}>  $items
     */
    protected function badges(array $items): HtmlString
    {
        $items = array_map(
            fn (array $item): array => array_key_exists('icon', $item)
                ? $item
                : $item + ['icon' => self::PERIOD_ICONS[$item['label']] ?? null],
            $items,
        );

        return new HtmlString(
            view('filament.widgets.stat-badges', ['items' => $items])->render()
        );
    }
}
