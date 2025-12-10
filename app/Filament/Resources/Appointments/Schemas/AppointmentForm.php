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
use Filament\Forms\Components\Placeholder;

use Illuminate\Support\Carbon;
use Closure;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Rules\TherapistAvailabilityRule;
use App\Rules\ClinicBedAvailabilityRule;

use App\Models\User;
use App\Models\Clinic;
use App\Models\Assessment;
use App\Models\TreatmentSession;
use App\Models\Holiday;

use App\Services\AppointmentSlotService;

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
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('assessment_id', null);
                    $set('treatment_session_id', null);
                    $set('appointment_datetime', null);
                    $type = $get('type') instanceof \BackedEnum
                        ? $get('type')->value
                        : $get('type');

                    if ($type === 'consult') {
                        $set('duration', config('project.appointment_consult_duration'));
                    } else {
                        $set('duration', get_treatment_session_duration($get('treatment_session_id')));
                    }
                })
                ->live()
                ->required(),
                // ->columnSpan(['lg' => 3]),
                // ->columnSpanFull(),
        ];
    }

    public static function getClientAndTherapistComponents()
    {
        return [
            Hidden::make('duration')->default(0),

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
                ->afterStateUpdated(fn ($state, callable $set) => $set('assessment_id', null))
                ->live()
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
                    $get('user_id')
                        ? Assessment::where('user_id', $get('user_id'))->pluck('id', 'id')
                        : []
                )
                ->visible(fn (callable $get) => $get('type')->value === 'treatment')
                ->required(fn (callable $get) => $get('type')->value === 'treatment')
                // ->afterStateUpdated(fn ($state, callable $set) => $set('treatment_session_id', null))
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('treatment_session_id', null);
                    $set('appointment_datetime', null);
                })
                ->live()
                ->placeholder('Select Assessment'),

            Select::make('treatment_session_id')
                ->label('Treatment Session')
                ->options(fn (callable $get) =>
                    $get('assessment_id')
                        ? TreatmentSession::where('assessment_id', $get('assessment_id'))->pluck('title', 'id')
                        : []
                )
                ->visible(fn (callable $get) => $get('type')->value === 'treatment')
                ->required(fn (callable $get) => $get('type')->value === 'treatment')
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('appointment_datetime', null);

                    $type = $get('type') instanceof \BackedEnum
                        ? $get('type')->value
                        : $get('type');

                    if ($type === 'consult') {
                        $set('duration', config('project.appointment_consult_duration')); // consult = 90 min
                    } else {
                        $set('duration', get_treatment_session_duration($get('treatment_session_id')));
                    }
                })
                ->live()
                ->placeholder('Select Treatment Session'),

            DateTimePicker::make('appointment_datetime')
                ->label('Appointment Date & Time')
                ->native(false)
                ->placeholder(now()->startOfMonth()->format('M d, Y h:i A'))
                ->minDate(Carbon::today())
                ->displayFormat('M d, Y h:i A')  // 12-hour AM/PM
                ->format('Y-m-d h:i A')
                ->minutesStep(5)
                ->closeOnDateSelection(false)
                ->required()
                ->afterStateHydrated(function (callable $set, $record) {
                    if ($record && $record->therapist_id) {
                        $set('therapist_id', $record->therapist_id);
                    }
                })
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $type = $get('type') instanceof \BackedEnum
                        ? $get('type')->value
                        : $get('type');

                    if ($type === 'consult') {
                        $set('duration', config('project.appointment_consult_duration')); // consult = 90 min
                    } else {
                        $set('duration', get_treatment_session_duration($get('treatment_session_id')));
                    }

                    $livewire->validateOnly('appointment_datetime'); // ✅ triggers instant revalidation
                })
                ->disabledDates(function (callable $get) {
                    $therapistId = $get('therapist_id');
                    if (!$therapistId) return [];

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
                ->rules([
                    fn (callable $get, $record) => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                        // 1️⃣ Block selecting past date/time
                        if (!$value) return;

                        $datetime = Carbon::parse($value);
                        $now = Carbon::now();

                        $type = $get('type') instanceof \BackedEnum
                            ? $get('type')->value
                            : $get('type');

                        $duration = $type === 'consult' ? $get('duration') : get_treatment_session_duration($get('treatment_session_id'));

                        // 1️⃣ Block Past Date/Time
                        if ($datetime->isPast())
                            return $fail("❌ You cannot select a past date/time.");

                        if ($datetime->isToday() && $datetime->lt($now))
                            return $fail("❌ Selected time has already passed.");

                        // 2️⃣ Clinic Hours Check
                        $clinic = Clinic::find($get('clinic_id'));
                        if (!$clinic) return;

                        $clinicStart = Carbon::parse($clinic->start_time);
                        $clinicEnd   = Carbon::parse($clinic->end_time)->subMinutes($duration);

                        if ($datetime->format('H:i:s') < ($clinicStart->format('H:i:s')) || $datetime->format('H:i:s') > ($clinicEnd->format('H:i:s'))) {
                            return $fail("❌ Appointment must be within clinic hours: " .
                                $clinicStart->format('h:i A') . " – " . $clinicEnd->format('h:i A'));
                        }

                        // 3️⃣ Therapist Availability Check
                        $therapistRule = new TherapistAvailabilityRule(
                            therapistId: $get('therapist_id'),
                            clinicId: $get('clinic_id'),
                            start: $datetime,
                            duration: $duration,
                            excludeId: $record?->id,
                        );
                        $therapistRule->validate($attribute, $value, $fail);

                        // 4️⃣ Bed Availability Check
                        $bedRule = new ClinicBedAvailabilityRule(
                            clinic: $clinic,
                            start: $datetime,
                            duration: $duration,
                            excludeId: $record?->id
                        );
                        $bedRule->validate($attribute, $value, $fail);

                    }
                ])
                ->hint(function ($get) {
                    $clinic = Clinic::find($get('clinic_id'));
                    if (!$clinic) return "Select clinic to see available timing";

                    $duration = $get('duration');

                    $start = Carbon::parse($clinic->start_time)->format('h:i A');
                    $end   = Carbon::parse($clinic->end_time)->subMinutes($duration)->format('h:i A');

                    return "Clinic hours: {$start} – {$end} | Duration: {$duration} minutes";
                })
                ->allowHtmlValidationMessages()
                ->reactive()
                ->live(),
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


}
