<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
// use Filament\Tables\Actions\Action as TableAction;
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
use Illuminate\Database\Eloquent\Builder;
use Str;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;
use Filament\Schemas\Schema;

use App\Models\User;
use App\Models\Clinic;

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
            // ->recordUrl(null)
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->placeholder('Unassigned')
                    ->sortable()
                    ->searchable()
                    ->visible(fn() => check_role('super_admin'))
                    ->action(
                        ViewAction::make('view_clinic')
                            ->record(fn(User $record) => $record->clinic)
                            ->infolist(
                                fn(Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
                            )
                            ->modal()
                            ->modalHeading(fn($record) => $record->clinic?->name ?? 'No Clinic Assigned')
                            ->visible(fn(User $record) => $record->clinic !== null)
                    ),
                TextColumn::make('name')
                    ->label('Name')
                    ->sortable(query: fn($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name'])
                    ->formatStateUsing(fn($record) => trim($record->first_name . ' ' . ($record->last_name ?? ''))),
                // TextColumn::make('state')
                //     ->label('State')
                //     ->state(fn(User $record) => $record->state ?? $record->clinic?->state)
                //     ->formatStateUsing(fn($state) => config('project.indian_states.' . $state, $state))
                //     ->searchable()
                //     ->sortable()
                //     ->badge()
                //     ->color('gray')
                //     ->placeholder('-'),
                TextColumn::make('mobile')->searchable(),
                TextColumn::make('gender')
                    ->label('Gender')
                    ->badge()
                    ->formatStateUsing(fn($state) => match (strtolower($state)) {
                        'male' => '👨 Male',
                        'female' => '👩 Female',
                        default => '❓ Unknown',
                    })
                    ->color(fn($state) => match (strtolower($state)) {
                        'male' => 'info',
                        'female' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-')
                    ->toggleable(),
                BadgeColumn::make('roles.name')
                    ->label('Roles')
                    ->formatStateUsing(fn($state) => ucfirst($state))
                    ->icon(fn($state) => match ($state) {
                        'super_admin' => 'heroicon-o-shield-check',
                        'therapist' => 'heroicon-o-hand-raised',
                        'clinic_manager' => 'heroicon-o-building-office',
                        'doctor' => 'heroicon-o-user-circle',
                        'user' => 'heroicon-o-user',
                        default => 'heroicon-o-user',
                    })
                    ->color(fn($state) => match ($state) {
                        'super_admin' => 'danger',
                        'therapist' => 'success',
                        'clinic_manager' => 'info',
                        'doctor' => 'warning',
                        'user' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('email')->label('Email address')->searchable()->toggleable()->placeholder('-'),
                ToggleColumn::make('is_active')
                    ->label('Status')
                    ->onIcon('heroicon-o-bolt')
                    ->offIcon('heroicon-o-power')
                    ->offColor('dark-danger')
                    ->onColor('success')
                    ->sortable()
                    // ->disabled(fn () => ! auth()->user()?->can('toggle_user_status'))
                    // ->visible(auth()->user()->can('toggle_user_status'))
                    ->afterStateUpdated(function ($state, $record) {
                        if (!auth()->user()->can('toggle_user_status')) {
                            Notification::make()
                                ->title('Access Denied')
                                ->body('You do not have permission to update user status.')
                                ->danger()
                                ->send();

                            $record->is_active = !$state;
                            $record->save();

                            return;
                        }

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

                SelectFilter::make('clinic')
                    ->relationship('clinic', 'name')
                    ->searchable()
                    ->preload(),

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
            ->filtersFormColumns(2)
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
            ->filtersTriggerAction(fn(Action $action) => $action->button()->label('Filters')->color('primary')->icon('heroicon-o-funnel'))
            ->recordActions([
                ActionGroup::make([
                    Action::make('new_assessment')
                        ->label('Facial Assessment')
                        ->icon('heroicon-m-plus')
                        ->action(function ($record) {
                            $assessmentUrl = new_assessment($record);
                            return redirect($assessmentUrl);
                        })
                        ->requiresConfirmation(),

                    Action::make('new_iv_assessment')
                        ->label('IV Assessment')
                        ->icon('heroicon-m-plus')
                        ->action(function ($record) {
                            $assessmentUrl = new_assessment($record, 'iv');
                            return redirect($assessmentUrl);
                        })
                        ->requiresConfirmation(),

                    Action::make('new_pigmentation_assessment')
                        ->label('Pigmentation Assessment')
                        ->icon('heroicon-m-plus')
                        ->action(function ($record) {
                            $assessment = \App\Models\Assessment::create([
                                'user_id' => $record->id,
                                'assessment_type' => 'pigmentation',
                                'status' => \App\Enums\AssessmentStatus::InProgress,
                            ]);
                            $assessmentUrl = new_assessment($record, 'pigmentation');
                            $assessmentUrl .= '&assessment_id=' . $assessment->id;
                            return redirect($assessmentUrl);
                        })
                        ->requiresConfirmation(),
                ])
                    ->label('Start Assessment')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->button()
                    ->visible(fn($record) => $record->hasRole('client')),


                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make()
                        ->successNotification(
                            Notification::make()
                                ->title('Client Restored 🎉')
                                ->body('The selected client have been restored successfully.')
                                ->success()
                        ),
                    DeleteAction::make()
                        ->successNotification(function ($record) {
                            return Notification::make()
                                ->title('Client Deleted 🎉')
                                ->body("The client **{$record->name}** has been removed successfully.")
                                ->success();
                        }),

                    Action::make('holiday')
                        ->label('Manage Holidays')
                        ->visible(fn($record) => $record->hasRole('therapist'))
                        ->icon('heroicon-o-no-symbol')
                        // ->iconButton()
                        ->color('danger')
                        ->tooltip('Manage Holidays')
                        ->url(fn($record) => route('filament.admin.resources.users.holidays', ['record' => $record])),

                    Action::make('assessment')
                        ->label('Manage Assessments')
                        ->visible(fn($record) => $record->hasRole('client'))
                        ->icon('heroicon-o-clipboard-document')
                        // ->iconButton()
                        ->color('info')
                        // ->tooltip('Manage Assessments')
                        ->url(fn($record) => route('filament.admin.resources.users.assessments', ['record' => $record])),

                    Action::make('packages')
                        ->label('Manage Packages')
                        ->visible(fn($record) => $record->hasRole('client'))
                        ->icon('heroicon-o-rectangle-stack')
                        // ->iconButton()
                        ->color('warning')
                        // ->tooltip('Manage Packages')
                        ->url(fn($record) => route('filament.admin.resources.users.packages', ['record' => $record])),

                    Action::make('invoice')
                        ->label('Manage Invoices')
                        ->visible(fn($record) => $record->hasRole('client'))
                        ->icon('heroicon-o-document-text')
                        // ->iconButton()
                        ->color('success')
                        // ->tooltip('Manage Invoices')
                        ->url(fn($record) => route('filament.admin.resources.users.invoices', ['record' => $record])),

                    Action::make('loyalty_points')
                        ->label('Manage Loyalty Points')
                        ->visible(fn($record) => $record->hasRole('client'))
                        ->icon('heroicon-o-star')
                        ->color('primary')
                        ->url(fn($record) => route('filament.admin.resources.users.loyalty_points', ['record' => $record])),

                    Action::make('weekly_schedule')
                        ->label('Manage Weekly Schedule')
                        ->visible(fn($record) => $record->hasRole('therapist'))
                        ->icon('heroicon-o-calendar-days')
                        // ->iconButton()
                        ->color('success')
                        ->tooltip('Manage Weekly Schedule')
                        ->url(fn($record) => route('filament.admin.resources.users.weekly_schedule', ['record' => $record])),

                    Action::make('permissions')
                        ->label('Manage Permissions')
                        ->icon('heroicon-o-key')
                        ->color('success')
                        // ->iconButton()
                        ->slideOver() // or ->modalHeading("Manage permissions")
                        // ->modalHeading("Manage permissions")
                        ->form([
                            CheckboxList::make('permissions')
                                ->label('Manage Permissions')
                                ->options(Permission::all()->pluck('name', 'id'))
                                ->columns(3)
                                ->searchable()
                                ->bulkToggleable()
                                ->default(fn($record) => $record->permissions()->pluck('id')->toArray()),
                        ])
                        // ->form(function () {
                        //     $permissions = Permission::all()->groupBy(function ($perm) {
                        //         // detect group by suffix (after ":") if exists
                        //         if (str_contains($perm->name, ':')) {
                        //             return Str::after($perm->name, ':'); // e.g. "User", "Role"
                        //         }

                        //         // detect custom permissions (no separator)
                        //         if (str_starts_with($perm->name, 'toggle_')) {
                        //             return 'Custom Permissions';
                        //         }

                        //         // detect widgets (common naming convention: "View:Something")
                        //         if (str_starts_with($perm->name, 'View:')) {
                        //             return 'Widgets';
                        //         }

                        //         return 'Misc';
                        //     });

                        //     return $permissions->map(function ($group, $key) {

                        //         return Section::make(ucfirst($key))
                        //             ->schema([
                        //                 CheckboxList::make("permissions_{$key}")
                        //                     ->label('Manage Permissions')
                        //                     ->options($group->pluck('name', 'id'))
                        //                     ->columns(3)
                        //                     ->bulkToggleable()
                        //                     ->default(fn($record) => $record->permissions()->pluck('id')->toArray()),
                        //                 // CheckboxList::make("permissions_{$key}")
                        //                 //     ->label("Manage {$key}")
                        //                 //     ->options($group->pluck('name', 'id'))
                        //                 //     ->columns(3)
                        //                 //     ->bulkToggleable()
                        //                 //     ->default(fn ($record) =>
                        //                 //         $record->permissions()->pluck('id')->toArray()
                        //                 //     ),
                        //             ])
                        //             ->collapsible()
                        //             ->collapsed();
                        //     })->values()->toArray();
                        // })
                        ->visible(fn($record) => ($record->hasRole('therapist') || $record->hasRole('clinic_manager')) && auth()->user()?->can('toggle_user_permissions'))
                        ->action(function (array $data, $record) {
                            if (!auth()->user()->can('toggle_user_permissions')) {
                                Notification::make()
                                    ->title('Access Denied')
                                    ->body('You do not have permission to update user status.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            if ($record->hasRole('super_admin')) {
                                Notification::make()
                                    ->title('Super Admin Permissions Locked')
                                    ->body('You cannot modify permissions for Super Admin.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $permissions = collect($data)
                                ->filter(fn($value, $key) => str_starts_with($key, 'permissions_'))
                                ->flatten()
                                ->filter()
                                ->toArray();

                            $record->syncPermissions($permissions ?? []);

                            Notification::make()
                                ->title('Permissions updated')
                                ->body("Permissions for role **{$record->name}** have been saved successfully.")
                                ->success()
                                ->send();
                        }),
                ]),

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
            ->groups(array_filter([
                auth()->user()->hasRole(config('project.roles.super_admin', 'super_admin')) ?
                Group::make('clinic_id')
                    ->label('Clinic Name')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn($record) => $record->clinic_id ?? 'no_clinic')
                    ->getTitleFromRecordUsing(fn($record) => $record->clinic?->name ?? 'Unassigned')
                : null,
                // Group::make('roles.name')->label('Role Name')->collapsible(),
                Group::make('gender')->label('Gender')->collapsible(),
                Group::make('created_at')->date(),
            ]))
            // ->groupingSettingsInDropdownOnDesktop()
            ->emptyStateDescription('Once you create your first user, it will appear here.');
        // ->contentGrid([
        //     'md' => 2,
        //     'xl' => 3,
        // ])
    }
}
