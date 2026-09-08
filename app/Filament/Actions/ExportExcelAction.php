<?php

namespace App\Filament\Actions;

use App\Exports\SelectableColumnsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Header action that exports the records currently visible in a table — the
 * applied filters, search and sort are all honoured — letting the user pick
 * which columns end up in the sheet. Every column is ticked by default.
 */
class ExportExcelAction
{
    /**
     * @param  class-string<SelectableColumnsExport>  $exporter
     */
    public static function make(string $exporter, string $filenamePrefix, string $label = 'Export Excel'): Action
    {
        return Action::make('export_excel')
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success')
            ->modalHeading('Export to Excel')
            ->modalDescription('Only the records matching the filters currently applied to the table are exported. Choose the columns you need.')
            ->modalSubmitActionLabel('Export')
            ->modalWidth('3xl')
            ->schema([
                CheckboxList::make('columns')
                    ->label('Columns to export')
                    ->options($exporter::columnOptions())
                    ->default($exporter::defaultColumns())
                    ->columns(3)
                    ->gridDirection('row')
                    ->bulkToggleable()
                    ->required()
                    ->validationMessages([
                        'required' => 'Select at least one column to export.',
                    ]),
            ])
            ->action(function (array $data, $livewire) use ($exporter, $filenamePrefix) {
                $query = $livewire->getFilteredSortedTableQuery();

                if (! $query || $query->clone()->doesntExist()) {
                    Notification::make()
                        ->title('Nothing to export')
                        ->body('No records match the filters currently applied.')
                        ->warning()
                        ->send();

                    return null;
                }

                $filename = $filenamePrefix . '-' . now()->format('d-m-Y-His') . '.xlsx';

                return Excel::download(
                    new $exporter($query, $data['columns'] ?? []),
                    $filename,
                );
            });
    }
}
