<?php

namespace App\Filament\Resources\UserPackages\Pages;

use App\Exports\UserPackageExport;
use App\Filament\Actions\ExportExcelAction;
use App\Filament\Resources\UserPackages\UserPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserPackages extends ListRecords
{
    protected static string $resource = UserPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportExcelAction::make(UserPackageExport::class, 'packages', 'Export Excel (.xlsx)'),
            CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
