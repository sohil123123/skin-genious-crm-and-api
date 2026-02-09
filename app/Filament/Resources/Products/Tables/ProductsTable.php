<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
// use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use App\Models\StockTransaction;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'product' => 'info',
                        'service' => 'success',
                        'iv_product' => 'warning',
                    }),
                TextColumn::make('sell_price')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('gst')
                    ->label('GST (%)')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('unit')->sortable()->placeholder('N/A'),
                TextColumn::make('stock')->badge()->sortable(),
                TextColumn::make('sku')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onColor('success')
                    ->offColor('danger')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'product' => 'Product',
                        'service' => 'Service',
                        'iv_product' => 'IV Product',
                    ])
                    ->searchable(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                Action::make('transactions')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->iconButton()
                    ->color('primary')
                    ->tooltip('View Stock Transactions')
                    ->url(fn ($record) => route('filament.admin.resources.products.transactions', ['record' => $record])),

                Action::make('addStock')
                    ->label('Add Stock')
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->form([
                        Group::make()
                            ->schema([
                                Section::make('Stock Transaction Details')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('type')
                                                ->options([
                                                    'purchase' => 'Purchase',
                                                    'return_in' => 'Return In',
                                                    // 'adjustment_add' => 'Adjustment (Add)',
                                                ])
                                                ->default('purchase')
                                                ->required(),
                                            TextInput::make('quantity')
                                                ->numeric()
                                                ->required()
                                                ->label('Quantity to Add')
                                                ->default(1),
                                        ]),
                                        Grid::make(1)->schema([
                                            Textarea::make('note')
                                                ->placeholder('Optional note')
                                                ->columnSpanFull(),
                                        ]),
                                    ]),
                            ]),
                    ])
                    ->action(function (Product $record, array $data): void {
                        StockTransaction::create([
                            'product_id' => $record->id,
                            'quantity' => $data['quantity'],
                            'type' => $data['type'],
                            'note' => $data['note'],
                        ]);
                        
                        Notification::make()
                            ->title('Stock Added 🎉')
                            ->body('The stock have been successfully added.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Product $record) => $record->type !== 'service'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}
