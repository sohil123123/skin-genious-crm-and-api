<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use App\Services\UserReportPdfService;
use Filament\Actions\Action;

/**
 * The client report downloads — complete report plus one per section — shared
 * by the Users table menu and the user's View page.
 *
 * Each hides itself when the client has nothing in that report. That check
 * reads `*_count` attributes when they are loaded (withCount / loadCount),
 * so load them first on lists; see UserReportPdfService::countRelations().
 */
class DownloadReportActions
{
    private const SECTION_ICONS = [
        'packages' => 'heroicon-o-rectangle-stack',
        'assessments' => 'heroicon-o-clipboard-document-list',
        'sessions' => 'heroicon-o-sparkles',
        'invoices' => 'heroicon-o-document-currency-rupee',
    ];

    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [
            Action::make('download_complete_report')
                ->label('Complete Report (All)')
                ->icon('heroicon-o-document-duplicate')
                ->color('primary')
                ->visible(fn(User $record): bool => self::offered($record, 'all'))
                ->action(fn(User $record) => app(UserReportPdfService::class)->download($record, 'all')),

            ...collect(self::SECTION_ICONS)
                ->map(fn(string $icon, string $section): Action => Action::make("download_{$section}_pdf")
                    ->label(UserReportPdfService::SECTIONS[$section] . ' PDF')
                    ->icon($icon)
                    ->color('gray')
                    ->visible(fn(User $record): bool => self::offered($record, $section))
                    ->action(fn(User $record) => app(UserReportPdfService::class)->download($record, $section)))
                ->values()
                ->all(),
        ];
    }

    /**
     * Reports are for clients only, and only when there is something in them.
     */
    public static function offered(User $record, string $section): bool
    {
        return $record->hasRole('client') && UserReportPdfService::hasData($record, $section);
    }
}
