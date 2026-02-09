<?php

namespace App\Filament\Resources\StockTransactions;

use App\Filament\Resources\StockTransactions\Pages\CreateStockTransaction;
use App\Filament\Resources\StockTransactions\Pages\EditStockTransaction;
use App\Filament\Resources\StockTransactions\Pages\ListStockTransactions;
use App\Filament\Resources\StockTransactions\Schemas\StockTransactionForm;
use App\Filament\Resources\StockTransactions\Tables\StockTransactionsTable;
use App\Models\StockTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StockTransactionResource extends Resource
{
    protected static ?string $model = StockTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

     protected static string | UnitEnum | null $navigationGroup = 'Inventory';

     protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return StockTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockTransactionsTable::configure($table);
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
            'index' => ListStockTransactions::route('/'),
            'create' => CreateStockTransaction::route('/create'),
        ];
    }
}
