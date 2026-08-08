<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadImports\Tables;

use App\Actions\Lead\RetryFailedRowsAction;
use App\Enums\LeadImportStatus;
use App\Models\LeadImport;
use App\Services\Lead\LeadImportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class LeadImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Imports run on a queue, so the history refreshes itself while a
            // file is being processed rather than needing a manual reload.
            ->poll('5s')
            ->columns([
                TextColumn::make('original_filename')
                    ->label('File')
                    ->description(fn (LeadImport $record): ?string => $record->label)
                    ->wrap()
                    ->searchable()
                    ->limit(50),

                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->sortable()
                    ->searchable()
                    ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),

                TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('uploader', fn (Builder $inner): Builder => $inner
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime(config('leads.display.datetime_format'))
                    ->timezone(config('leads.display.timezone'))
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                ViewColumn::make('progress')
                    ->label('Progress')
                    ->view('filament.lead.columns.import-progress'),

                TextColumn::make('total_rows')
                    ->label('Rows')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('imported_rows')
                    ->label('New')
                    ->badge()
                    ->color('success')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('updated_rows')
                    ->label('Updated')
                    ->badge()
                    ->color('info')
                    ->alignEnd()
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('skipped_rows')
                    ->label('Skipped')
                    ->badge()
                    ->color('gray')
                    ->alignEnd()
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('failed_rows')
                    ->label('Failed')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('duration_for_humans')
                    ->label('Duration')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('encoding')
                    ->label('Format')
                    ->formatStateUsing(fn (LeadImport $record): string => sprintf(
                        '%s · %s',
                        $record->encoding,
                        $record->delimiter === "\t" ? 'Tab' : $record->delimiter
                    ))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(LeadImportStatus::options())
                    ->multiple(),

                SelectFilter::make('clinic_id')
                    ->label('Clinic')
                    ->relationship('clinic', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => check_role(config('project.roles.super_admin'))),

                SelectFilter::make('uploaded_by')
                    ->label('Uploaded by')
                    ->relationship('uploader', 'first_name')
                    ->searchable()
                    ->preload(),

                Filter::make('uploaded_between')
                    ->schema([
                        DatePicker::make('from')->label('Uploaded from'),
                        DatePicker::make('until')->label('Uploaded until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date))),

                Filter::make('has_failures')
                    ->label('Has unresolved failures')
                    ->query(fn (Builder $query): Builder => $query->whereHas('failures', fn (Builder $inner): Builder => $inner->where('is_resolved', false)))
                    ->toggle(),

                // Deleted records are hidden by default, so without this there
                // is no way to reach one to restore or permanently remove it.
                TrashedFilter::make(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(4)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('downloadOriginal')
                        ->label('Download original')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (LeadImport $record): bool => $record->hasFile()
                            && auth()->user()->can('download', $record))
                        // Streamed through an authorised action rather than a
                        // public URL: the file is raw personal data.
                        ->action(fn (LeadImport $record) => Storage::disk($record->disk)
                            ->download($record->stored_path, $record->original_filename)),

                    Action::make('downloadFailed')
                        ->label('Download failed rows')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->visible(fn (LeadImport $record): bool => $record->hasFailedExport()
                            && auth()->user()->can('download', $record))
                        ->action(fn (LeadImport $record) => Storage::disk($record->disk)->download(
                            $record->failed_export_path,
                            'failed_' . $record->original_filename,
                        )),

                    Action::make('retryFailed')
                        ->label('Retry failed rows')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Failed rows are replayed from their stored data. Rows that now import successfully are marked resolved.')
                        ->visible(fn (LeadImport $record): bool => app(RetryFailedRowsAction::class)->canRun($record)
                            && auth()->user()->can('retry', $record))
                        ->action(function (LeadImport $record): void {
                            $count = app(RetryFailedRowsAction::class)->execute($record);

                            Notification::make()
                                ->title($count > 0 ? 'Retrying failed rows' : 'Nothing to retry')
                                ->body($count > 0 ? "{$count} rows queued for another attempt." : 'All failed rows have already been resolved.')
                                ->color($count > 0 ? 'success' : 'gray')
                                ->send();
                        }),

                    Action::make('cancel')
                        ->label('Cancel import')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('Rows already imported are kept. Remaining rows will not be processed.')
                        ->visible(fn (LeadImport $record): bool => $record->status->isCancellable()
                            && auth()->user()->can('cancel', $record))
                        ->action(function (LeadImport $record): void {
                            app(LeadImportService::class)->cancel($record);

                            Notification::make()->title('Import cancelled')->warning()->send();
                        }),

                    // Removing the history entry, not the leads: the foreign key
                    // is nullOnDelete, so imported leads survive and simply stop
                    // pointing at a batch.
                    DeleteAction::make()
                        ->label('Delete record')
                        ->modalHeading('Delete this import record')
                        ->modalDescription(fn (LeadImport $record): string => static::deleteDescription($record)
                            . ' The record can be restored afterwards.')
                        // A running import would keep writing rows against a
                        // record that no longer appears in the history.
                        ->visible(fn (LeadImport $record): bool => ! $record->status->isRunning()),

                    RestoreAction::make(),

                    ForceDeleteAction::make()
                        ->label('Delete permanently')
                        ->modalHeading('Permanently delete this import record')
                        ->modalDescription(fn (LeadImport $record): string => static::deleteDescription($record)
                            . ' Its failure rows and logs are destroyed with it, and this cannot be undone.')
                        ->visible(fn (LeadImport $record): bool => ! $record->status->isRunning())
                        // The uploaded export and the failed-rows CSV live on
                        // disk, outside the cascade, so they are removed here or
                        // not at all.
                        ->before(fn (LeadImport $record) => static::deleteStoredFiles($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    RestoreBulkAction::make(),

                    ForceDeleteBulkAction::make()
                        ->label('Delete permanently')
                        ->modalDescription('Imported leads are kept and lose their link to the import. Failure rows, logs and the stored files are destroyed. This cannot be undone.')
                        ->before(fn (Collection $records) => $records->each(
                            fn (LeadImport $record) => static::deleteStoredFiles($record)
                        )),
                ]),
            ])
            ->emptyStateHeading('No imports yet')
            ->emptyStateDescription('Upload a Facebook lead export to get started.')
            ->emptyStateIcon('heroicon-o-inbox-arrow-down');
    }

    /**
     * Say what deleting this record does to the leads that came from it.
     *
     * The question anyone hesitates over here is whether the patients go too,
     * so the count is stated rather than described in the abstract.
     */
    protected static function deleteDescription(LeadImport $record): string
    {
        $leads = $record->leads()->count();

        if ($leads === 0) {
            return 'No leads came from this import.';
        }

        return sprintf(
            'The %s imported from this file %s kept, and will no longer be linked to an import batch.',
            $leads === 1 ? '1 lead' : number_format($leads) . ' leads',
            $leads === 1 ? 'is' : 'are',
        );
    }

    /**
     * Remove the uploaded export and the failed-rows CSV.
     *
     * Both sit on a disk rather than in the database, so the foreign key
     * cascade does not reach them: a permanent delete that skipped this would
     * leave raw personal data behind with nothing left pointing at it.
     */
    protected static function deleteStoredFiles(LeadImport $record): void
    {
        $disk = Storage::disk($record->disk);

        foreach ([$record->stored_path, $record->failed_export_path] as $path) {
            if (filled($path) && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
