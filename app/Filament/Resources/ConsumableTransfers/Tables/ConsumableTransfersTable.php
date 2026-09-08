<?php

namespace App\Filament\Resources\ConsumableTransfers\Tables;

use App\Filament\Exports\ConsumableTransferExporter;
use App\Models\ConsumableTransfer;
use App\Services\ConsumableTransferPdfService;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ConsumableTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('clinic.name')
                    ->searchable()
                    ->sortable()
                    ->visible(fn() => auth()->user()->hasRole('super_admin')),
                TextColumn::make('transfer_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->badge(),
                TextColumn::make('items_summary')
                    ->label('Product Summary')
                    ->state(fn(ConsumableTransfer $record): string => $record->items->map(fn($item) => "{$item->product->name} (x{$item->quantity_used})")->join(', '))
                    ->limit(40)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('items.product', fn($q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->color('primary')
                    ->size('xs')
                    ->action(
                        Action::make('view_items')
                            ->modalHeading('Consumption Details')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Close')
                            ->infolist([
                                RepeatableEntry::make('items')
                                    ->label('Items List')
                                    ->schema([
                                        TextEntry::make('product.name')->label('Product Name'),
                                        TextEntry::make('quantity_used')->label('Quantity Used'),
                                    ])
                                    ->columns(2)
                            ])
                    ),
                TextColumn::make('creator.first_name')
                    ->label('Created By')
                    ->sortable(),
                TextColumn::make('notes')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
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

                Filter::make('transfer_date_filter')
                    ->form([
                        Grid::make(2)->schema([
                            DatePicker::make('from')
                                ->label('Date from'),
                            DatePicker::make('until')
                                ->label('Date until'),
                        ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('transfer_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('transfer_date', '<=', $date),
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
                    Action::make('download_pdf')
                        ->label('Download PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(fn(ConsumableTransfer $record, ConsumableTransferPdfService $service) => $service->download($record)),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ConsumableTransferExporter::class)
                        ->label('Export Selected'),
                ]),
            ]);
    }
}
