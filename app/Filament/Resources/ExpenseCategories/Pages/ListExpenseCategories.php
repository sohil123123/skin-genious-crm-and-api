<?php

namespace App\Filament\Resources\ExpenseCategories\Pages;

use App\Filament\Resources\ExpenseCategories\ExpenseCategoryResource;
use App\Filament\Resources\ExpenseCategories\Schemas\ExpenseCategoryForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpenseCategories extends ListRecords
{
    protected static string $resource = ExpenseCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->schema(ExpenseCategoryForm::components())
                ->modalWidth('3xl')
                ->createAnother(false),
        ];
    }
}
