<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Services\ExpensePdfService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(\App\Filament\Exports\ExpenseExporter::class)
                ->label('Export CSV/Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success'),

            Action::make('downloadPdfReport')
                ->label('Download PDF Report')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function (ExpensePdfService $service) {
                    $records = $this->getFilteredTableQuery()->get();

                    return $service->downloadReport($records);
                }),

            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->schema(ExpenseForm::components())
                ->modalWidth('5xl')
                ->createAnother(false)
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Expense saved successfully 🎉')
                        ->body('The expense has been recorded and sent for approval.'),
                ),
        ];
    }
}
