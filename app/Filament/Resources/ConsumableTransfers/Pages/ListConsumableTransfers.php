<?php

namespace App\Filament\Resources\ConsumableTransfers\Pages;

use App\Filament\Exports\ConsumableTransferExporter;
use App\Filament\Resources\ConsumableTransfers\ConsumableTransferResource;
use App\Services\ConsumableTransferPdfService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListConsumableTransfers extends ListRecords
{
    protected static string $resource = ConsumableTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(ConsumableTransferExporter::class)
                ->label('Export CSV/Excel')
                ->icon('heroicon-o-table-cells')
                ->color('success'),
            Action::make('downloadPdfReport')
                ->label('Download PDF Report')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function (ConsumableTransferPdfService $service) {
                    $records = $this->getFilteredTableQuery()->with(['items.product', 'clinic', 'creator'])->get();
                    return $service->downloadReport($records);
                }),
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
