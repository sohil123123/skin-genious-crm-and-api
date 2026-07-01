<?php

namespace App\Filament\Resources\ExpenseCategories\Tables;

use App\Enums\ExpenseCategoryType;
use App\Filament\Resources\ExpenseCategories\Schemas\ExpenseCategoryForm;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Enums\FiltersLayout;

class ExpenseCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Category')
                    ->icon('heroicon-o-tag')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn($state): string => ($state instanceof ExpenseCategoryType ? $state : ExpenseCategoryType::tryFrom((string) $state))?->label() ?? ucfirst((string) $state))
                    ->color(fn($state): string => ($state instanceof ExpenseCategoryType ? $state->value : (string) $state) === ExpenseCategoryType::Fixed->value ? 'warning' : 'info')
                    ->sortable(),

                TextColumn::make('expenses_sum_amount')
                    ->label('Total Spent')
                    ->money('INR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('expenses_count')
                    ->label('Expenses')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onIcon('heroicon-o-bolt')
                    ->offIcon('heroicon-o-power')
                    ->offColor('dark-danger')
                    ->onColor('success')
                    // ->sortable()
                    // ->requiresConfirmation()
                    ->disabled(fn() => !auth()->user()?->can('toggle_user_status'))
                    // ->visible(auth()->user()->can('toggle_user_status'))
                    ->afterStateUpdated(function ($state, $record) {
                        $record->is_active = $state;
                        $record->save();

                        Notification::make()
                            ->title($record->is_active ? 'Category Activated ✅' : 'Category Deactivated 🚫')
                            ->body("Category status has been updated successfully.")
                            ->success()
                            ->send();

                    }),

                // TextColumn::make('sort_order')
                //     ->label('Sort')
                //     ->sortable()
                //     ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(ExpenseCategoryType::options()),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn(Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->icon('heroicon-o-pencil-square')
                        ->schema(ExpenseCategoryForm::components())
                        ->modalWidth('3xl'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
