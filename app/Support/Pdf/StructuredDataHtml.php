<?php

namespace App\Support\Pdf;

use BackedEnum;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Turns the free-form JSON the assessments and sessions carry (diagnosis,
 * plans, steps, scan metrics…) into readable HTML for mPDF.
 *
 * The shapes are produced by AI prompts and change between versions, so
 * nothing here knows about specific keys. It reads the shape instead:
 *
 *  - a flat object becomes a label / value grid,
 *  - a list of short strings becomes chips, longer ones a bullet list,
 *  - a list of flat objects that share their keys becomes a table,
 *  - anything more nested becomes titled blocks, one level at a time.
 */
class StructuredDataHtml
{
    /** Deeper than this and nesting stops adding indentation. */
    private const MAX_INDENT_DEPTH = 4;

    /** A list of flat objects with more columns than this reads better as cards. */
    private const MAX_TABLE_COLUMNS = 6;

    /**
     * Rough page capacity, in the units fitsOnPage() counts, used to decide
     * whether a block can be kept whole. Measured on real cards, a full A4
     * page holds about seventy; this leaves a margin.
     */
    private const PAGE_LINES = 60;

    /** Set while rendering inside a block that is already kept whole. */
    private static bool $insideKeep = false;

    /** Rows / items that travel with a heading when a table or list is split. */
    private const HEAD_ROWS = 3;

    private const KEEP = 'page-break-inside: avoid;';

    public static function render(mixed $value, int $depth = 0): string
    {
        return implode('', array_map(self::html(...), self::pieces($value, $depth)));
    }

    /**
     * A titled section (e.g. "Diagnosis") that never leaves its title
     * stranded at the foot of a page and, when it fits on one page, moves
     * to the next page whole instead of splitting.
     */
    public static function section(string $title, mixed $content, string $titleStyle = ''): string
    {
        $titleHtml = '<div class="clinical-title"' . ($titleStyle ? ' style="' . e($titleStyle) . '"' : '') . '>' . e($title) . '</div>';

        return self::html(self::block('clinical-block', $titleHtml, fn() => self::pieces($content)));
    }

    /**
     * The rendered value as consecutive blocks, so a caller can keep a
     * heading with the first of them.
     *
     * A piece is either finished HTML or, for a block too tall for one page,
     * a split — `head` is its opening (title and first rows) that must not be
     * separated from whatever heading precedes it, `rest` the remainder.
     *
     * @return list<string|array{class: string, head: string, rest: string}>
     */
    private static function pieces(mixed $value, int $depth = 0): array
    {
        $value = self::normalise($value);

        if (self::isEmpty($value)) {
            return ['<span class="muted">—</span>'];
        }

        if (! is_array($value)) {
            return [self::scalar($value)];
        }

        return array_is_list($value)
            ? self::renderList($value, $depth)
            : self::renderObject($value, $depth);
    }

    /**
     * mPDF moves a `page-break-inside: avoid` block to the next page when it
     * would otherwise split, but only if it fits on one page, and every such
     * block costs another layout pass. So only blocks estimated to fit are
     * marked, and never one inside another.
     *
     * A block too tall to keep whole comes back as a split, so the heading
     * above it can be kept with its opening — however deep the first child.
     *
     * @param  callable(): list<string|array>  $render
     * @return string|array{class: string, head: string, rest: string}
     */
    private static function block(string $class, string $title, callable $render): string|array
    {
        $open = '<div class="' . $class . '">';

        if (self::$insideKeep) {
            return $open . $title . implode('', array_map(self::html(...), $render())) . '</div>';
        }

        self::$insideKeep = true;

        try {
            $whole = $title . implode('', array_map(self::html(...), $render()));
        } finally {
            self::$insideKeep = false;
        }

        if (self::fitsOnPage($whole)) {
            return '<div class="' . $class . '" style="' . self::KEEP . '">' . $whole . '</div>';
        }

        // Too tall: render again so the parts can keep themselves, and glue
        // the title to the opening of the first part.
        $pieces = $render();
        $first = array_shift($pieces) ?? '';

        if (is_array($first)) {
            // The first part is split too: its box is drawn as two joined
            // boxes, the top one travelling with this title.
            $head = $title . self::splitBox($first['class'], $first['head'], 'head');
            $rest = self::splitBox($first['class'], $first['rest'], 'rest');
        } else {
            $head = $title . $first;
            $rest = '';
        }

        return [
            'class' => $class,
            'head' => $head,
            'rest' => $rest . implode('', array_map(self::html(...), $pieces)),
        ];
    }

    /**
     * Finished HTML for a piece. A split that nobody glued a heading to
     * still keeps its own opening together.
     *
     * @param  string|array{class: string, head: string, rest: string}  $piece
     */
    private static function html(string|array $piece): string
    {
        if (is_string($piece)) {
            return $piece;
        }

        // Blocks inside a kept head are already kept by it; leaving their
        // own markers would only cost mPDF another layout pass each.
        $head = self::fitsOnPage($piece['head'])
            ? '<div style="' . self::KEEP . '">' . str_replace(' style="' . self::KEEP . '"', '', $piece['head']) . '</div>'
            : $piece['head'];

        return self::wrap($piece['class'], $head . $piece['rest']);
    }

    private static function wrap(string $class, string $html): string
    {
        return $class === '' ? $html : '<div class="' . $class . '">' . $html . '</div>';
    }

    /**
     * One half of a box split across the heading that travels with it.
     */
    private static function splitBox(string $class, string $html, string $part): string
    {
        if ($class === '') {
            return $html;
        }

        $style = $part === 'head'
            ? 'margin-bottom: 0; padding-bottom: 0; border-bottom: none;'
            : 'margin-top: 0; padding-top: 0; border-top: none;';

        return '<div class="' . $class . '" style="' . $style . '">' . $html . '</div>';
    }

    /**
     * Rows too many for one page become a split: the first few travel with
     * whatever heading precedes them.
     *
     * @param  list<string>  $rows  `<tr>` / `<li>` elements
     * @return string|array{class: string, head: string, rest: string}
     */
    private static function rows(string $open, string $close, array $rows): string|array
    {
        $whole = $open . implode('', $rows) . $close;

        if (self::$insideKeep || count($rows) <= self::HEAD_ROWS || self::fitsOnPage($whole)) {
            return $whole;
        }

        // The continuation drops the column headings: they sit directly
        // above it unless a page break falls there.
        $restOpen = preg_replace('#<thead>.*?</thead>#s', '', $open);
        $restOpen = preg_replace('#^<(table|ul)#', '<$1 style="margin-top: 0; border-top: none;"', $restOpen);
        $headOpen = preg_replace('#^<(table|ul)#', '<$1 style="margin-bottom: 0;"', $open);

        return [
            'class' => '',
            'head' => $headOpen . implode('', array_slice($rows, 0, self::HEAD_ROWS)) . $close,
            'rest' => $restOpen . implode('', array_slice($rows, self::HEAD_ROWS)) . $close,
        ];
    }

    public static function fitsOnPage(string $html): bool
    {
        $lines = substr_count($html, '<tr')
            + substr_count($html, '<li')
            + substr_count($html, '<br')
            + substr_count($html, '<div') * 0.6
            // A photo row (four to a row) is about ten lines tall. SVG images
            // are inline progress bars, no taller than their line.
            + preg_match_all('#<img(?![^>]*image/svg)#', $html) * 2.5
            // Collapsed, or template indentation would count as text.
            + mb_strlen(preg_replace('/\s+/u', ' ', strip_tags($html))) / 95;

        return $lines <= self::PAGE_LINES;
    }

    /**
     * The same HTML with its keep-together markers removed, for placing
     * inside a block that is itself kept together.
     */
    public static function withoutKeeps(string $html): string
    {
        return str_replace(
            [' style="' . self::KEEP . '"', ' class="keep"', ' keep"'],
            ['', '', '"'],
            $html,
        );
    }

    /**
     * Whether a value has anything worth printing.
     */
    public static function isEmpty(mixed $value): bool
    {
        $value = self::normalise($value);

        if (is_array($value)) {
            foreach ($value as $item) {
                if (! self::isEmpty($item)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || (is_string($value) && trim($value) === '');
    }

    public static function label(string|int $key): string
    {
        if (is_int($key)) {
            return '#' . ($key + 1);
        }

        return Str::of($key)->replace(['_', '-'], ' ')->squish()->ucfirst()->toString();
    }

    public static function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return self::yesNo($value);
        }

        if (is_string($value) && in_array(strtolower(trim($value)), ['true', 'false'], true)) {
            return self::yesNo(strtolower(trim($value)) === 'true');
        }

        if (is_float($value)) {
            $value = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }

        // The AI writes light markdown; keep its emphasis, drop the markers.
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', e((string) $value));

        return nl2br($html);
    }

    private static function yesNo(bool $value): string
    {
        return $value
            ? '<span class="chip chip-green">Yes</span>'
            : '<span class="chip chip-gray">No</span>';
    }

    private static function normalise(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y, h:i A');
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true);
        }

        // Some columns hold JSON encoded twice.
        if (is_string($value) && strlen($value) > 1 && in_array($value[0], ['{', '['], true)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

    private static function isFlat(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array(self::normalise($item))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string|array>
     */
    private static function renderList(array $items, int $depth): array
    {
        $items = array_values(array_filter($items, fn($item) => ! self::isEmpty($item)));

        if ($items === []) {
            return ['<span class="muted">—</span>'];
        }

        $items = array_map(fn($item) => self::normalise($item), $items);

        if (self::isFlat($items)) {
            $short = collect($items)->every(fn($item) => mb_strlen((string) $item) <= 40);

            if ($short) {
                return ['<div class="chips">' . collect($items)
                    ->map(fn($item) => '<span class="chip">' . self::scalar($item) . '</span>')
                    ->implode(' ') . '</div>'];
            }

            return [self::rows(
                '<ul class="list">',
                '</ul>',
                array_map(fn($item) => '<li>' . self::scalar($item) . '</li>', $items),
            )];
        }

        $allObjects = collect($items)->every(fn($item) => is_array($item) && ! array_is_list($item));

        if ($allObjects && collect($items)->every(fn($item) => self::isFlat($item))) {
            $columns = collect($items)->flatMap(fn($item) => array_keys($item))->unique()->values();

            if ($columns->count() <= self::MAX_TABLE_COLUMNS) {
                return [self::renderTable($items, $columns->all())];
            }
        }

        $cards = [];

        foreach ($items as $index => $item) {
            $cards[] = self::block(
                'card-item',
                '<div class="card-item-index">' . ($index + 1) . '</div>',
                fn() => self::pieces($item, $depth + 1),
            );
        }

        return $cards;
    }

    private static function renderTable(array $rows, array $columns): string|array
    {
        $open = '<table class="data-table"><thead><tr>';

        foreach ($columns as $column) {
            $open .= '<th>' . e(self::label($column)) . '</th>';
        }

        $open .= '</tr></thead><tbody>';
        $trs = [];

        foreach ($rows as $row) {
            $tr = '<tr>';

            foreach ($columns as $column) {
                $cell = $row[$column] ?? null;
                $tr .= '<td>' . (self::isEmpty($cell) ? '<span class="muted">—</span>' : self::scalar($cell)) . '</td>';
            }

            $trs[] = $tr . '</tr>';
        }

        return self::rows($open, '</tbody></table>', $trs);
    }

    /**
     * Keys that only repeat data shown elsewhere in the same record.
     */
    private static function isSkipped(string $key): bool
    {
        return str_starts_with(strtolower($key), 'passthrough');
    }

    /**
     * @param  array<string, array<string, mixed>>  $records
     */
    private static function renderKeyedTable(array $records, array $columns): string|array
    {
        $open = '<table class="data-table"><thead><tr><th></th>';

        foreach ($columns as $column) {
            $open .= '<th>' . e(self::label($column)) . '</th>';
        }

        $open .= '</tr></thead><tbody>';
        $trs = [];

        foreach ($records as $key => $row) {
            $tr = '<tr><td class="bold">' . e(self::label($key)) . '</td>';

            foreach ($columns as $column) {
                $cell = self::normalise($row[$column] ?? null);
                $tr .= '<td>' . (self::isEmpty($cell) ? '<span class="muted">—</span>' : self::scalar($cell)) . '</td>';
            }

            $trs[] = $tr . '</tr>';
        }

        return self::rows($open, '</tbody></table>', $trs);
    }

    /**
     * @return list<string|array>
     */
    private static function renderObject(array $object, int $depth): array
    {
        $scalars = [];
        $records = [];
        $nested = [];

        foreach ($object as $key => $item) {
            if (is_string($key) && self::isSkipped($key)) {
                continue;
            }

            $item = self::normalise($item);

            if (self::isEmpty($item)) {
                continue;
            }

            if (! is_array($item)) {
                $scalars[$key] = $item;
            } elseif (! array_is_list($item) && self::isFlat($item)) {
                $records[$key] = $item;
            } else {
                $nested[$key] = $item;
            }
        }

        // Sibling objects of the same small shape (scores, per-zone metrics…)
        // read far better as one table than as a heading per entry.
        $recordColumns = collect($records)->flatMap(fn($item) => array_keys($item))->unique()->values();

        if (count($records) >= 2 && $recordColumns->count() < self::MAX_TABLE_COLUMNS) {
            $recordsTable = self::renderKeyedTable($records, $recordColumns->all());
        } else {
            $recordsTable = null;
            // Back into the one group, in the order the source had them.
            $nested = array_replace(array_intersect_key($object, $records + $nested), $records + $nested);
        }

        if ($scalars === [] && $nested === [] && $recordsTable === null) {
            return ['<span class="muted">—</span>'];
        }

        $pieces = [];

        if ($scalars !== []) {
            $pieces[] = self::rows('<table class="kv-table">', '</table>', array_map(
                fn($key, $item) => '<tr><td class="kv-label">' . e(self::label($key)) . '</td>'
                    . '<td class="kv-value">' . self::scalar($item) . '</td></tr>',
                array_keys($scalars),
                $scalars,
            ));
        }

        if ($recordsTable !== null) {
            $pieces[] = $recordsTable;
        }

        $level = min($depth, self::MAX_INDENT_DEPTH);

        foreach ($nested as $key => $item) {
            $pieces[] = self::block(
                'nest nest-' . $level,
                '<div class="nest-title nest-title-' . $level . '">' . e(self::label($key)) . '</div>',
                fn() => self::pieces($item, $depth + 1),
            );
        }

        return $pieces;
    }
}
