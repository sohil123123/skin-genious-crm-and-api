<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\PurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ExportAction::make()
                ->exporter(\App\Filament\Exports\PurchaseExporter::class)
                ->label('Export CSV/Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success'),
            \Filament\Actions\Action::make('downloadPdfReport')
                ->label('Download PDF Report')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function (\App\Services\PurchasePdfService $service) {
                    $records = $this->getFilteredTableQuery()->get();
                    return $service->downloadReport($records);
                }),
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
