<?php

namespace App\Filament\Resources\ExpenseCategories;

use App\Filament\Resources\ExpenseCategories\Pages\ListExpenseCategories;
use App\Filament\Resources\ExpenseCategories\Schemas\ExpenseCategoryForm;
use App\Filament\Resources\ExpenseCategories\Tables\ExpenseCategoriesTable;
use App\Models\ExpenseCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole(['super_admin', 'clinic_manager']);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSum('expenses', 'amount')
            ->withCount('expenses');
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpenseCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpenseCategories::route('/'),
        ];
    }
}
