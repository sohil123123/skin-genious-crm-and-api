<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeadImports\Pages;

use App\Actions\Lead\RetryFailedRowsAction;
use App\Filament\Resources\LeadImports\LeadImportResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\LeadImport;
use App\Services\Lead\LeadImportService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ViewLeadImport extends ViewRecord
{
    protected static string $resource = LeadImportResource::class;

    /**
     * Poll only while the import is moving. A finished import is static, so
     * continuing to poll would refresh the page forever for no reason.
     */
    public function getPollingInterval(): ?string
    {
        /** @var LeadImport $record */
        $record = $this->getRecord();

        return $record->status->isRunning() ? '3s' : null;
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            // The infolist lays sections out in two columns; without this the
            // progress panel is squeezed into a half-width column beside the
            // File card, which is the one thing on this page that wants room.
            View::make('filament.lead.import-progress-panel')
                ->viewData(fn (): array => ['record' => $this->getRecord()])
                ->columnSpanFull(),

            Section::make('File')
                ->columns(['default' => 1, 'md' => 3])
                ->schema([
                    TextEntry::make('original_filename')->label('File name'),
                    TextEntry::make('label')->label('Source')->placeholder('—'),
                    TextEntry::make('clinic.name')->label('Clinic')->badge()->color('info'),
                    TextEntry::make('uploader.name')->label('Uploaded by')->placeholder('—'),
                    TextEntry::make('created_at')
                        ->label('Uploaded at')
                        ->dateTime(config('leads.display.datetime_format'))
                        ->timezone(config('leads.display.timezone')),
                    TextEntry::make('file_size')
                        ->label('Size')
                        ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : number_format($state / 1024, 1) . ' KB'),
                    TextEntry::make('encoding')->label('Encoding')->badge()->color('gray'),
                    TextEntry::make('delimiter')
                        ->label('Delimiter')
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            "\t" => 'Tab',
                            ',' => 'Comma',
                            ';' => 'Semicolon',
                            '|' => 'Pipe',
                            default => (string) $state,
                        })
                        ->badge()
                        ->color('gray'),
                    TextEntry::make('template.name')->label('Mapping template')->placeholder('None'),
                ]),

            Section::make('Configuration')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextEntry::make('duplicate_strategy')
                        ->label('Duplicate handling')
                        ->badge()
                        ->helperText(fn (LeadImport $record): ?string => $record->duplicate_strategy?->getDescription()),
                    TextEntry::make('duplicate_match_fields')
                        ->label('Matched on')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => \App\Enums\CrmLeadField::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                    TextEntry::make('error_message')
                        ->label('Error')
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn (LeadImport $record): bool => filled($record->error_message)),
                ]),

            Section::make('Column mapping')
                ->collapsible()
                ->collapsed()
                ->schema([
                    View::make('filament.lead.import-mapping-summary')
                        ->viewData(fn (): array => ['record' => $this->getRecord()]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewLeads')
                ->label('View imported leads')
                ->icon('heroicon-o-user-group')
                ->color('primary')
                ->url(fn (LeadImport $record): string => LeadResource::getUrl('index', [
                    'tableFilters' => ['lead_import_id' => ['value' => $record->getKey()]],
                ]))
                ->visible(fn (LeadImport $record): bool => $record->successful_rows > 0),

            Action::make('downloadOriginal')
                ->label('Download original')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (LeadImport $record): bool => $record->hasFile() && auth()->user()->can('download', $record))
                ->action(fn (LeadImport $record) => Storage::disk($record->disk)
                    ->download($record->stored_path, $record->original_filename)),

            Action::make('downloadFailed')
                ->label('Download failed rows')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn (LeadImport $record): bool => $record->hasFailedExport() && auth()->user()->can('download', $record))
                ->action(fn (LeadImport $record) => Storage::disk($record->disk)
                    ->download($record->failed_export_path, 'failed_' . $record->original_filename)),

            Action::make('retryFailed')
                ->label('Retry failed rows')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Failed rows are replayed from their stored data, so this works even after the original file has been pruned.')
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
                ->label('Cancel')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (LeadImport $record): bool => $record->status->isCancellable() && auth()->user()->can('cancel', $record))
                ->action(function (LeadImport $record): void {
                    app(LeadImportService::class)->cancel($record);

                    Notification::make()->title('Import cancelled')->warning()->send();
                }),
        ];
    }
}
