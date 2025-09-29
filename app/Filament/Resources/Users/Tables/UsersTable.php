<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Illuminate\Contracts\View\View;
use Filament\Notifications\Notification;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Schemas\Components\Section;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            // ->placeholder(fn () => view('custom-table-placeholder', [
            //     'thead' => $table->renderHeader(), // Or manually render if needed
            //     'rows' => 5,
            //     'widths' => ['w-24', 'w-16', 'w-32', 'w-20', 'w-12', 'w-28', 'w-20'],
            // ]))
            ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name'])
                    ->formatStateUsing(fn ($record) => trim($record->first_name . ' ' . ($record->last_name ?? ''))),
                TextColumn::make('mobile')->searchable(),
                TextColumn::make('gender')->badge()->placeholder('-')->toggleable(),
                TextColumn::make('roles.name')->badge()->color('primary')->searchable()->sortable()->toggleable(),
                TextColumn::make('email')->label('Email address')->searchable()->toggleable()->placeholder('-'),
                ToggleColumn::make('is_active')->label('Status')->toggleable()->sortable()
                    ->action(function ($record) {
                        $record->update([
                            'is_active' => ! $record->is_active,
                        ]);
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),

                SelectFilter::make('is_active')
                    ->options([
                        1 => 'Active',
                        0 => 'Deactive',
                    ])
                    ->label('Status')
                    ->searchable(),

                // SelectFilter::make('roles')
                //     ->relationship('roles', 'name')
                //     ->multiple()
                //     ->preload()
                //     ->searchable(),
            ], layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            // ->filtersFormSchema(fn (array $filters): array => [
            //     Section::make('Visibility')
            //         ->description('These filters affect the visibility of the records in the table.')
            //         ->schema([
            //             $filters['is_active'],
            //             $filters['roles'],
            //         ])
            //         ->columns(2)
            //         ->columnSpanFull(),
            //     // $filters['author'],
            // ])
            ->filtersTriggerAction(fn (Action $action) => $action->button()->label('Filters'))
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('User Deleted 🎉')
                            ->body("The User **{$record->name}** has been removed successfully.")
                            ->success();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotification(
                            Notification::make()
                                ->title('User Deleted 🎉')
                                ->body('The selected users have been deleted successfully.')
                                ->success()
                        ),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->groups([
                // Group::make('roles.name')->label('Role Name')->collapsible(),
                Group::make('gender')->label('Gender')->collapsible(),
                Group::make('created_at')->date(),
            ])
            // ->groupingSettingsInDropdownOnDesktop()
            ->emptyStateDescription('Once you create your first user, it will appear here.');
            // ->contentGrid([
            //     'md' => 2,
            //     'xl' => 3,
            // ])
    }
}
