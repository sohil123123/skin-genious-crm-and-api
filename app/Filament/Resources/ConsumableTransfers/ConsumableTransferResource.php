<?php

namespace App\Filament\Resources\ConsumableTransfers;

use App\Filament\Resources\ConsumableTransfers\Pages\CreateConsumableTransfer;
use App\Filament\Resources\ConsumableTransfers\Pages\EditConsumableTransfer;
use App\Filament\Resources\ConsumableTransfers\Pages\ListConsumableTransfers;
use App\Filament\Resources\ConsumableTransfers\Schemas\ConsumableTransferForm;
use App\Filament\Resources\ConsumableTransfers\Tables\ConsumableTransfersTable;
use App\Models\ConsumableTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConsumableTransferResource extends Resource
{
    protected static ?string $model = ConsumableTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'clinic_manager']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (!auth()->user()->hasRole('super_admin')) {
            $query->where('clinic_id', auth()->user()->clinic_id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return ConsumableTransferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConsumableTransfersTable::configure($table);
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
            'index' => ListConsumableTransfers::route('/'),
            'create' => CreateConsumableTransfer::route('/create'),
            'edit' => EditConsumableTransfer::route('/{record}/edit'),
        ];
    }
}
