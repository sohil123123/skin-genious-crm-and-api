<?php

namespace App\Filament\Resources\Expenses\Schemas;

use App\Enums\ExpensePaymentMethod;
use App\Enums\ExpenseReferenceType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::components());
    }

    public static function components(): array
    {
        return [
            Section::make('Expense Details')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Grid::make(3)->schema([
                        auth()->user()->hasRole('super_admin')
                            ? Select::make('clinic_id')
                                ->relationship('clinic', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false)
                            : Hidden::make('clinic_id')
                                ->default(fn () => auth()->user()->clinic_id),

                        Select::make('expense_category_id')
                            ->label('Category')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->prefixIcon('heroicon-o-tag'),

                        DatePicker::make('expense_date')
                            ->label('Expense Date')
                            ->default(now())
                            ->required()
                            ->prefixIcon('heroicon-o-calendar'),

                        TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->prefix('Rs.')
                            ->placeholder('0.00'),

                        Select::make('payment_method')
                            ->options(ExpensePaymentMethod::options())
                            ->default(ExpensePaymentMethod::Cash->value)
                            ->required()
                            ->native(false)
                            ->prefixIcon('heroicon-o-credit-card'),

                        Select::make('reference_type')
                            ->options(ExpenseReferenceType::options())
                            ->default(ExpenseReferenceType::Manual->value)
                            ->required()
                            ->native(false)
                            ->prefixIcon('heroicon-o-link'),

                        TextInput::make('reference_id')
                            ->label('Reference ID')
                            ->numeric()
                            ->visible(fn ($get) => $get('reference_type') !== ExpenseReferenceType::Manual->value)
                            ->disabled(fn ($record) => $record?->reference_type === ExpenseReferenceType::Purchase->value),

                        TextInput::make('reference_number')
                            ->label('Reference Number')
                            ->placeholder('Invoice, bill, receipt or transaction reference')
                            ->maxLength(255),

                        TextInput::make('vendor_name')
                            ->label('Vendor / Payee')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                ])
                ->columns(1),
        ];
    }
}
