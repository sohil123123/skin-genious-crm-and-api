<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Pages;

use App\Enums\Call\CallProvider;
use App\Filament\Resources\Calls\CallResource;
use App\Filament\Widgets\CallStatsOverview;
use App\Jobs\Call\SyncCallyzerCallsJob;
use App\Models\CallSyncRun;
use App\Services\Call\CallProviderManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * The call list, with the tabs staff actually work from.
 *
 * "Needs attention" is first rather than last on purpose: an unmatched call is
 * a conversation sitting outside a patient's history, and it stays invisible
 * until somebody is shown it.
 */
class ListCalls extends ListRecords
{
    protected static string $resource = CallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Re-runs the page's own queries. The badges above the table are
            // live counts of work queues, and this is the cheapest way to see
            // whether anything arrived while the page sat open.
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {}),

            $this->syncCallyzerAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CallStatsOverview::class,
        ];
    }

    /**
     * The work queues, above the table.
     *
     * Every tab carries a live count, so the size of the matching backlog and
     * the follow-up list is visible without opening either. The two that
     * represent work colour themselves only while there is something in them —
     * a cleared queue sitting there in amber reads as a warning nobody can act
     * on, and teaches people to ignore the colour.
     *
     * Counts come from listingQuery() rather than the tab's own modified query,
     * so a badge can never disagree with the rows underneath it.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-queue-list')
                ->badge(fn (): int => static::listingQuery()->count())
                ->badgeColor('gray'),

            'needs_attention' => Tab::make('Needs attention')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->needsMatching())
                ->badge(fn (): int => static::listingQuery()->needsMatching()->count())
                ->badgeColor(fn (): string => static::listingQuery()->needsMatching()->exists() ? 'warning' : 'gray'),

            // Temporarily off with the rest of follow-up; see the note on the
            // Follow-up filter in CallsTable. A tab whose count can only ever
            // fall, because nothing can raise a new flag, is worse than no tab.
            //
            // 'follow_up' => Tab::make('Follow-up')
            //     ->icon('heroicon-o-flag')
            //     ->modifyQueryUsing(fn (Builder $query): Builder => $query->needsFollowUp())
            //     ->badge(fn (): int => static::listingQuery()->needsFollowUp()->count())
            //     ->badgeColor(fn (): string => static::listingQuery()->needsFollowUp()->exists() ? 'danger' : 'gray'),

            'today' => Tab::make('Today')
                ->icon('heroicon-o-calendar-days')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereDate('started_at', now(app_timezone())->toDateString()))
                ->badge(fn (): int => static::listingQuery()
                    ->whereDate('started_at', now(app_timezone())->toDateString())
                    ->count())
                ->badgeColor('info'),

            // Grey, unlike the tabs beside them. Incoming and outgoing counts
            // are the shape of the day's traffic, not a queue of work - a
            // coloured badge here would compete with the ones that mean
            // somebody has to do something.
            'incoming' => Tab::make('Incoming')
                ->icon('heroicon-o-arrow-down-left')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->incoming())
                ->badge(fn (): int => static::listingQuery()->incoming()->count())
                ->badgeColor('gray'),

            'outgoing' => Tab::make('Outgoing')
                ->icon('heroicon-o-arrow-up-right')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->outgoing())
                ->badge(fn (): int => static::listingQuery()->outgoing()->count())
                ->badgeColor('gray'),

            'missed' => Tab::make('Missed')
                ->icon('heroicon-o-phone-x-mark')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->missed())
                ->badge(fn (): int => static::listingQuery()->missed()->count())
                ->badgeColor(fn (): string => static::listingQuery()->missed()->exists() ? 'danger' : 'gray'),
        ];
    }

    /**
     * The resource query as the table actually lists it — deleted calls
     * excluded — so a badge can never disagree with the rows below it.
     */
    protected static function listingQuery(): Builder
    {
        return static::getResource()::getEloquentQuery()->whereNull('calls.deleted_at');
    }

    /**
     * Pull Callyzer history now rather than waiting for the schedule.
     *
     * Queued rather than run inline: a wide window is minutes of paced API
     * calls, and doing that in a web request would time out and leave a
     * half-finished run behind. The in-progress guard is what stops an
     * impatient second press doubling the request rate against an API that
     * allows one call every two seconds.
     */
    protected function syncCallyzerAction(): Action
    {
        return Action::make('syncCallyzer')
            ->label('Sync Callyzer now')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (): bool => app(CallProviderManager::class)
                ->syncable(CallProvider::Callyzer)
                ?->isSyncEnabled() === true)
            ->schema([
                DatePicker::make('from')
                    ->label('From')
                    ->default(now()->subDays(2))
                    ->maxDate(now())
                    // Both are prefilled, so the placeholder only shows if
                    // someone clears one — and then it needs to say what
                    // happens next, not repeat the label.
                    ->placeholder('Continue from the last sync'),
                DatePicker::make('to')
                    ->label('To')
                    ->default(now())
                    ->maxDate(now())
                    ->placeholder('Up to now'),
            ])
            ->modalHeading('Sync Callyzer call history')
            ->modalDescription('Runs in the background, paced to the provider rate limit. Calls already imported are updated, never duplicated.')
            ->modalSubmitActionLabel('Start sync')
            ->action(function (array $data): void {
                if (CallSyncRun::isRunning(CallProvider::Callyzer)) {
                    Notification::make()
                        ->warning()
                        ->title('A sync is already running')
                        ->body('Wait for it to finish — two at once would trip the Callyzer rate limit.')
                        ->send();

                    return;
                }

                SyncCallyzerCallsJob::dispatch(
                    from: $data['from'] ?? null,
                    to: $data['to'] ?? null,
                    trigger: 'manual',
                    triggeredBy: auth()->id(),
                );

                Notification::make()
                    ->success()
                    ->title('Sync started')
                    ->body('Progress appears on the Call Integration Health page.')
                    ->send();
            });
    }
}
