<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Grid::make(3)->schema([
                            // Select::make('product_id')
                            //     ->relationship('product', 'name')
                            //     ->searchable()
                            //     ->preload()
                            //     ->required()
                            //     ->placeholder('Select product'),
                            Select::make('type')
                                ->options([
                                    'purchase' => 'Purchase (Add)',
                                    // 'sale' => 'Sale (Deduct)',
                                    'return_in' => 'Return In (Add)',
                                    'return_out' => 'Return Out (Deduct)',
                                    // 'adjustment_add' => 'Adjustment (Add)',
                                    // 'adjustment_remove' => 'Adjustment (Deduct)',
                                    'damage' => 'Damage (Deduct)',
                                    'internal_use' => 'Internal Use (Deduct)',
                                ])
                                ->required()
                                ->placeholder('Select transaction type'),
                            TextInput::make('quantity')
                                ->numeric()
                                ->required()
                                ->default(1)
                                ->placeholder('Enter quantity'),
                        ]),
                        Grid::make(1)->schema([
                            Textarea::make('note')
                                ->columnSpanFull()
                                ->placeholder('Enter transaction notes (optional)'),
                        ]),
                    ])
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->columns([
                TextColumn::make('quantity')->badge()->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'purchase', 'return_in', 'adjustment_add', 'initial' => 'success',
                        'sale', 'return_out', 'adjustment_remove', 'damage', 'internal_use' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('note')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'purchase' => 'Purchase (Add)',
                        'sale' => 'Sale (Deduct)',
                        'return_in' => 'Return In (Add)',
                        'return_out' => 'Return Out (Deduct)',
                        'damage' => 'Damage (Deduct)',
                        'internal_use' => 'Internal Use (Deduct)',
                    ]),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->visible(fn ($livewire) => in_array($livewire->getOwnerRecord()->type, ['product', 'iv_product'])),
                // AssociateAction::make(),
            ])
            ->recordActions([
                // EditAction::make(),
                // DissociateAction::make(),
                // DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DissociateBulkAction::make(),
                    // DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first transaction, it will appear here.');
    }
}
