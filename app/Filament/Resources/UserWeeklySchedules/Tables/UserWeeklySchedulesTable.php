<?php

namespace App\Filament\Resources\UserWeeklySchedules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Indicator;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Support\Icons\Heroicon;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

use App\Models\Clinic;
use App\Models\User;
use App\Models\UserWeeklySchedule;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserWeeklySchedulesTable
{
    public static function configure(Table $table): Table
    {
        $filters = [];
        if (check_role('super_admin') || check_role(['clinic_manager', 'clinic_head'])) {
            $filters[] = Filter::make('advanced')
                // ->visible(fn () => check_role('super_admin'))
                ->label('Advanced Filters')
                ->form([
                    Section::make('Clinic & Therapist Filters')
                        ->icon('heroicon-o-building-office-2')
                        ->description('Filter by clinic and therapist users.')
                        ->schema([
                            Grid::make(1) // 2-column grid for better space utilization
                                ->schema([
                                    // Clinic
                                    Select::make('clinic_id')
                                        ->label('Clinic')
                                        ->relationship('clinic', 'name')
                                        ->visible(check_role('super_admin'))
                                        // ->searchable()
                                        // ->preload()
                                        ->placeholder('Select clinic')
                                        // ->native(false)
                                        ->live(), // Triggers reactive updates on dependents

                                    // User
                                    Select::make('user_id')
                                        ->label('Therapist')
                                        ->options(function (callable $get) {
                                            $clinicId = $get('clinic_id');
                                            if (!$clinicId)
                                                $clinicId = auth()->user()->clinic_id;

                                            return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                        })
                                        ->reactive()
                                        // ->searchable()
                                        // ->native(false)
                                        ->placeholder('Select Therapist'),

                                    Select::make('day_of_week')
                                        ->options(day_options())
                                        ->required(),
                                ]),
                        ])
                        ->columns(1)
                        ->collapsible(),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
                        ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                        ->when($data['day_of_week'] ?? null, fn ($q, $val) => $q->where('day_of_week', $val));
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if ($data['clinic_id'] ?? null) {
                        $clinic = Clinic::find($data['clinic_id']);
                        if ($clinic) {
                            $indicators[] = Indicator::make('Clinic: ' . $clinic->name)->removeField('clinic_id');
                        }
                    }

                    if ($data['user_id'] ?? null) {
                        $user = User::find($data['user_id']);
                        if ($user) {
                            $indicators[] = Indicator::make('Therapist: ' . $user->name)->removeField('user_id');
                        }
                    }

                    if ($data['day_of_week'] ?? null) {
                        $day = UserWeeklySchedule::dayOptions()[$data['day_of_week']];
                        $indicators[] = Indicator::make('day_of_week: ' . $day)->removeField('day_of_week');
                    }

                    return $indicators;
                });
        }
        else {
            $filters[] = SelectFilter::make('day_of_week')->options(day_options());
        }


        $table = $table
            ->deferLoading()
            // ->recordUrl(null)
            ->defaultSort('day_of_week', 'asc')
            ->columns([
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->visible(fn () => check_role('super_admin'))
                    ->icon('heroicon-o-building-office')
                    ->color('gray')
                    ->placeholder('Unassigned')
                    ->searchable()
                    ->action(
                        ViewAction::make('view_clinic')
                            ->record(fn ($record) => $record->clinic)
                            ->infolist(
                                fn (Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
                            )
                            ->modal()
                            ->modalHeading(fn ($record) => $record->clinic?->name ?? 'No Clinic Assigned')
                            ->visible(fn ($record) => $record->clinic !== null)
                    )
                    ->toggleable(),

                TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->searchable(['first_name', 'last_name'])
                    ->visible(fn () => check_role('super_admin') || check_role(['clinic_manager', 'clinic_head']))
                    ->badge()
                    ->icon('heroicon-o-user')
                    ->color('info'),

                TextColumn::make('day_of_week')
                    ->label('Day')
                    ->badge()
                    ->formatStateUsing(fn ($state) => day_options()[$state])
                    ->color(fn ($state) => day_color((int) $state))
                    ->icon(fn ($state) => match ($state) {
                        7 => 'heroicon-o-sun',
                        default => 'heroicon-o-calendar-days',
                    })
                    ->sortable(),

                TextColumn::make('start_time')->label('Start Time')->badge(),

                TextColumn::make('end_time')->label('End Time')->badge(),

                IconColumn::make('has_overlap')
                    ->label('Has Overlap')
                    ->boolean()
                    ->trueIcon(Heroicon::ExclamationTriangle)
                    ->trueColor('warning')
                    ->falseColor('success'),

                IconColumn::make('is_active')
                    ->label('Working day')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('updated_at')->label('Updated')->since(),
            ])
            ->filters($filters, layout: FiltersLayout::Modal)
            // ->filtersFormColumns(2) // Reduced to 2 for better readability in modal; adjust as needed
            // ->filtersFormWidth('md:max-w-4xl')
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            ->recordActions([
                EditAction::make(),
                // DeleteAction::make(),
            ])
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //     ]),
            // ])
            ->paginated([14, 25, 50, 100, 'all'])
            ->defaultPaginationPageOption(14)
            ->emptyStateDescription('Once you create your first record, it will appear here.');

        if (check_role('super_admin')) {
            $table->groups([
                Group::make('clinic_id')
                    ->label('Clinic')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->clinic_id ?? 'no_clinic')
                    ->getTitleFromRecordUsing(fn ($record) => $record->clinic?->name ?? 'Unassigned'),
                Group::make('user_id')
                    ->label('Therapist')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->user_id ?? 'no_therapist')
                    ->getTitleFromRecordUsing(fn ($record) => $record->therapist?->first_name ?? 'Unassigned'),
                Group::make('day_of_week')->label('Day Of Week'),
                Group::make('created_at')->date()
            ]);
            // $table->groups($groups);
            $table->defaultGroup('user_id');
        }
        else if (check_role(['clinic_manager', 'clinic_head'])) {
            $table->groups([
                Group::make('user_id')
                    ->label('Therapist')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->user_id ?? 'no_therapist')
                    ->getTitleFromRecordUsing(fn ($record) => $record->therapist?->first_name ?? 'Unassigned'),
                Group::make('day_of_week')->label('Day Of Week'),
                Group::make('created_at')->date()
            ]);
            // $table->groups($groups);
            $table->defaultGroup('user_id');
        }

        return $table;
    }


    // public static function configure(Table $table): Table
    // {
    //     return $table
    //         ->deferLoading()
    //         // ->recordUrl(null)
    //         ->modifyQueryUsing(function (Builder $query) {
    //             $query->getQuery()->orders = null;
    //             $query->select([
    //                 DB::raw('MIN(id) as id'),
    //                     'clinic_id',
    //                     'user_id',
    //                 DB::raw('MIN(id) as sort_id'),
    //                 DB::raw("
    //                     JSON_ARRAYAGG(
    //                         JSON_OBJECT(
    //                             'day', day_of_week,
    //                             'start', start_time,
    //                             'end', end_time,
    //                             'active', is_active
    //                         )
    //                     ) as weekly_schedule
    //                 "),
    //             ])
    //             ->where('is_active', true)
    //             ->groupBy('clinic_id', 'user_id');
    //         })
    //         ->defaultSort('sort_id', 'asc')
    //         ->columns([
    //             TextColumn::make('clinic.name')
    //                 ->label('Clinic')
    //                 ->badge()
    //                 ->visible(fn () => auth()->user()->hasRole('super_admin'))
    //                 ->icon('heroicon-o-building-office')
    //                 ->color('gray')
    //                 ->placeholder('Unassigned')
    //                 ->searchable()
    //                 ->action(
    //                     ViewAction::make('view_clinic')
    //                         ->record(fn ($record) => $record->clinic)
    //                         ->infolist(
    //                             fn (Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
    //                         )
    //                         ->modal()
    //                         ->modalHeading(fn ($record) => $record->clinic?->name ?? 'No Clinic Assigned')
    //                         ->visible(fn ($record) => $record->clinic !== null)
    //                 )
    //                 ->toggleable(),
    //             TextColumn::make('therapist.name')->label('Therapist')->badge()->searchable(['first_name', 'last_name']),

    //             static::dayColumn('Mon', 1),
    //             static::dayColumn('Tue', 2),
    //             static::dayColumn('Wed', 3),
    //             static::dayColumn('Thu', 4),
    //             static::dayColumn('Fri', 5),
    //             static::dayColumn('Sat', 6),
    //             static::dayColumn('Sun', 7),

    //             TextColumn::make('updated_at')->since(),

    //         ])
    //         ->filters([
    //             // 3) Other Filters: Improved layout with 2-column grid, dependencies, and role-based visibility
    //             Filter::make('advanced')
    //                 ->label('Advanced Filters')
    //                 ->form([
    //                     Section::make('Clinic & Therapist Filters')
    //                         ->icon('heroicon-o-building-office-2')
    //                         ->description('Filter by clinic and therapist users.')
    //                         ->schema([
    //                             Grid::make(1) // 2-column grid for better space utilization
    //                                 ->schema([
    //                                     // Clinic
    //                                     Select::make('clinic_id')
    //                                         ->label('Clinic')
    //                                         ->relationship('clinic', 'name')
    //                                         ->searchable()
    //                                         ->preload()
    //                                         ->placeholder('Select clinic')
    //                                         ->native(false)
    //                                         ->live() // Triggers reactive updates on dependents
    //                                         ->visible(fn () => auth()->user()->hasRole('super_admin')),

    //                                     // Client
    //                                     Select::make('user_id')
    //                                         ->label('Therapist')
    //                                         ->options(function (callable $get) {
    //                                             $clinicId = $get('clinic_id');
    //                                             if (!$clinicId)
    //                                                 $clinicId = auth()->user()->clinic_id;

    //                                             return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
    //                                         })
    //                                         ->reactive()
    //                                         ->searchable()
    //                                         ->placeholder('Select Therapist'),

    //                                     Select::make('day_of_week')
    //                                         ->label('Day')
    //                                         ->options(UserWeeklySchedule::dayOptions())
    //                                         ->required(),
    //                                 ]),
    //                         ])
    //                         ->columns(1)
    //                         ->collapsible(),
    //                 ])
    //                 ->query(function (Builder $query, array $data): Builder {
    //                     return $query
    //                         ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
    //                         ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
    //                         ->when($data['day_of_week'] ?? null, fn ($q, $val) => $q->where('day_of_week', $val));
    //                 })
    //                 ->indicateUsing(function (array $data): array {
    //                     $indicators = [];

    //                     if ($data['clinic_id'] ?? null) {
    //                         $clinic = Clinic::find($data['clinic_id']);
    //                         if ($clinic) {
    //                             $indicators[] = Indicator::make('Clinic: ' . $clinic->name)->removeField('clinic_id');
    //                         }
    //                     }

    //                     if ($data['user_id'] ?? null) {
    //                         $user = User::find($data['user_id']);
    //                         if ($user) {
    //                             $indicators[] = Indicator::make('Therapist: ' . $user->name)->removeField('user_id');
    //                         }
    //                     }

    //                     if ($data['day_of_week'] ?? null) {
    //                         $day = UserWeeklySchedule::dayOptions()[$data['day_of_week']];
    //                         $indicators[] = Indicator::make('day_of_week: ' . $day)->removeField('day_of_week');
    //                     }

    //                     return $indicators;
    //                 }),
    //         ],layout: FiltersLayout::Modal)
    //         // ->filtersFormColumns(2) // Reduced to 2 for better readability in modal; adjust as needed
    //         // ->filtersFormWidth('md:max-w-4xl')
    //         ->filtersTriggerAction(
    //             fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
    //         )
    //         ->recordActions([
    //             EditAction::make(),
    //             DeleteAction::make(),
    //         ])
    //         // ->groups([
    //         //     Group::make('clinic_id')
    //         //         ->label('Clinic')
    //         //         ->collapsible()
    //         //         ->getKeyFromRecordUsing(fn ($record) => $record->clinic_id ?? 'no_clinic')
    //         //         ->getTitleFromRecordUsing(fn ($record) => $record->clinic?->name ?? 'Unassigned'),
    //         //     Group::make('user_id')
    //         //         ->label('Therapist')
    //         //         ->collapsible()
    //         //         ->getKeyFromRecordUsing(fn ($record) => $record->user_id ?? 'no_therapist')
    //         //         ->getTitleFromRecordUsing(fn ($record) => $record->therapist?->first_name ?? 'Unassigned'),
    //         //     Group::make('day_of_week')->label('Day Of Week'),
    //         //     Group::make('created_at')->date(),
    //         // ])
    //         // ->defaultGroup('user_id')
    //         ->toolbarActions([
    //             BulkActionGroup::make([
    //                 DeleteBulkAction::make(),
    //             ]),
    //         ])
    //         ->emptyStateDescription('Once you create your first record, it will appear here.');
    // }

    // protected static function dayColumn(string $label, int $day)
    // {
    //     return TextColumn::make($label)
    //         ->label($label)
    //         ->html()
    //         // ->badge()
    //         ->state(function ($record) use ($day) {
    //             $shifts = $record->shiftsForDay($day);

    //             if (empty($shifts)) {
    //                 return '<span class="text-gray-400"> — </span>';
    //             }

    //             return collect($shifts)
    //                 ->map(fn ($t) => "<div class='text-sm'>{$t}</div>")
    //                 ->implode('');
    //         });
    // }
}
