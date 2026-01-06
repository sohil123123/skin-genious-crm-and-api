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

class AppointmentsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        // Section::make('Appointment Type')
                        //     ->schema(static::getAppointmentTypeComponents())
                        //     ->columns(2),

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

    // public static function getAppointmentTypeComponents(): array
    // {
    //     return [
    //         ToggleButtons::make('type')
    //             ->inline()
    //             ->options(AppointmentType::class)
    //             ->default('treatment')
    //             ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
    //                 $set('assessment_id', null);
    //                 $set('treatment_session_id', null);
    //                 $set('appointment_datetime', null);
    //                 $type = $get('type') instanceof \BackedEnum
    //                     ? $get('type')->value
    //                     : $get('type');

    //                 if ($type === 'consult') {
    //                     $set('duration', config('project.appointment_consult_duration'));
    //                 } else {
    //                     $set('duration', get_treatment_session_duration($get('treatment_session_id')));
    //                 }
    //             })
    //             ->live()
    //             ->required(),
    //             // ->columnSpan(['lg' => 3]),
    //             // ->columnSpanFull(),
    //     ];
    // }

    public static function getClientAndTherapistComponents()
    {
        return [
            // Grid::make(2)->schema([
                TextEntry::make('type')->badge(),
                TextEntry::make('client.first_name')->label('Client Name'),
                // TextEntry::make('duration_minutes')
                //     ->badge()
                //     ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
                //     ->formatStateUsing(fn ($state) => $state . ' minutes'),
            // ]),

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

            auth()->user()->hasRole('therapist')
                ? Hidden::make('therapist_id')->default(auth()->id())
                : Select::make('therapist_id')
                    ->label('Therapist')
                    ->options(function (callable $get) {
                        $clinicId = $get('clinic_id') ?? auth()->user()->clinic_id;
                        return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                    })
                    ->searchable()
                    ->required()
                    ->placeholder('Select Therapist')
                    ->afterStateUpdated(fn ($state, callable $set) => $set('start_date', null))
                    ->live(),

            Select::make('assessment_id')
                ->label('Assessment')
                ->options(fn (callable $get, $record) =>
                    Assessment::where('user_id', $record->user_id)->pluck('id', 'id')
                )
                ->visible(fn (callable $get, $record) => $record->type->value === 'treatment')
                ->required(fn (callable $get, $record) => $record->type->value === 'treatment')
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('treatment_session_id', null);
                    $set('start_date', null);
                })
                ->live()
                ->placeholder('Select Assessment'),

            Select::make('treatment_session_id')
                ->label('Treatment Session')
                ->options(fn (callable $get) =>
                    $get('assessment_id') ? TreatmentSession::where('assessment_id', $get('assessment_id'))->pluck('title', 'id') : []
                )
                ->visible(fn (callable $get, $record) => $record->type->value === 'treatment')
                ->required(fn (callable $get, $record) => $record->type->value === 'treatment')
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('start_date', null);
                })
                ->live()
                ->placeholder('Select Treatment Session'),

            DatePicker::make('start_date')
                ->label('Date')
                ->required()
                ->native(false)
                ->closeOnDateSelection(true)
                ->placeholder(now()->startOfMonth()->format('M d, Y'))
                ->format('Y-m-d')
                ->live()
                ->minDate(today())
                ->allowHtmlValidationMessages()
                ->reactive(),

            Grid::make(2)->schema([
                Select::make('start_time')
                    ->options(time_options())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($livewire) => $livewire->validateOnly('start_date')),

                 Select::make('end_time')
                    ->options(time_options())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($livewire) => $livewire->validateOnly('start_date'))
                    ->rules([
                        fn ($get) => function (string $attribute, $value, $fail) use ($get) {
                            $start = $get('start_time');

                            if (! $start || ! $value) return;

                            $startTime = Carbon::createFromTimeString($start . ':00');
                            $endTime   = Carbon::createFromTimeString($value . ':00');

                            // End must be after start
                            if ($endTime->lessThanOrEqualTo($startTime)) {
                                $fail('End time must be after start time.');
                                return;
                            }
                        },
                    ]),
            ]),

            // STEP 4: STATUS
            ToggleButtons::make('status')
                ->inline()
                // ->options(AppointmentStatus::class)
                ->options(
                    collect(AppointmentStatus::cases())
                        ->reject(fn ($case) => in_array(
                            $case,
                            [AppointmentStatus::InProgress],
                            true
                        ))
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getLabel(),
                        ])
                        ->all()
                )
                ->colors(
                    collect(AppointmentStatus::cases())
                        ->reject(fn ($case) => in_array(
                            $case,
                            [AppointmentStatus::InProgress],
                            true
                        ))
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getColor(),
                        ])
                        ->all()
                )
                ->icons(
                    collect(AppointmentStatus::cases())
                        ->reject(fn ($case) => in_array(
                            $case,
                            [AppointmentStatus::InProgress],
                            true
                        ))
                        ->mapWithKeys(fn ($case) => [
                            $case->value => $case->getIcon(),
                        ])
                        ->all()
                )
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
