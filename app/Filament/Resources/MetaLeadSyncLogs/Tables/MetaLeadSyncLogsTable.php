<?php

declare(strict_types=1);

namespace App\Filament\Resources\MetaLeadSyncLogs\Tables;

use App\Enums\MetaSyncStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\MetaLeadSyncLogs\MetaLeadSyncLogResource;
use App\Jobs\Lead\ProcessMetaLeadJob;
use App\Models\MetaLeadSyncLog;
use App\Models\MetaPage;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Filament\Tables\Enums\FiltersLayout;

/**
 * The Meta ingestion audit trail.
 *
 * Retry re-queues the existing record rather than creating a new one, so the
 * leadgen_id stays unique and a retry can never produce a second CRM lead.
 */
class MetaLeadSyncLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime(config('leads.display.datetime_format'))
                    ->timezone(config('leads.display.timezone'))
                    ->sortable(),

                TextColumn::make('metaPage.page_name')
                    ->label('Page')
                    ->badge()
                    ->color('info')
                    ->placeholder('Unknown page')
                    ->toggleable(),

                TextColumn::make('metaPage.clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->visible(fn(): bool => check_role(config('project.roles.super_admin'))),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('leadgen_id')
                    ->label('Meta lead ID')
                    ->copyable()
                    ->searchable()
                    ->fontFamily('mono')
                    ->size('xs'),

                TextColumn::make('lead_id')
                    ->label('CRM lead')
                    ->placeholder('—')
                    ->formatStateUsing(fn($state): string => '#' . $state)
                    ->url(fn(MetaLeadSyncLog $record): ?string => $record->lead_id
                        ? LeadResource::getUrl('view', ['record' => $record->lead_id])
                        : null)
                    ->color('primary'),

                TextColumn::make('attempts')
                    ->label('Tries')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('error_message')
                    ->label('Detail')
                    ->limit(60)
                    ->tooltip(fn(MetaLeadSyncLog $record): ?string => $record->error_message)
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MetaSyncStatus::options())
                    ->multiple(),

                SelectFilter::make('meta_page_id')
                    ->label('Page')
                    // A just-discovered Page has no name yet, and a null label
                    // is not something Filament can render.
                    ->relationship('metaPage', 'page_id')
                    ->getOptionLabelFromRecordUsing(fn(MetaPage $record): string => $record->displayName())
                    ->searchable()
                    ->preload(),

                Filter::make('needs_attention')
                    ->label('Needs attention')
                    ->query(fn(Builder $query): Builder => $query->where('status', MetaSyncStatus::Failed->value))
                    ->toggle(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                ViewAction::make()
                    ->modalWidth('3xl'),

                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Fetches this lead from Meta again. If the CRM already has it, nothing is duplicated.')
                    // Only unfinished records can be retried; a successful one
                    // has nothing left to do.
                    ->visible(fn(MetaLeadSyncLog $record): bool => !$record->isSettled()
                        && MetaLeadSyncLogResource::canView($record))
                    ->action(function (MetaLeadSyncLog $record): void {
                        static::requeue($record);

                        Notification::make()
                            ->title('Lead re-queued')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('retryFailed')
                        ->label('Retry selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record->isSettled()) {
                                    continue;
                                }

                                static::requeue($record);
                                $count++;
                            }

                            Notification::make()
                                ->title($count > 0 ? "{$count} leads re-queued" : 'Nothing to retry')
                                ->status($count > 0 ? 'success' : 'warning')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No Meta leads received yet')
            ->emptyStateDescription('Records appear here as leads arrive from connected Meta Pages.')
            ->emptyStateIcon('heroicon-o-arrow-path-rounded-square');
    }

    /**
     * Put a record back on the queue.
     *
     * The status is reset to pending first, because the job short-circuits on a
     * settled record and would otherwise return without doing anything.
     */
    protected static function requeue(MetaLeadSyncLog $record): void
    {
        $record->forceFill([
            'status' => MetaSyncStatus::Pending,
            'error_message' => null,
        ])->save();

        ProcessMetaLeadJob::dispatch($record->getKey());
    }
}
