<?php

namespace App\Filament\Resources\Appointments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Hidden;
use Filament\Actions\Action;
use Illuminate\Validation\Rule;

use Illuminate\Support\Carbon;
use Closure;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Rules\AppointmentAvailability;

use App\Models\User;
use App\Models\Clinic;
use App\Models\Assessment;
use App\Models\TreatmentSession;
use App\Models\Holiday;

class AppointmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Appointment Type')
                            ->schema(static::getAppointmentTypeComponents())
                            ->columns(2),

                        Section::make('Choose Client, Therapist and Date')
                            ->schema(static::getClientAndTherapistComponents())
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Appointment created')
                            ->state(fn ($record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last modified')
                            ->state(fn ($record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn ($record) => $record === null),
            ])
            ->columns(3);
    }

    public static function getAppointmentTypeComponents(): array
    {
        return [
            ToggleButtons::make('type')
                ->inline()
                ->options(AppointmentType::class)
                ->default('treatment')
                ->live()
                ->required(),
                // ->columnSpan(['lg' => 3]),
                // ->columnSpanFull(),
        ];
    }

    public static function getClientAndTherapistComponents()
    {
        return [
            auth()->user()->hasRole('super_admin')
                ? Select::make('clinic_id')
                    ->label('Clinic')
                    ->relationship('clinic', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Select Clinic')
                    ->live()
                : Hidden::make('clinic_id')->default(auth()->user()->clinic_id),

            Select::make('user_id')
                ->label('Client')
                ->options(function (callable $get) {
                    $clinicId = $get('clinic_id');
                    if (!$clinicId)
                        $clinicId = auth()->user()->clinic_id;
                    
                    return User::active()->role('client')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                })
                ->searchable()
                ->required()
                ->placeholder('Select Client'),

            auth()->user()->hasRole('therapist')
                ? Hidden::make('therapist_id')->default(auth()->id())
                : Select::make('therapist_id')
                    ->label('Therapist')
                    ->options(function (callable $get) {
                        $clinicId = $get('clinic_id');
                        if (!$clinicId)
                            $clinicId = auth()->user()->clinic_id;
                        
                        return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                    })
                    ->searchable()
                    ->required()
                    ->placeholder('Select Therapist')
                    ->afterStateUpdated(fn ($state, callable $set) => $set('appointment_datetime', null))
                    ->live(),

            Select::make('assessment_id')
                ->label('Assessment')
                ->options(fn (callable $get) =>
                    $get('client_id')
                        ? Assessment::where('user_id', $get('client_id'))->pluck('id', 'id')
                        : []
                )
                ->visible(fn (callable $get) => $get('type')->value === 'treatment')
                ->required(fn (callable $get) => $get('type')->value === 'treatment')
                ->placeholder('Select Assessment'),

            Select::make('treatment_session_id')
                ->label('Treatment Session')
                ->options(fn (callable $get) =>
                    $get('assessment_id')
                        ? TreatmentSession::where('assessment_id', $get('assessment_id'))
                            ->pluck('name', 'id')
                        : []
                )
                ->visible(fn (callable $get) => $get('type')->value === 'treatment')
                ->required(fn (callable $get) => $get('type')->value === 'treatment')
                ->placeholder('Select Treatment Session'),

            DateTimePicker::make('appointment_datetime')
                ->label('Appointment Date & Time')
                ->native(false)
                ->placeholder(now()->startOfMonth()->format('M d, Y h:i A'))
                // ->placeholder('Select Appointment Date & Time')
                ->minDate(Carbon::today())
                ->displayFormat('M d, Y h:i A')  // 12-hour AM/PM
                ->format('Y-m-d h:i A')
                ->minutesStep(5)
                ->closeOnDateSelection(false)
                ->reactive()
                ->afterStateHydrated(function (callable $set, $record) {
                    if ($record && $record->therapist_id) {
                        $set('therapist_id', $record->therapist_id);
                    }
                })
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $livewire->validateOnly('appointment_datetime'); // ✅ triggers instant revalidation
                })
                ->disabledDates(function (callable $get) {
                    $therapistId = $get('therapist_id');
                    $type = $get('type')->value;
                    if (!$therapistId || $type == 'consult') {
                        return [];
                    }

                    $holidays = Holiday::where('user_id', $therapistId)
                        // ->where('status', 'approved')
                        ->get(['start_date', 'end_date']);

                    return $holidays->flatMap(function ($holiday) {
                        $dates = [];
                        $start = Carbon::parse($holiday->start_date);
                        $end = Carbon::parse($holiday->end_date);

                        while ($start->lte($end)) {
                            $dates[] = $start->toDateString();
                            $start->addDay();
                        }

                        return $dates;
                    })->toArray();
                })
                ->required()
                // ->rules([
                //     fn (callable $get, $record) => new AppointmentAvailability(
                //         therapistId: $get('therapist_id'),
                //         clinicId: $get('clinic_id'),
                //         start: Carbon::parse($get('appointment_datetime')),
                //         end: Carbon::parse($get('appointment_datetime')),
                //         excludeId: $record?->id,
                //     ),
                // ])
                ->rules([
                    fn (callable $get, $record) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                        // 1️⃣ Block selecting past date/time
                        if (!$value) return;

                        $selectedDateTime = Carbon::parse($value);
                        $now = Carbon::now()->addMinutes(30);

                        // -----------------------------------------------------
                        // 1️⃣ Block selecting past date/time
                        // -----------------------------------------------------
                        if ($selectedDateTime->isToday() && $selectedDateTime->lessThan($now)) {
                            $fail("You cannot select a past time ({$now->format('h:i A')}) for today's date.");
                            return;
                        }

                        if ($selectedDateTime->isPast()) {
                            $fail("You cannot select a past date or time ({$now->format('M d, Y h:i A')}).");
                            return;
                        }
                            
                        // 2️⃣ Clinic timing logic
                        $clinicId = $get('clinic_id');
                        if (!$clinicId) return;

                        $clinic = Clinic::find($clinicId);
                        if (!$clinic) return;

                        $clinicStart = Carbon::parse($clinic->start_time)->addMinutes(30)->format('H:i:s');
                        $clinicEnd   = Carbon::parse($clinic->end_time)->subMinutes(30)->format('H:i:s');

                        if (!$value) return;

                        $time = $selectedDateTime->format('H:i:s');
                        
                        if ($time < $clinicStart || $time > $clinicEnd) {
                            $clinicStart = Carbon::parse($clinicStart)->format('h:i A');
                            $clinicEnd = Carbon::parse($clinicEnd)->format('h:i A');
                            $fail("Allowed time for this clinic is between {$clinicStart} and {$clinicEnd}.");
                            return; // Stop next rule
                        }

                        // 3️⃣ Availability rule
                        $rule = new AppointmentAvailability(
                            therapistId: $get('therapist_id'),
                            clinicId: $clinicId,
                            start: Carbon::parse($get('appointment_datetime')),
                            end: Carbon::parse($get('appointment_datetime')),
                            excludeId: $record?->id,
                        );

                        $rule->validate($attribute, $value, $fail);
                    }
                ])
                ->hint(function ($get) {
                    $clinic = Clinic::find($get('clinic_id'));

                    if (!$clinic) return 'Select clinic to see available timing.';

                    $start = Carbon::parse($clinic->start_time)->addMinutes(30)->format('h:i A');
                    $end   = Carbon::parse($clinic->end_time)->subMinutes(30)->format('h:i A');

                    return "Available time: {$start} – {$end}";
                })
                ->allowHtmlValidationMessages(),
                // ->helperText('Ensure the therapist does not already have an appointment at this time.')
                // ->columnSpanFull(),
                // ->columnSpan(['lg' => fn ($record) => $record === null ? 1 : 1]),

            // STEP 4: STATUS
            ToggleButtons::make('status')
                ->inline()
                ->options(AppointmentStatus::class)
                ->default('confirmed')
                ->required()
                ->columnSpanFull(),
                // ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

            // STEP 5: NOTES
            Textarea::make('notes')
                ->placeholder('Enter notes here...')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    // public static function configure(Schema $schema): Schema
    // {
    //     return $schema->components([
    //         Group::make()
    //             ->schema([
    //                 Section::make('Appointment Details')
    //                     ->icon('heroicon-o-clipboard-document-check')
    //                     ->schema([
    //                         Grid::make(2)->schema([
    //                             // STEP 1: TYPE
    //                             ToggleButtons::make('type')
    //                                 ->inline()
    //                                 ->options(AppointmentType::class)
    //                                 ->default('treatment')
    //                                 ->live()
    //                                 ->required()
    //                                 ->columnSpanFull(),

    //                             // STEP 2: BASIC DETAILS
    //                             auth()->user()->hasRole('super_admin')
    //                                 ? Select::make('clinic_id')
    //                                     ->label('Clinic')
    //                                     ->relationship('clinic', 'name')
    //                                     ->searchable()
    //                                     ->preload()
    //                                     ->required()
    //                                     ->placeholder('Select Clinic')
    //                                 : Hidden::make('clinic_id')->default(auth()->user()->clinic_id),

    //                             Select::make('client_id')
    //                                 ->label('Client')
    //                                 ->options(function (callable $get) {
    //                                     $clinicId = $get('clinic_id');
    //                                     if (!$clinicId)
    //                                         $clinicId = auth()->user()->clinic_id;
                                        
    //                                     return User::active()->role('client')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
    //                                 })
    //                                 ->searchable()
    //                                 ->required()
    //                                 ->placeholder('Select Client'),

    //                             auth()->user()->hasRole('therapist')
    //                                 ? Hidden::make('therapist_id')->default(auth()->id())
    //                                 : Select::make('therapist_id')
    //                                     ->label('Therapist')
    //                                     ->options(function (callable $get) {
    //                                         $clinicId = $get('clinic_id');
    //                                         if (!$clinicId)
    //                                             $clinicId = auth()->user()->clinic_id;
                                            
    //                                         return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
    //                                     })
    //                                     ->searchable()
    //                                     ->required()
    //                                     ->placeholder('Select Therapist')
    //                                     ->afterStateUpdated(fn ($state, callable $set) => $set('appointment_datetime', null))
    //                                     ->live(),

    //                             Select::make('assessment_id')
    //                                 ->label('Assessment')
    //                                 ->options(fn (callable $get) =>
    //                                     $get('client_id')
    //                                         ? Assessment::where('user_id', $get('client_id'))->pluck('id', 'id')
    //                                         : []
    //                                 )
    //                                 ->visible(fn (callable $get) => $get('type')->value === 'treatment')
    //                                 ->required(fn (callable $get) => $get('type')->value === 'treatment')
    //                                 ->placeholder('Select Assessment'),

    //                             Select::make('treatment_session_id')
    //                                 ->label('Treatment Session')
    //                                 ->options(fn (callable $get) =>
    //                                     $get('assessment_id')
    //                                         ? TreatmentSession::where('assessment_id', $get('assessment_id'))
    //                                             ->pluck('name', 'id')
    //                                         : []
    //                                 )
    //                                 ->visible(fn (callable $get) => $get('type')->value === 'treatment')
    //                                 ->required(fn (callable $get) => $get('type')->value === 'treatment')
    //                                 ->placeholder('Select Treatment Session'),

    //                         ]),
    //                         Grid::make(2)->schema([
    //                             // STEP 3: DATE & TIME
    //                             DateTimePicker::make('appointment_datetime')
    //                                 ->label('Appointment Date & Time')
    //                                 ->native(false)
    //                                 ->placeholder('Select Appointment Date & Time')
    //                                 ->minDate(Carbon::today())
    //                                 ->displayFormat('M d, Y h:i A')  // 12-hour AM/PM
    //                                 ->format('Y-m-d h:i A')
    //                                 ->minutesStep(5)
    //                                 ->closeOnDateSelection(false)
    //                                 ->reactive()
    //                                 ->afterStateHydrated(function (callable $set, $record) {
    //                                     if ($record && $record->therapist_id) {
    //                                         $set('therapist_id', $record->therapist_id);
    //                                     }
    //                                 })
    //                                 ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
    //                                     $livewire->validateOnly('appointment_datetime'); // ✅ triggers instant revalidation
    //                                 })
    //                                 ->disabledDates(function (callable $get) {
    //                                     $therapistId = $get('therapist_id');
    //                                     $type = $get('type')->value;
    //                                     if (!$therapistId || $type == 'consult') {
    //                                         return [];
    //                                     }

    //                                     $holidays = Holiday::where('user_id', $therapistId)
    //                                         // ->where('status', 'approved')
    //                                         ->get(['start_date', 'end_date']);

    //                                     return $holidays->flatMap(function ($holiday) {
    //                                         $dates = [];
    //                                         $start = Carbon::parse($holiday->start_date);
    //                                         $end = Carbon::parse($holiday->end_date);

    //                                         while ($start->lte($end)) {
    //                                             $dates[] = $start->toDateString();
    //                                             $start->addDay();
    //                                         }

    //                                         return $dates;
    //                                     })->toArray();
    //                                 })
    //                                 ->required()
    //                                 ->rules([
    //                                     fn (callable $get, $record) => new AppointmentAvailability(
    //                                         therapistId: $get('therapist_id'),
    //                                         clinicId: $get('clinic_id'),
    //                                         start: Carbon::parse($get('appointment_datetime')),
    //                                         end: Carbon::parse($get('appointment_datetime')),
    //                                         excludeId: $record?->id,
    //                                     ),
    //                                 ])
    //                                 ->allowHtmlValidationMessages()
    //                                 ->helperText('Ensure the therapist does not already have an appointment at this time.')
    //                                 // ->columnSpanFull(),
    //                                 ->columnSpan(['lg' => fn ($record) => $record === null ? 1 : 1]),

    //                             // STEP 4: STATUS
    //                             ToggleButtons::make('status')
    //                                 ->inline()
    //                                 ->options(AppointmentStatus::class)
    //                                 ->default('confirmed')
    //                                 ->required()
    //                                 ->columnSpanFull(),
    //                                 // ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

    //                             // STEP 5: NOTES
    //                             Textarea::make('notes')
    //                                 ->placeholder('Enter notes here...')
    //                                 ->rows(3)
    //                                 ->columnSpanFull(),
    //                         ]),
    //                     ]),
    //             ])
    //             ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

    //         Section::make()
    //             ->schema([
    //                 TextEntry::make('created_at')
    //                     ->label('Appointment created')
    //                     ->state(fn ($record): ?string => $record->created_at?->diffForHumans()),

    //                 TextEntry::make('updated_at')
    //                     ->label('Last modified')
    //                     ->state(fn ($record): ?string => $record->updated_at?->diffForHumans()),
    //             ])
    //             ->columnSpan(['lg' => 1])
    //             ->hidden(fn ($record) => $record === null),
    //     ])
    //     ->columns(3);
    // }

    // public static function configure(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             Group::make()
    //                 ->schema([
    //                     Section::make('Basic Information')
    //                         ->icon('heroicon-o-information-circle')
    //                         ->schema([
    //                             Grid::make(3)->schema([
    //                                 Select::make('clinic_id')
    //                                     ->label('Clinic')
    //                                     ->relationship('clinic', 'name')
    //                                     ->searchable()
    //                                     ->preload()
    //                                     ->placeholder('Select clinic')
    //                                     ->native(true)
    //                                     ->required(),
                                    
    //                                 Select::make('client_id')
    //                                     ->label('Client')
    //                                     ->options(function (callable $get) {
    //                                         $clinicId = $get('clinic_id');
    //                                         if (!$clinicId) {
    //                                             return [];
    //                                         }
    //                                         return User::whereHas('roles', fn ($q) => $q->where('name', 'client'))
    //                                                 ->where('clinic_id', $clinicId)
    //                                                 ->get()
    //                                                 ->mapWithKeys(fn ($u) => [$u->id => $u->name]);

    //                                     })
    //                                     ->searchable()
    //                                     ->placeholder('Select Client')
    //                                     ->required(),

    //                                 Select::make('therapist_id')
    //                                     ->label('Therapist')
    //                                     ->options(function (callable $get) {
    //                                         $clinicId = $get('clinic_id');
    //                                         if (!$clinicId) {
    //                                             return [];
    //                                         }
    //                                         return User::whereHas('roles', fn ($q) => $q->where('name', 'therapist'))
    //                                                 ->where('clinic_id', $clinicId)
    //                                                 ->get()
    //                                                 ->mapWithKeys(fn ($u) => [$u->id => $u->name]);

    //                                     })
    //                                     ->searchable()
    //                                     ->placeholder('Select Therapist')
    //                                     ->required()
    //                                     ->afterStateUpdated(function ($state, callable $set) {
    //                                         // 👇 Reset appointment date when therapist changes
    //                                         $set('appointment_datetime', null);
    //                                     })
    //                                     ->live(),

    //                                 Select::make('assessment_id')
    //                                     ->label('Assessment')
    //                                     ->options(function (callable $get) {
    //                                         $clientId = $get('client_id');
    //                                         if (!$clientId) {
    //                                             return [];
    //                                         }
    //                                         return Assessment::where('user_id', $clientId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->id]);

    //                                     })
    //                                     ->searchable()
    //                                     ->visible(fn (callable $get) => $get('type')->value === 'treatment')
    //                                     ->placeholder('Select Assessment'),

    //                                 Select::make('treatment_session_id')
    //                                     ->label('Treatment Session')
    //                                     ->options(function (callable $get) {
    //                                         $assessmentId = $get('assessment_id');
    //                                         if (!$assessmentId) {
    //                                             return [];
    //                                         }
    //                                         return TreatmentSession::where('assessment_id', $assessmentId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);

    //                                     })
    //                                     ->searchable()
    //                                     ->visible(fn (callable $get) => $get('type')->value === 'treatment')
    //                                     ->placeholder('Select Treatment Session'),

                                    
    //                             ]),
    //                             Grid::make(4)->schema([
    //                                 ToggleButtons::make('type')
    //                                     ->inline()
    //                                     ->options(AppointmentType::class)
    //                                     ->default('treatment')
    //                                     ->live()
    //                                     ->required(),

    //                                 DateTimePicker::make('appointment_datetime')
    //                                     ->required()
    //                                     ->native(false)
    //                                     ->placeholder('Select Appointment Date & Time')
    //                                     ->minDate(Carbon::today())
    //                                     ->displayFormat('M d, Y h:i A') // 👈 12-hour format with AM/PM
    //                                     ->format('Y-m-d h:i A')         // 👈 stored as 12-hour AM/PM
    //                                     ->closeOnDateSelection(false)   // keep picker open to pick time
    //                                     ->seconds(false)                // hide seconds
    //                                     // ->default(Carbon::now())
    //                                     ->minutesStep(5)
    //                                     ->afterStateHydrated(function (callable $set, $record) {
    //                                         // when editing, make sure therapist_id is set for correct disabling
    //                                         if ($record && $record->therapist_id) {
    //                                             $set('therapist_id', $record->therapist_id);
    //                                         }
    //                                     })
    //                                     ->disabledDates(function (callable $get) {
    //                                         $therapistId = $get('therapist_id');
    //                                         $type = $get('type')->value;
    //                                         if (!$therapistId || $type == 'consult') {
    //                                             return [];
    //                                         }

    //                                         // Fetch all holidays for the selected therapist
    //                                         $holidays = Holiday::where('user_id', $therapistId)
    //                                             ->where('status', 'approved')
    //                                             ->get(['start_date', 'end_date']);

    //                                         $disabledDates = [];

    //                                         foreach ($holidays as $holiday) {
    //                                             $start = Carbon::parse($holiday->start_date);
    //                                             $end = Carbon::parse($holiday->end_date);

    //                                             // Generate all dates in the range
    //                                             while ($start->lte($end)) {
    //                                                 $disabledDates[] = $start->toDateString();
    //                                                 $start->addDay();
    //                                             }
    //                                         }

    //                                         return $disabledDates;
    //                                     }),

    //                                 // DatePicker::make('appointment_date')
    //                                 //     ->label('Appointment Date')
    //                                 //     ->required()
    //                                 //     ->native(false)
    //                                 //     ->placeholder('Select Appointment Date')
    //                                 //     ->minDate(Carbon::today())
    //                                 //     ->closeOnDateSelection()
    //                                 //     // ->default(Carbon::now())
    //                                 //     ->disabledDates(function (callable $get) {
    //                                 //         $therapistId = $get('therapist_id');
    //                                 //         if (!$therapistId) {
    //                                 //             return [];
    //                                 //         }

    //                                 //         // Fetch all holidays for the selected therapist
    //                                 //         $holidays = Holiday::where('user_id', $therapistId)
    //                                 //             // ->where('status', 'approved')
    //                                 //             ->get(['start_date', 'end_date']);

    //                                 //         $disabledDates = [];

    //                                 //         foreach ($holidays as $holiday) {
    //                                 //             $start = Carbon::parse($holiday->start_date);
    //                                 //             $end = Carbon::parse($holiday->end_date);

    //                                 //             // Generate all dates in the range
    //                                 //             while ($start->lte($end)) {
    //                                 //                 $disabledDates[] = $start->toDateString();
    //                                 //                 $start->addDay();
    //                                 //             }
    //                                 //         }

    //                                 //         return $disabledDates;
    //                                 //     }),

    //                                 // TimePicker::make('Appointment Time')
    //                                 //     // ->datalist([
    //                                 //     //     '09:00',
    //                                 //     //     '09:30',
    //                                 //     //     '10:00',
    //                                 //     //     '10:30',
    //                                 //     //     '11:00',
    //                                 //     //     '11:30',
    //                                 //     //     '12:00',
    //                                 //     // ])
    //                                 //     // ->default(function () {
    //                                 //     //     return $now = Carbon::now()->format('H:i');
    //                                 //     //     // Round to the next 30 minutes
    //                                 //     //     // $minutes = $now->minute >= 30 ? 0 : 30;
    //                                 //     //     // return $now->copy()->addMinutes(30 - ($now->minute % 30))->format('H:i');
    //                                 //     // })
    //                                 //     ->step(300)     
    //                                 //     ->withoutSeconds(),

    //                                 ToggleButtons::make('status')
    //                                     ->inline()
    //                                     ->options(AppointmentStatus::class)
    //                                     ->default('confirmed')
    //                                     ->required()
    //                                     ->columnSpan(['lg' => 2]),

    //                             ]),
    //                             Grid::make(2)->schema([
    //                                 Textarea::make('notes')->columnSpanFull()->placeholder('Notes'),
    //                             ])
    //                         ]),
    //                 ])
    //                 ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

    //             Section::make()
    //                 ->schema([
    //                     TextEntry::make('created_at')
    //                         ->label('Appointment created date')
    //                         ->state(fn ($record): ?string => $record->created_at?->diffForHumans()),

    //                     TextEntry::make('updated_at')
    //                         ->label('Last modified at')
    //                         ->state(fn ($record): ?string => $record->updated_at?->diffForHumans()),
    //                 ])
    //                 ->columnSpan(['lg' => 1])
    //                 ->hidden(fn ($record) => $record === null),
    //         ])
    //         ->columns(3);


    // }

    // public static function configure(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             Select::make('type')
    //                 ->options(['consult' => 'Consult', 'treatment' => 'Treatment'])
    //                 ->default('consult')
    //                 ->required(),
    //             Select::make('client_id')
    //                 ->relationship('client', 'id')
    //                 ->required(),
    //             Select::make('therapist_id')
    //                 ->relationship('therapist', 'id')
    //                 ->required(),
    //             Select::make('clinic_id')
    //                 ->relationship('clinic', 'name')
    //                 ->required(),
    //             Select::make('assessment_id')
    //                 ->relationship('assessment', 'id'),
    //             Select::make('treatment_session_id')
    //                 ->relationship('treatmentSession', 'title'),
    //             DateTimePicker::make('appointment_datetime')
    //                 ->required(),
    //             Select::make('status')
    //                 ->options(AppointmentStatus::class)
    //                 ->default('scheduled')
    //                 ->required(),
    //             TextInput::make('products_used'),
    //             TextInput::make('resources_used'),
    //             Textarea::make('notes')
    //                 ->columnSpanFull(),
    //             DateTimePicker::make('billed_at'),
    //         ]);
    // }
}
