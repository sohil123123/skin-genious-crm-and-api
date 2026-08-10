<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\LeadImport;
use App\Models\LeadImportFailure;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class LeadImportStatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Import Activity';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $base = fn (): Builder => LeadImport::query()
            ->when(! check_role(config('project.roles.super_admin')), fn (Builder $query) => $query->forCurrentClinic());

        $running = $base()->running()->count();
        $last30Days = $base()->where('created_at', '>=', now()->subDays(30));

        // MySQL returns SUM() as a string, and number_format() rejects strings
        // on PHP 8.4, so these are cast rather than passed through.
        $importedRows = (int) (clone $last30Days)->sum('imported_rows');
        $updatedRows = (int) (clone $last30Days)->sum('updated_rows');
        $skippedRows = (int) (clone $last30Days)->sum('skipped_rows');

        $unresolvedFailures = LeadImportFailure::query()
            ->where('is_resolved', false)
            ->whereHas('import', fn (Builder $query) => $query
                ->when(! check_role(config('project.roles.super_admin')), fn (Builder $inner) => $inner->forCurrentClinic()))
            ->count();

        return [
            Stat::make('Running now', number_format($running))
                ->description($running > 0 ? 'Progress updates automatically' : 'Nothing in the queue')
                ->icon('heroicon-m-arrow-path')
                ->color($running > 0 ? 'warning' : 'gray'),

            Stat::make('New leads (30 days)', number_format($importedRows))
                ->description(number_format($updatedRows) . ' existing leads updated')
                ->icon('heroicon-m-user-plus')
                ->color('success'),

            Stat::make('Duplicates skipped (30 days)', number_format($skippedRows))
                ->description('Rows that already existed in the CRM')
                ->icon('heroicon-m-document-duplicate')
                ->color('info'),

            Stat::make('Rows awaiting fix', number_format($unresolvedFailures))
                ->description($unresolvedFailures > 0 ? 'Download, correct and retry them' : 'Nothing outstanding')
                ->icon('heroicon-m-exclamation-triangle')
                ->color($unresolvedFailures > 0 ? 'danger' : 'success'),
        ];
    }

    public function getColumns(): int
    {
        return 4;
    }
}
