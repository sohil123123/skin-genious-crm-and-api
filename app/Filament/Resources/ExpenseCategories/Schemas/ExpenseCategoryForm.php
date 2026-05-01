<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use App\Enums\ExpenseCategoryType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::components());
    }

    public static function components(): array
    {
        return [
            Section::make('Category Details')
                ->icon('heroicon-o-tag')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label('Category Name')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-tag')
                            ->placeholder('Inventory Purchase'),

                        // TextInput::make('slug')
                        //     ->maxLength(255)
                        //     ->unique(ignoreRecord: true)
                        //     ->prefixIcon('heroicon-o-link')
                        //     ->helperText('Leave empty to generate from the category name.'),

                        Select::make('type')
                            ->options(ExpenseCategoryType::options())
                            ->default(ExpenseCategoryType::Variable->value)
                            ->required()
                            ->native(false)
                            ->prefixIcon('heroicon-o-adjustments-horizontal'),

                        // TextInput::make('sort_order')
                        //     ->numeric()
                        //     ->default(0)
                        //     ->minValue(0)
                        //     ->prefixIcon('heroicon-o-bars-arrow-down'),

                        // Toggle::make('is_active')
                        //     ->label('Active')
                        //     ->default(true)
                        //     ->inline(false),

                        Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Optional internal note about what belongs in this category.')
                            ->columnSpanFull(),
                    ]),
                ]),
        ];
    }
}
