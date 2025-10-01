<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
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
use Filament\Forms\Components\CheckboxList;
use Spatie\Permission\Models\Permission;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;

use Str;

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
                // TextColumn::make('gender')
                //     ->label('Gender')
                //     ->badge()
                //     ->icon(fn ($state) => match ($state) {
                //         'Male'   => 'heroicon-m-user',
                //         'Female' => 'heroicon-m-user-circle',
                //         default  => 'heroicon-m-question-mark-circle',
                //     })
                //     ->color(fn ($state) => match ($state) {
                //         'Male'   => 'info',
                //         'Female' => 'pink',
                //         default  => 'gray',
                //     })
                //     ->placeholder('-')
                //     ->toggleable(),
                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match (strtolower($state)) {
                        'male'   => '👨 Male',
                        'female' => '👩 Female',
                        default  => '❓ Unknown',
                    })
                    ->color(fn ($state) => match (strtolower($state)) {
                        'male'   => 'info',
                        'female' => 'danger',
                        default  => 'gray',
                    })
                    ->placeholder('-')
                    ->toggleable(),
                // TextColumn::make('roles.name')->badge()->color('primary')->searchable()->sortable()->toggleable(),
                BadgeColumn::make('roles.name')
                    ->label('Roles')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->icon(fn ($state) => match ($state) {
                        'admin'          => 'heroicon-o-shield-check',
                        'therapist'      => 'heroicon-o-hand-raised',
                        'clinic_manager' => 'heroicon-o-building-office',
                        'doctor'         => 'heroicon-o-user-circle',
                        'user'           => 'heroicon-o-user',
                        default          => 'heroicon-o-user',
                    })
                    ->color(fn ($state) => match ($state) {
                        'admin'          => 'danger',
                        'therapist'      => 'success',
                        'clinic_manager' => 'info',
                        'doctor'         => 'warning',
                        'user'           => 'gray',
                        default          => 'gray',
                    }),
                TextColumn::make('email')->label('Email address')->searchable()->toggleable()->placeholder('-'),
                // ToggleColumn::make('is_active')
                //     ->label('Status')
                //     ->toggleable()
                //     ->sortable()
                //     // ->disabled(fn () => ! auth()->user()?->can('toggle_user_status'))
                //     // ->visible(auth()->user()->can('toggle_user_status'))
                //     ->action(function ($record) {
                //         if (! auth()->user()->can('toggle_user_status')) {
                //             Notification::make()
                //                 ->title('Access Denied')
                //                 ->body('You do not have permission to update user status.')
                //                 ->danger()
                //                 ->send();
                //             return;
                //         }

                //         $record->update([
                //             'is_active' => ! $record->is_active,
                //         ]);

                //         Notification::make()
                //             ->title('Status Updated')
                //             ->body("User status has been updated successfully.")
                //             ->success()
                //             ->send();
                //     }),
                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onIcon('heroicon-o-bolt')
                    ->offIcon('heroicon-o-power')
                    ->offColor('dark-danger')
                    ->sortable()
                    // ->disabled(fn () => ! auth()->user()?->can('toggle_user_status'))
                    // ->visible(auth()->user()->can('toggle_user_status'))
                    ->afterStateUpdated(function ($state, $record) {
                        // This runs whenever toggle is changed
                        if (! auth()->user()->can('toggle_user_status')) {
                            Notification::make()
                                ->title('Access Denied')
                                ->body('You do not have permission to update user status.')
                                ->danger()
                                ->send();

                            // revert change
                            $record->is_active = ! $state;
                            $record->save();

                            return;
                        }

                        // Save the new state
                        $record->is_active = $state;
                        $record->save();

                        Notification::make()
                            ->title('Status Updated')
                            ->body("User status has been updated successfully.")
                            ->success()
                            ->send();
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

                SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                    ])
                    ->searchable(),

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
                RestoreAction::make()
                    ->successNotification(
                        Notification::make()
                            ->title('User Restored 🎉')
                            ->body('The selected users have been restored successfully.')
                            ->success()
                    ),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('User Deleted 🎉')
                            ->body("The User **{$record->name}** has been removed successfully.")
                            ->success();
                    }),
                Action::make('permissions')
                    ->label('Permissions')
                    ->icon('heroicon-o-key')
                    ->color('success')
                    ->slideOver() // or ->modalHeading("Manage permissions")
                    // ->modalHeading("Manage permissions")
                    // ->form([
                    //     CheckboxList::make('permissions')
                    //         ->label('Manage Permissions')
                    //         ->options(Permission::all()->pluck('name', 'id'))
                    //         ->columns(3)
                    //         ->searchable()
                    //         ->bulkToggleable()
                    //         ->default(fn($record) => $record->permissions()->pluck('id')->toArray()),
                    // ])
                    ->form(function () {
                        $permissions = Permission::all()->groupBy(function ($perm) {
                            // detect group by suffix (after ":") if exists
                            if (str_contains($perm->name, ':')) {
                                return Str::after($perm->name, ':'); // e.g. "User", "Role"
                            }

                            // detect custom permissions (no separator)
                            if (str_starts_with($perm->name, 'toggle_')) {
                                return 'Custom Permissions';
                            }

                            // detect widgets (common naming convention: "View:Something")
                            if (str_starts_with($perm->name, 'View:')) {
                                return 'Widgets';
                            }

                            return 'Misc';
                        });

                        return $permissions->map(function ($group, $key) {
                            return Section::make(ucfirst($key))
                                ->schema([
                                    CheckboxList::make("permissions_{$key}")
                                        ->label("Manage {$key}")
                                        ->options($group->pluck('name', 'id'))
                                        ->columns(3)
                                        ->bulkToggleable()
                                        ->default(fn ($record) =>
                                            $record->permissions()->pluck('id')->toArray()
                                        ),
                                ])
                                ->collapsible()
                                ->collapsed();
                        })->values()->toArray();
                    })
                    ->visible(fn () => auth()->user()?->can('toggle_user_permissions'))
                    ->action(function ($record) {
                        if (! auth()->user()->can('toggle_user_permissions')) {
                            Notification::make()
                                ->title('Access Denied')
                                ->body('You do not have permission to update user status.')
                                ->danger()
                                ->send();
                            return;
                        }

                        if ($record->name === 'admin')
                            return; // Do nothing for admin role

                        $record->syncPermissions($data['permissions'] ?? []);

                        // ✅ Show notification after saving
                        Notification::make()
                            ->title('Permissions updated')
                            ->body("Permissions for role **{$record->name}** have been saved successfully.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        // ->visible(fn () => auth()->user()?->can('DeleteAny:User'))
                        ->successNotification(
                            Notification::make()
                                ->title('Users Deleted 🎉')
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
