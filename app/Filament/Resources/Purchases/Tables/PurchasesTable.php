<?php

namespace App\Filament\Resources\Purchases\Tables;

use App\Filament\Exports\PurchaseExporter;
use App\Models\Purchase;
use App\Services\PurchasePdfService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),
                TextColumn::make('supplier_name')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('items_summary')
                    ->label('Product Summary')
                    ->state(fn(Purchase $record): string => $record->items->map(fn($item) => "{$item->product->name} (x{$item->quantity})")->join(', '))
                    ->limit(30)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('items.product', fn($q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->color('primary')
                    ->size('xs')
                    ->action(
                        Action::make('view_items')
                            ->modalHeading('Purchase Items')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->infolist([
                                RepeatableEntry::make('items')
                                    ->label('Items List')
                                    ->schema([
                                        TextEntry::make('product.name')->label('Product Name'),
                                        TextEntry::make('quantity')->label('Quantity'),
                                        TextEntry::make('purchase_price')
                                            ->label('Rate')
                                            ->money('INR'),
                                        TextEntry::make('total')
                                            ->label('Amount')
                                            ->money('INR'),
                                    ])
                                    ->columns(4)
                            ])
                    ),
                TextColumn::make('purchase_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('INR')
                    ->sortable(),
                TextColumn::make('payment_mode')
                    ->searchable()
                    ->badge(),
                TextColumn::make('status')
                    ->searchable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Partial' => 'warning',
                        'Unpaid' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('clinic_id')
                    ->relationship('clinic', 'name', fn ($query) => $query->active())
                    ->searchable()
                    ->preload()
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),

                Filter::make('purchase_date')
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make('from')
                                ->label('Created from'),
                            DatePicker::make('until')
                                ->label('Created until'),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('purchase_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From ' . Carbon::parse($data['from'])->toFormattedDateString())
                                ->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Until ' . Carbon::parse($data['until'])->toFormattedDateString())
                                ->removeField('until');
                        }
                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary'))
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('download')
                        ->label('Download PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(fn(Purchase $record, PurchasePdfService $service) => $service->download($record)),
                ]),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PurchaseExporter::class)
                        ->label('Export Selected'),
                ]),
            ]);
    }
}
