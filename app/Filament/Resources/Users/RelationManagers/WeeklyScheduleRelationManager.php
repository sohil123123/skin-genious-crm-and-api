<?php

namespace App\Filament\Resources\Users\RelationManagers;


use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;

use Filament\Notifications\Notification;

use Filament\Support\Icons\Heroicon;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

use App\Services\Scheduling\UserWeeklyScheduleService;

use Illuminate\Validation\Rule;
use Carbon\Carbon;

use App\Models\UserWeeklySchedule;

class WeeklyScheduleRelationManager extends RelationManager
{
    protected static string $relationship = 'weeklySchedules';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Weekly Schedule')
                    ->schema(self::weeklyDaySchema())
                    ->columnSpan(['lg' => 3]),
            ]);
    }

    protected static function weeklyDaySchema(): array
    {
        return collect([
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ])
        ->map(function ($label, $day) {
            return Repeater::make("days.$day.shifts")
                ->extraAttributes([
                    'class' => 'shifts-repeater',
                ])
                ->table([
                    TableColumn::make('-')->width(100),
                    TableColumn::make('Start Time')->width(100),
                    TableColumn::make('End Time')->width(100),
                    TableColumn::make('Is Working day?')->width(100),
                ])
                ->schema([
                    Hidden::make('id'),
                    TextEntry::make("day_label_$day")->label(false)->default($label),

                    Select::make('start_time')
                        ->label('Start Time')
                        ->options(time_options())
                        ->dehydrateStateUsing(fn ($state) => $state ? $state . ':00' : null)
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            if (!$state) return;

                            // Get all days
                            $days = $get('../../../../days') ?? [];

                            foreach ($days as $dayKey => $dayData) {
                                foreach ($dayData['shifts'] ?? [] as $shiftIndex => $shift) {

                                    // Only auto-fill EMPTY start_time
                                    if (empty($shift['start_time'])) {
                                        $set("../../../../days.$dayKey.shifts.$shiftIndex.start_time", $state);
                                    }
                                }
                            }
                        })
                        ->reactive()
                        ->required(),

                    Select::make('end_time')
                        ->label('End Time')
                        ->options(time_options())
                        ->dehydrateStateUsing(fn ($state) => $state ? $state . ':00' : null)
                        ->afterStateUpdated(function ($state, callable $get, callable $set, $livewire) {
                            $livewire->validateOnly('end_time');

                            if (!$state) return;

                            // Get all days
                            $days = $get('../../../../days') ?? [];

                            foreach ($days as $dayKey => $dayData) {
                                foreach ($dayData['shifts'] ?? [] as $shiftIndex => $shift) {

                                    // Only auto-fill EMPTY end_time
                                    if (empty($shift['end_time'])) {
                                        $set("../../../../days.$dayKey.shifts.$shiftIndex.end_time", $state);
                                    }
                                }
                            }
                        })
                        ->rules([
                            // ✅ End time must be after start time
                            function (callable $get) {
                                return function (string $attribute, $value, $fail) use ($get) {
                                    $start = $get('start_time');

                                    if ($start && Carbon::createFromTimeString($value)
                                        ->lessThanOrEqualTo(Carbon::createFromTimeString($start . ':00'))
                                    ) {
                                        $fail('End Time must be after Start Time.');
                                    }
                                };
                            },

                            // ✅ Composite uniqueness (FIXED & WORKING)
                            function (callable $get) use ($day) {
                                return Rule::unique('user_weekly_schedules', 'end_time')
                                    ->where(function ($query) use ($get, $day) {
                                        $query
                                            ->where('clinic_id', $get('../../../../clinic_id'))
                                            ->where('user_id', $get('../../../../user_id'))
                                            ->where('day_of_week', $day)
                                            ->where('start_time', $get('start_time') . ':00');
                                    })
                                    ->ignore($get('id'));
                            },
                        ])
                        ->validationMessages([
                            'unique' => 'This therapist already has a shift for the selected day and time.',
                        ])
                        ->reactive()
                        ->required(),

                    Toggle::make("is_active")
                        ->label('Working day')
                        ->default(true)
                        ->live(),
                ])
                ->hiddenLabel()
                ->reorderable(false)
                ->defaultItems(1)
                ->minItems(1)
                ->hiddenLabel()
                ->addActionLabel('Add another '. $label .' shift')
                ->deleteAction(
                    fn ($action) => $action
                        ->disabled(fn (callable $get) => count($get('days')[$day]['shifts'] ?? []) === 1)
                        ->tooltip('At least one shift is required')
                )
                ->required();
        })
        ->values()
        ->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort('day_of_week', 'asc')
            ->paginated(false)
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
            ->filters([
                SelectFilter::make('day_of_week')
                    ->options(day_options())
            ], layout: FiltersLayout::Modal)
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filter'),
            )
            ->recordActions([
                EditAction::make()
                    ->action(function (array $data, $record) {
                        $data['clinic_id'] = $this->ownerRecord->clinic_id;
                        $data['user_id'] = $this->ownerRecord->id;
                        app(UserWeeklyScheduleService::class)->saveOrUpdate($data);
                    })
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $clinicId = $record->clinic_id;
                        $userId   = $record->user_id;

                        $schedules = UserWeeklySchedule::where([
                            'clinic_id' => $clinicId,
                            'user_id' => $userId,
                        ])
                        ->orderBy('day_of_week')
                        ->orderBy('start_time')
                        ->get();

                        $days = [];
                        foreach ($schedules as $schedule) {
                            $day = $schedule->day_of_week;

                            $days[$day]['shifts'][] = [
                                'id' => $schedule->id,
                                'start_time' => $schedule->start_time,
                                'end_time' => $schedule->end_time,
                                'is_active' => $schedule->is_active,
                            ];
                        }
                        return [
                            'clinic_id' => $clinicId,
                            'user_id' => $userId,
                            'days' => $days,
                        ];
                    })
                    ->successNotification(
                        Notification::make()
                            ->title('Weekly schedule updated')
                            ->body('Weekly schedule updated successfully.')
                            ->success()
                    ),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create Schedule')
                    ->icon('heroicon-o-plus')
                    ->createAnother(false)
                    ->visible(fn () =>! $this->getOwnerRecord()->weeklySchedules()->exists())
                    ->action(function (array $data) {
                        $data['clinic_id'] = $this->ownerRecord->clinic_id;
                        $data['user_id'] = $this->ownerRecord->id;
                        app(UserWeeklyScheduleService::class)->saveOrUpdate($data);
                    })
                    ->successNotification(
                        Notification::make()
                            ->title('Weekly schedule created')
                            ->body('Weekly schedule created successfully.')
                            ->success()
                    ),
            ])
            ->emptyStateDescription('Once you create your first record, it will appear here.');
    }
}
