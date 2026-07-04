<?php

namespace App\Filament\Resources\UserLeaveEntitlements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ActionGroup;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Tables\Table;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Notifications\Notification;

use App\Enums\LeaveType;

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
                TextColumn::make('year')->badge()->color('info')->sortable(),
                TextColumn::make('leave_type')->badge(),
                TextColumn::make('total_allowed')->badge()->color('success')->sortable(),
                TextColumn::make('used')->badge()->color('danger')->sortable(),
                TextColumn::make('remaining')->badge()->color('warning')->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(
                [
                    SelectFilter::make('user')
                        ->relationship(
                            name: 'user',
                            titleAttribute: 'first_name',
                            modifyQueryUsing: fn($query) =>
                            $query->whereHas('roles', fn($q) => $q->where('name', 'therapist'))
                        ),
                    // ->searchable()
                    // ->preload(),
                    SelectFilter::make('leave_type')
                        ->label('Leave Type')
                        ->options(LeaveType::class)
                        ->placeholder('All'),
                    SelectFilter::make('year')
                        ->options(fn() => UserLeaveEntitlement::query()->distinct('year')->pluck('year', 'year')->toArray())
                        ->label('Year')
                        ->placeholder('All'),
                ],
                layout: FiltersLayout::Modal
            )
            ->filtersFormColumns(3)
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->successNotification(function ($record) {
                            return Notification::make()
                                ->title('Leave Entitlement Deleted 🎉')
                                ->body("The Entitlement has been removed successfully.")
                                ->success();
                        }),
                ])
            ])
            ->groups([
                Group::make('user_id')
                    ->label('Therapist')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn($record) => $record->user_id ?? 'no_therapist')
                    ->getTitleFromRecordUsing(fn($record) => $record->therapist?->first_name ?? 'Unassigned'),
                Group::make('year')->label('Year')->collapsible(),
                Group::make('leave_type')->label('Leave Type')->collapsible(),
                Group::make('created_at')->date(),
            ])
            ->defaultGroup('user_id')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first user leave entitlements, it will appear here.');
    }

}
