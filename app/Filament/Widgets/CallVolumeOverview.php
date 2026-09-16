<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\Call\CallDirection;
use App\Enums\Call\CallStatus;
use App\Filament\Widgets\Concerns\RendersStatBadges;
use App\Models\Call;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * The phone at four lengths: today, yesterday, this month, and all of it.
 *
 * Sits above the thirty-day summary rather than inside it, because the two
 * answer different questions. Thirty days says how the line is performing;
 * these four say whether the phone is busier or quieter than it was yesterday,
 * which is what somebody opens this page to find out and the one thing a
 * rolling window structurally cannot tell them.
 *
 * Every card carries the same four figures, so comparing periods is a straight
 * read across the row rather than a hunt. Direction leads because sixty
 * outgoing calls and sixty incoming ones are the same total and opposite days —
 * one is the clinic working a list, the other is the advertising working.
 */
class CallVolumeOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    // The badge rendering the Leads overview uses, so the two read the same.
    use RendersStatBadges;

    // protected ?string $heading = 'Call Overview';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * The union must match the parent exactly — StatsOverviewWidget declares
     * int|array|null, and adding "string" to it is a fatal error at class load
     * that takes down every page rendering the panel.
     */
    protected int|array|null $columns = [
        'default' => 1,
        'sm' => 2,
        'xl' => 4,
    ];

    /**
     * Polling off. The table below already polls every ten seconds and reloads
     * its header widgets with it; a second timer here would double the queries
     * for numbers that are the same either way.
     */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Call::class) ?? false;
    }

    protected function getStats(): array
    {
        $startOfToday = Carbon::today();
        $startOfYesterday = $startOfToday->copy()->subDay();
        $startOfMonth = Carbon::now()->startOfMonth();

        $counts = $this->countsByDay(
            // On the first of the month, yesterday is in the previous one.
            // Starting at the month boundary would leave the yesterday card
            // permanently empty on exactly that day.
            $startOfMonth->lt($startOfYesterday) ? $startOfMonth : $startOfYesterday
        );

        return [
            $this->periodStat(
                'Today',
                $this->figures($counts->where('day', $startOfToday->toDateString())),
                'heroicon-m-sun',
                'primary',
            ),

            $this->periodStat(
                'Yesterday',
                $this->figures($counts->where('day', $startOfYesterday->toDateString())),
                'heroicon-m-arrow-uturn-left',
                'gray',
            ),

            $this->periodStat(
                'This month',
                $this->figures($counts->where('day', '>=', $startOfMonth->toDateString())),
                'heroicon-m-calendar-days',
                'info',
            ),

            // Unbounded by date on purpose: the standing total is the context
            // the other three are read against.
            $this->periodStat(
                'All calls',
                $this->overallFigures(),
                'heroicon-m-phone',
                'gray',
            ),
        ];
    }

    /**
     * One period: the total, with the four figures that describe it beneath.
     *
     * Volume alone flatters, which is why the rate sits here rather than being
     * left to the reader: sixty calls that reached nobody is a worse day than
     * twenty that reached someone, and only one of those numbers says so.
     *
     * @param  array{total: int, incoming: int, outgoing: int, connected: int, missed: int}  $figures
     */
    protected function periodStat(string $label, array $figures, string $icon, string $color): Stat
    {
        // Not incoming + outgoing: the direction vocabulary has four values,
        // and a call reported as internal or unknown still happened. A total
        // that quietly omitted it would disagree with the list underneath.
        $total = $figures['total'];

        $badges = [
            [
                'label' => 'incoming',
                'value' => number_format($figures['incoming']),
                // The same two glyphs the rows use for direction, so the
                // summary and the list read as one screen rather than two
                // vocabularies.
                'icon' => 'heroicon-m-arrow-down-left',
                // Coloured only when there is something to colour. A green zero
                // reads as "all well" on the morning nobody rang.
                'color' => $figures['incoming'] > 0 ? 'success' : 'gray',
            ],
            [
                'label' => 'outgoing',
                'value' => number_format($figures['outgoing']),
                'icon' => 'heroicon-m-arrow-up-right',
                'color' => $figures['outgoing'] > 0 ? 'info' : 'gray',
            ],
            [
                'label' => 'missed',
                'value' => number_format($figures['missed']),
                'icon' => 'heroicon-m-phone-x-mark',
                // Red where calls were missed: somebody rang the clinic and
                // nobody answered, which is the strongest thing this row can
                // report. Green only where there were calls to miss and none
                // was — on a day with no calls at all, a green nought claims a
                // success nobody earned.
                'color' => match (true) {
                    $figures['missed'] > 0 => 'danger',
                    $total > 0 => 'success',
                    default => 'gray',
                },
            ],
        ];

        // A rate over no calls is not zero, it is undefined. A red nought per
        // cent on a quiet morning is an alarm about nothing, so the badge is
        // left off rather than printed as a failure.
        $rate = $total > 0 ? round($figures['connected'] / $total * 100, 1) : null;

        if ($rate !== null) {
            $badges[] = [
                'label' => 'reached',
                'value' => $rate . '%',
                // Signal bars: how much of the calling actually got through.
                'icon' => 'heroicon-m-signal',
                // The same thresholds the thirty-day card uses, so the two
                // never colour the same rate differently.
                'color' => match (true) {
                    $rate >= 60 => 'success',
                    $rate >= 35 => 'warning',
                    default => 'danger',
                },
            ];
        }

        return Stat::make($label, number_format($total))
            ->description($this->badges($badges))
            ->icon($icon)
            ->color($total > 0 ? $color : 'gray');
    }

    /**
     * Add a set of daily rows up into the five figures a card needs.
     *
     * @param  Collection<int, object>  $rows
     * @return array{total: int, incoming: int, outgoing: int, connected: int, missed: int}
     */
    protected function figures(Collection $rows): array
    {
        return [
            'total' => (int) $rows->sum('total'),
            'incoming' => (int) $rows->sum('incoming'),
            'outgoing' => (int) $rows->sum('outgoing'),
            'connected' => (int) $rows->sum('connected'),
            'missed' => (int) $rows->sum('missed'),
        ];
    }

    /**
     * Every call on record, in one row.
     *
     * @return array{total: int, incoming: int, outgoing: int, connected: int, missed: int}
     */
    protected function overallFigures(): array
    {
        $row = $this->withFigures(Call::query()->forCurrentClinic())->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'incoming' => (int) ($row->incoming ?? 0),
            'outgoing' => (int) ($row->outgoing ?? 0),
            'connected' => (int) ($row->connected ?? 0),
            'missed' => (int) ($row->missed ?? 0),
        ];
    }

    /**
     * One row per day, carrying every figure the period cards need.
     *
     * Grouped in SQL and summed in PHP rather than a count per period per
     * figure — sixteen queries otherwise, on a widget that renders above a
     * polling table. The result is at most a month of rows.
     *
     * DATE() appears only in the SELECT, so the range in the WHERE still uses
     * the index on started_at.
     *
     * @return Collection<int, object>
     */
    protected function countsByDay(Carbon $from): Collection
    {
        return $this->withFigures(
            Call::query()
                ->forCurrentClinic()
                ->where('started_at', '>=', $from)
                ->selectRaw('DATE(started_at) as day')
        )
            ->groupByRaw('DATE(started_at)')
            ->get();
    }

    /**
     * The five figures every card is built from.
     *
     * One definition, applied to both the standing total and the per-day
     * breakdown. They match CallAnalyticsService, which draws the thirty-day
     * card directly below this one: missed is the status, connected is the
     * flag. Defining either differently here would put two numbers on one
     * screen that disagree about the same calls.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Call>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Call>
     */
    protected function withFigures($query)
    {
        return $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as incoming', [CallDirection::Incoming->value])
            ->selectRaw('SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END) as outgoing', [CallDirection::Outgoing->value])
            ->selectRaw('SUM(CASE WHEN is_connected = 1 THEN 1 ELSE 0 END) as connected')
            ->selectRaw('SUM(CASE WHEN call_status = ? THEN 1 ELSE 0 END) as missed', [CallStatus::Missed->value]);
    }
}
