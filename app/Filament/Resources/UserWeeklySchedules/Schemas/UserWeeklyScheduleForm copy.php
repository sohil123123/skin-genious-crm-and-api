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
            ->components([
                Group::make()
                    ->schema([
                        Section::make('User Schedule')
                            ->schema([
                                auth()->user()->hasRole('super_admin')
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

                                auth()->user()->hasRole('therapist')
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
                                        ->required()
                                        ->placeholder('Select Therapist')
                                        ->afterStateUpdated(fn ($state, callable $set) => $set('appointment_datetime', null))
                                        ->live(),

                                Select::make('day_of_week')
                                    ->label('Day')
                                    ->options(UserWeeklySchedule::dayOptions())
                                    ->required()
                                    ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                                        $livewire->validateOnly('end_time');
                                    })
                                    ->live(),

                                // TimePicker::make('start_time')
                                //     ->label('Start Time')
                                //     ->seconds(false)
                                //     ->required(),
                                Select::make('start_time')
                                    ->label('Start Time')
                                    ->options(time_options())
                                    ->dehydrateStateUsing(fn ($state) => $state ? $state . ':00' : null)
                                    ->required(),

                                // TimePicker::make('end_time')
                                //     ->label('End Time')
                                //     ->seconds(false)
                                //     ->required()
                                //     ->rule(function ($get) {
                                //         return function (string $attribute, $value, Closure $fail) use ($get) {
                                //             $start = $get('start_time');
                                //             if ($start && $value && $value <= $start) {
                                //                 $fail('End Time must be after Start Time.');
                                //             }
                                //         };
                                //     }),

                                Select::make('end_time')
                                    ->label('End Time')
                                    ->options(time_options())
                                    ->required()
                                    ->dehydrateStateUsing(fn ($state) => $state ? $state . ':00' : null)
                                    ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                                        $livewire->validateOnly('end_time');
                                    })
                                    ->rules([
                                        // ✅ End time must be after start time
                                        fn ($get) => function (string $attribute, $value, $fail) use ($get) {
                                            $start = $get('start_time');

                                            if ($start && Carbon::createFromTimeString($value)
                                                ->lessThanOrEqualTo(Carbon::createFromTimeString($start . ':00'))
                                            ) {
                                                // $fail('Therapist, Day Of Week, Start Time and End Time must be unique.');
                                                $fail('End Time must be after Start Time.');
                                            }
                                        },

                                        // ✅ Composite uniqueness (IMPORTANT FIX)
                                        fn ($get, ?UserWeeklySchedule $record) =>
                                            Rule::unique('user_weekly_schedules', 'end_time')
                                                ->where(fn ($query) => $query
                                                    ->where('clinic_id', $get('clinic_id'))
                                                    ->where('user_id', $get('user_id'))
                                                    ->where('day_of_week', $get('day_of_week'))
                                                    ->where('start_time', $get('start_time') . ':00')
                                                )
                                                ->ignore($record?->id),
                                    ])
                                    ->validationMessages([
                                        'unique' => 'This therapist already has a shift for the selected day and time.',
                                    ]),

                                // Toggle::make('allows_overlap')
                                //     ->label('Allow Overlapping Shift')
                                //     ->default(true)
                                //     ->helperText('Overlapping shifts are allowed but will be highlighted'),

                                ToggleButtons::make('is_active')
                                    ->inline()
                                    ->boolean()
                                    ->default(fn ($record) => $record?->is_active ?? true)
                                    ->required(),

                                Textarea::make('notes')
                                    ->columnSpanFull()
                                    ->placeholder('Optional internal notes'),
                            ])
                            ->columns(3)
                ])
                ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),
            ]);
    }
}
