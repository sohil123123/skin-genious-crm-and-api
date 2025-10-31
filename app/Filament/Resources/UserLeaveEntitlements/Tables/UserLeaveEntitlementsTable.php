<?php

namespace App\Filament\Resources\UserLeaveEntitlements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Tables\Table;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Notifications\Notification;

use App\Enums\HolidayType;

use App\Models\UserLeaveEntitlement;

class UserLeaveEntitlementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')->searchable(['first_name', 'last_name']),
                TextColumn::make('year')->sortable(),
                TextColumn::make('leave_type')->badge(),
                TextColumn::make('entitlement')->numeric()->sortable(),
                TextColumn::make('taken')->numeric()->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('leave_type')
                    ->label('Leave Type')
                    ->options(HolidayType::class)
                    ->placeholder('All'),
                SelectFilter::make('year')
                    ->options(fn () => UserLeaveEntitlement::query()->distinct('year')->pluck('year', 'year')->toArray())
                    ->label('Year')
                    ->placeholder('All'),
            ],
            layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Leave Entitlement Deleted 🎉')
                            ->body("The Entitlement has been removed successfully.")
                            ->success();
                    }),
            ])
            ->groups([
                Group::make('user.first_name')->label('User')->collapsible(),
                Group::make('year')->label('Year')->collapsible(),
                Group::make('leave_type')->label('Leave Type')->collapsible(),
                Group::make('created_at')->date(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

}
