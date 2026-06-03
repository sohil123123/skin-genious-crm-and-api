<?php

namespace App\Filament\Resources\ClinicInventories;

use App\Filament\Resources\ClinicInventories\Pages\CreateClinicInventory;
use App\Filament\Resources\ClinicInventories\Pages\EditClinicInventory;
use App\Filament\Resources\ClinicInventories\Pages\ListClinicInventories;
use App\Filament\Resources\ClinicInventories\Schemas\ClinicInventoryForm;
use App\Filament\Resources\ClinicInventories\Tables\ClinicInventoriesTable;
use App\Models\ClinicInventory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ClinicInventoryResource extends Resource
{
    protected static ?string $model = ClinicInventory::class;

    protected static ?string $navigationLabel = 'Inventories';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $recordTitleAttribute = 'id';

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ClinicInventoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClinicInventoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClinicInventories::route('/'),
            // 'create' => CreateClinicInventory::route('/create'),
            // 'edit' => EditClinicInventory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->when(!check_role(config('project.roles.super_admin')), function ($query) {
                $query->where('clinic_id', auth()->user()->clinic_id);
            });
    }
}
