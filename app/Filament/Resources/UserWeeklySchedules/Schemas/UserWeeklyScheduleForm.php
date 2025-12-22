<?php

namespace App\Filament\Resources\UserWeeklySchedules\Schemas;

use Filament\Schemas\Schema;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Hidden;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Placeholder;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Closure;

use App\Models\UserWeeklySchedule;
use App\Models\User;

class UserWeeklyScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Weekly Schedule')
                    ->schema([
                        Grid::make(2)
                            // ->visible(fn () => check_role('super_admin'))
                            ->schema([
                                check_role('super_admin')
                                    ? Select::make('clinic_id')
                                        ->label('Clinic')
                                        ->relationship('clinic', 'name')
                                        // ->searchable()
                                        // ->preload()
                                        ->native(false)
                                        ->reactive()
                                        ->required()
                                        ->placeholder('Select Clinic')
                                        ->live()
                                    : Hidden::make('clinic_id')->default(auth()->user()->clinic_id),

                                check_role('therapist')
                                    ? Hidden::make('user_id')->default(auth()->id())
                                    : Select::make('user_id')
                                        ->label('Therapist')
                                        ->options(function (callable $get) {
                                            $clinicId = $get('clinic_id');
                                            if (!$clinicId)
                                                $clinicId = auth()->user()->clinic_id;

                                            return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                        })
                                        // ->searchable()
                                        // ->preload()
                                        ->native(true)
                                        ->reactive()
                                        ->required()
                                        ->placeholder('Select Therapist')
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('appointment_datetime', null))
                                        ->live(),
                            ]),

                        Section::make('Weekly Days')->schema(self::weeklyDaySchema()),
                    ])
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
        ])->map(function ($label, $day) {

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
                    TextEntry::make("day_label_$day")
                        ->label(false)
                        ->default($label),

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
                // ->orderColumn('sort')
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

            // return Repeater::make("days.$day.shifts")
            //     ->hiddenLabel()
            //     ->defaultItems(1)
            //     ->schema([
            //             TimePicker::make('start_time')
            //                 ->label('Start Time')
            //                 ->required()
            //                 // ->minutesStep(15)
            //                 ->seconds(false),

            //             TimePicker::make('end_time')
            //                 ->label('End Time')
            //                 ->required()
            //                 // ->minutesStep(15)
            //                 ->seconds(false),
            //     ])
            //     ->columns(2)
            //     ->itemLabel($label);

            // return Section::make($label)
            //     ->schema([
            //         Toggle::make("days.$day.is_active")
            //             ->label('Working day')
            //             ->default(true)
            //             ->live(),

            //         Repeater::make("days.$day.shifts")
            //             ->label('Shifts')
            //             ->visible(fn ($get) => $get("days.$day.is_active"))
            //             ->minItems(1)
            //             ->defaultItems(1)
            //             ->addActionLabel('Add shift')
            //             ->hiddenLabel()
            //             ->reorderable(false)
            //             ->grid(2)
            //             ->table([
            //                 TableColumn::make('Start Time')->width(100),
            //                 TableColumn::make('End Time')->width(110),
            //             ])
            //             ->schema([
            //                 // Grid::make(3)
            //                 //     ->schema([
            //                         Select::make('start_time')
            //                             ->label('Start Time')
            //                             ->options(time_options())
            //                             ->dehydrateStateUsing(fn ($state) => $state ? $state . ':00' : null)
            //                             ->required(),

            //                         Select::make('end_time')
            //                             ->label('End Time')
            //                             ->options(time_options())
            //                             ->dehydrateStateUsing(fn ($state) => $state ? $state . ':00' : null)
            //                            ->required(),
            //                             // ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
            //                             //     $livewire->validateOnly('end_time');
            //                             // })

            //                         // Toggle::make('allows_overlap')
            //                         //     ->label('Allow overlap')
            //                         //     ->default(false),
            //                     // ]),
            //             ]),
            //     ])
            //     ->collapsible();
        })->values()->toArray();
    }
}
