<?php

namespace App\Filament\Resources\AvailabilityExceptions\Schemas;

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

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Closure;

use App\Enums\LeaveType;

use App\Models\AvailabilityException;
use App\Models\User;
use App\Models\Clinic;

class AvailabilityExceptionForm
{
    public static function getTypeComponents(): array
    {
        return [
            ToggleButtons::make('exceptionable_type')
                ->label('Applies To')
                ->inline()
                ->options(fn ($get) =>
                    auth()->user()->hasRole('super_admin')
                        ? [
                            User::class   => 'Therapist',
                            Clinic::class => 'Clinic (Holiday)',
                        ]
                        : [
                            User::class   => 'Therapist',
                        ]
                )
                ->default(User::class)
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('exceptionable_id', null);
                    $set('clinic_id', null);
                    $set('type', null);
                    $set('start_date', null);
                    $set('end_date', null);
                    $set('leave_type', null);

                    // Force leave_full_day when clinic selected
                    if ($state === Clinic::class) {
                        $set('type', 'leave_full_day');
                    }
                })
                ->live()
                ->required(),
        ];
    }

    public static function getClinicAndTherapistComponents()
    {
        return [
            auth()->user()->hasRole('super_admin')
                ? Select::make('clinic_id')
                    ->label('Clinic')
                    ->relationship('clinic', 'name')
                    // ->searchable()
                    // ->preload()
                    ->required()
                    ->placeholder('Select Clinic')
                    ->visible(fn ($get) => $get('exceptionable_type') == User::class)
                    ->live()
                : Hidden::make('clinic_id')->default(auth()->user()->clinic_id),

            auth()->user()->hasRole('therapist')
                ? Hidden::make('exceptionable_id')->default(auth()->id())
                : Select::make('exceptionable_id')
                    ->label(fn ($get) => $get('exceptionable_type') == Clinic::class ? 'Clinic (Holiday)' : 'Therapist')
                    ->options(function (callable $get) {
                        if ($get('exceptionable_type') == Clinic::class)
                            return Clinic::active()->pluck('name', 'id');

                        return User::active()->role('therapist')->where('clinic_id', $get('clinic_id'))->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                    })
                    // ->searchable()
                    ->required()
                    // ->placeholder('Select Therapist')
                    ->live(),

            Select::make('type')
                ->required()
                ->live()
                ->options(fn ($get) =>
                    $get('exceptionable_type') === Clinic::class
                        ? [
                            'leave_full_day' => 'Full Day Leave',
                        ]
                        : [
                            'leave_full_day' => 'Full Day Leave',
                            'leave_partial'  => 'Partial Leave',
                            'extra_hours'    => 'Extra Working Hours',
                            'override_hours' => 'Override Hours',
                            'blocked_hours'  => 'Blocked Hours',
                        ]
                )
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    // Keep your existing logic
                    $set('end_date', $get('start_date'));
                    $livewire->validateOnly('start_date');
                }),

            DatePicker::make('start_date')
                ->required()
                ->native(false)
                ->closeOnDateSelection(false)
                ->placeholder(now()->startOfMonth()->format('M d, Y'))
                ->format('Y-m-d')
                ->live()
                ->minDate(today())
                ->disabledDates(function () {
                    return disabled_sunday_dates();
                })
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('end_date', $state);
                    $livewire->validateOnly('start_date'); // ✅ triggers instant revalidation
                })
                ->allowHtmlValidationMessages()
                ->reactive()
                ->rules([
                    fn ($get, ?AvailabilityException $record) =>
                        function (string $attribute, $value, $fail) use ($get, $record) {

                            $exceptionableType = $get('exceptionable_type');
                            $exceptionableId   = $get('exceptionable_id');
                            $type              = $get('type');

                            if (! $exceptionableType || ! $exceptionableId || ! $type)
                                return;

                            $newStart = Carbon::parse($value);
                            $newEnd   = Carbon::parse($get('end_date') ?? $value);

                            /**
                             * ==================================================
                             * CASE A — CLINIC
                             * ==================================================
                             */
                            if ($exceptionableType === Clinic::class) {

                                $exists = AvailabilityException::query()
                                    ->where('exceptionable_type', Clinic::class)
                                    ->where('exceptionable_id', $exceptionableId)
                                    ->whereDate('start_date', '<=', $newEnd)
                                    ->whereDate('end_date', '>=', $newStart)
                                    ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                                    ->exists();

                                if ($exists) {
                                    $fail('A clinic availability exception already exists for this date range.');
                                }

                                return;
                            }

                            /**
                             * ==================================================
                             * CASE B — USER
                             * ==================================================
                             */

                            /**
                             * B1 — Full-day leave (date overlap only)
                             */
                            if ($type === 'leave_full_day') {
                                $baseQuery = AvailabilityException::query()
                                    ->where('exceptionable_type', User::class)
                                    ->where('exceptionable_id', $exceptionableId)
                                    ->whereDate('start_date', '<=', $newEnd)
                                    ->whereDate('end_date', '>=', $newStart)
                                    ->when($record, fn ($q) => $q->whereKeyNot($record->id));
                                    // ->exists();

                                /**
                                 * 1️⃣ Full-day leave check
                                 */
                                $fullDayExists = (clone $baseQuery)->where('type', 'leave_full_day')->exists();
                                if ($fullDayExists)
                                    $fail('A full-day leave already exists for this date range.');

                                /**
                                 * 2️⃣ Other leave types (no leave_full_day)
                                 */
                                $conflicts = (clone $baseQuery)->where('type', '!=', 'leave_full_day');
                                if ($conflicts->exists()) {
                                    $details = "";
                                    foreach ($conflicts->get() as $c) {
                                        $sd = Carbon::parse($c->start_date);
                                        $ed = Carbon::parse($c->end_date);
                                        $details .= "• <strong>{$c->type}</strong> already exists for <b>{$sd->format('M d, Y')}: {$c->start_time} → {$ed->format('M d, Y')}: {$c->end_time}</b><br>";
                                    }
                                    $fail("❌ <strong>Conflicting exceptions:</strong><br>{$details}");
                                }
                                return;
                            }

                            /**
                             * B2 — Time-based leave (date + time overlap)
                             */
                            $startTime = $get('start_time');
                            $endTime   = $get('end_time');

                            if (! $startTime || ! $endTime) {
                                return;
                            }

                            $startTime = Carbon::createFromTimeString($startTime . ':00');
                            $endTime   = Carbon::createFromTimeString($endTime . ':00');

                            $conflicts = AvailabilityException::query()
                                ->where('exceptionable_type', User::class)
                                ->where('exceptionable_id', $exceptionableId)
                                ->where('type', '!=', 'leave_full_day')
                                ->whereDate('start_date', '<=', $newEnd)
                                ->whereDate('end_date', '>=', $newStart)
                                ->where(function ($q) use ($startTime, $endTime) {
                                    // overlap rule: existing.start < new.end AND existing.end > new.start
                                    $q->where('start_time', '<', $endTime->format('H:i:s'))
                                    ->where('end_time',   '>', $startTime->format('H:i:s'));
                                })
                                ->when($record, fn ($q) => $q->whereKeyNot($record->id));

                            if ($conflicts->exists()) {
                                $details = "";
                                foreach ($conflicts->get() as $c) {
                                    $sd = Carbon::parse($c->start_date);
                                    $ed = Carbon::parse($c->end_date);
                                    $details .= "• <strong>{$c->type}</strong> already exists for <b>{$sd->format('M d, Y')}: {$c->start_time} → {$ed->format('M d, Y')}: {$c->end_time}</b><br>";
                                }
                                $fail("❌ <strong>Conflicting exceptions:</strong><br>{$details}");
                            }

                        },
                ]),
                // ->rules([
                //     fn ($get, ?AvailabilityException $record) =>
                //         function (string $attribute, $value, $fail) use ($get, $record) {

                //             $exceptionableType = $get('exceptionable_type');
                //             $exceptionableId   = $get('exceptionable_id');
                //             $type              = $get('type');
                //             $newStart = Carbon::parse($value);
                //             $newEnd   = Carbon::parse($get('end_date') ?? $value);

                //             if (! $exceptionableType || ! $exceptionableId || ! $type) return;

                //             /*
                //             * --------------------------------------------------
                //             * BASE RULE (Clinic + User)
                //             * Block ANY overlapping date range
                //             * --------------------------------------------------
                //             */
                //             $baseExists = AvailabilityException::query()
                //                 ->where('exceptionable_type', $exceptionableType)
                //                 ->where('exceptionable_id', $exceptionableId)
                //                 ->whereDate('start_date', '<=', $newEnd)
                //                 ->whereDate('end_date', '>=', $newStart)
                //                 ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                //                 ->exists();

                //             if ($baseExists) {
                //                 $fail('An availability exception already exists for this date.');
                //                 return;
                //             }

                //             /*
                //             * --------------------------------------------------
                //             * USER-SPECIFIC RULES
                //             * --------------------------------------------------
                //             */
                //             if ($exceptionableType !== User::class) {
                //                 return;
                //             }

                //             /*
                //             * Full-day leave → already blocked by base rule
                //             */
                //             if ($type === 'leave_full_day') {
                //                 return;
                //             }

                //             /*
                //             * Time-based leave → allow same date ONLY if no time overlap
                //             */
                //             $startTime = $get('start_time');
                //             $endTime   = $get('end_time');

                //             if (! $startTime || ! $endTime) return;

                //             $startTime = Carbon::createFromTimeString($startTime . ':00');
                //             $endTime   = Carbon::createFromTimeString($endTime . ':00');

                //             $overlapExists = AvailabilityException::query()
                //                 ->where('exceptionable_type', User::class)
                //                 ->where('exceptionable_id', $exceptionableId)
                //                 ->where('type', '!=', 'leave_full_day')
                //                 ->whereDate('start_date', '<=', $newEnd)
                //                 ->whereDate('end_date', '>=', $newStart)
                //                 ->where(function ($q) use ($startTime, $endTime) {
                //                     // existing.start < new.end AND existing.end > new.start
                //                     $q->where('start_time', '<', $endTime->format('H:i:s'))
                //                     ->where('end_time',   '>', $startTime->format('H:i:s'));
                //                 })
                //                 ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                //                 ->exists();

                //             if ($overlapExists) {
                //                 $fail('An overlapping leave already exists for this time range.');
                //             }
                //         },
                // ]),

            DatePicker::make('end_date')
                ->required()
                ->native(false)
                ->placeholder(now()->startOfMonth()->format('M d, Y'))
                ->format('Y-m-d')
                // ->minDate(today())
                ->minDate(fn ($get) => $get('start_date'))
                ->disabledDates(function () {
                    return disabled_sunday_dates();
                })
                ->closeOnDateSelection(false)
                ->afterOrEqual('start_date')
                ->rules([
                    fn ($get) => function (string $attribute, $value, $fail) use ($get) {
                        $startDate = $get('start_date');

                        if (! $startDate || ! $value) {
                            return;
                        }

                        if (Carbon::parse($value)->lt(Carbon::parse($startDate))) {
                            $fail('End date must be the same as or after the start date.');
                        }
                    },
                ]),

            Select::make('start_time')
                ->options(time_options())
                ->visible(fn ($get) => $get('type') !== 'leave_full_day')
                ->required(fn ($get) => $get('type') !== 'leave_full_day')
                ->dehydrateStateUsing(fn ($state, $get) =>
                    $get('type') === 'leave_full_day' ? null : ($state ? $state : null)
                ),

            Select::make('end_time')
                ->options(time_options())
                ->visible(fn ($get) => $get('type') !== 'leave_full_day')
                ->required(fn ($get) => $get('type') !== 'leave_full_day')
                ->dehydrateStateUsing(fn ($state, $get) =>
                    $get('type') === 'leave_full_day' ? null : ($state ? $state : null)
                )
                ->rules([
                    fn ($get) => function (string $attribute, $value, $fail) use ($get) {

                        // Only apply for partial leave
                        if ($get('type') !== 'leave_partial') return;

                        $start = $get('start_time');

                        if (! $start || ! $value) return;

                        $startTime = Carbon::createFromTimeString($start . ':00');
                        $endTime   = Carbon::createFromTimeString($value . ':00');

                        // End must be after start
                        if ($endTime->lessThanOrEqualTo($startTime)) {
                            $fail('End time must be after start time.');
                            return;
                        }

                        $minutes = $startTime->diffInMinutes($endTime);

                        if ($minutes > 240) $fail('Partial leave cannot exceed 4 hours.');
                    },
                ]),

            Select::make('leave_type')
                ->required(fn ($get) => $get('type') === 'leave_full_day')
                ->options(LeaveType::class)
                ->visible(fn ($get) => str_starts_with((string)$get('type'), 'leave') && $get('exceptionable_type') == User::class),

            Textarea::make('reason')
                ->required()
                ->placeholder('Reason for Leave')
                ->columnSpanFull()
                ->visible(fn ($get) => $get('exceptionable_type') == User::class),

            Textarea::make('notes')
                ->placeholder('Additional notes')
                ->columnSpanFull(),

        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema(static::getTypeComponents())
                            ->columns(2),

                        Section::make()
                            ->schema(static::getClinicAndTherapistComponents())
                            ->columns(2),
                    ])
                    ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Availability created')
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

    // public static function configure(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             Group::make()
    //                 ->schema([
    //                     Section::make('Availability Exception Details')
    //                         ->schema([
    //                             auth()->user()->hasRole('super_admin')
    //                                 ? Select::make('exceptionable_type')
    //                                     ->label('Applies To')
    //                                     ->options([
    //                                         User::class   => 'Therapist',
    //                                         Clinic::class => 'Clinic (Holiday)',
    //                                     ])
    //                                     ->required()
    //                                     ->live()
    //                                     ->afterStateUpdated(fn ($state, callable $set) => $set('exceptionable_id', null))
    //                                 : Hidden::make('exceptionable_type')->default(User::class),

    //                             auth()->user()->hasRole('super_admin')
    //                                 ? Select::make('clinic_id')
    //                                     ->label('Clinic')
    //                                     ->relationship('clinic', 'name')
    //                                     // ->searchable()
    //                                     // ->preload()
    //                                     ->required()
    //                                     ->placeholder('Select Clinic')
    //                                     ->visible(fn ($get) => $get('exceptionable_type') == User::class)
    //                                     ->live()
    //                                 : Hidden::make('clinic_id')->default(auth()->user()->clinic_id),

    //                             auth()->user()->hasRole('therapist')
    //                                 ? Hidden::make('exceptionable_id')->default(auth()->id())
    //                                 : Select::make('exceptionable_id')
    //                                     ->label(fn ($get) => $get('exceptionable_type') == Clinic::class ? 'Clinic (Holiday)' : 'Therapist')
    //                                     ->options(function (callable $get) {
    //                                         return User::active()->role('therapist')->where('clinic_id', $get('clinic_id'))->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
    //                                     })
    //                                     // ->searchable()
    //                                     ->required()
    //                                     // ->placeholder('Select Therapist')
    //                                     ->live(),

    //                             Select::make('type')
    //                                 ->options([
    //                                     'leave_full_day' => 'Full Day Leave',
    //                                     'leave_partial'  => 'Partial Leave',
    //                                     'extra_hours'    => 'Extra Working Hours',
    //                                     'override_hours' => 'Override Hours',
    //                                     'blocked_hours'  => 'Blocked Hours',
    //                                 ])
    //                                 ->required()
    //                                 ->live()
    //                                 ->afterStateUpdated(function (string $state, $get, $set) {
    //                                     // if (in_array($state, [
    //                                     //     'leave_partial',
    //                                     //     'extra_hours',
    //                                     //     'override_hours',
    //                                     //     'blocked_hours',
    //                                     // ])) {
    //                                         $set('end_date', $get('start_date'));
    //                                     // }
    //                                 }),

    //                             // Select::make('effect')
    //                             //     ->options([
    //                             //         'block'    => 'Block Time',
    //                             //         'add'      => 'Add Time',
    //                             //         'override' => 'Override Schedule',
    //                             //     ])
    //                             //     ->required(),

    //                             DatePicker::make('start_date')
    //                                 ->required()
    //                                 ->native(false)
    //                                 ->closeOnDateSelection(false)
    //                                 ->placeholder(now()->startOfMonth()->format('M d, Y'))
    //                                 ->format('Y-m-d')
    //                                 ->live()
    //                                 ->minDate(now())
    //                                 ->disabledDates(function () {
    //                                     return disabled_sunday_dates();
    //                                 })
    //                                 ->afterStateUpdated(function ($state, $get, $set) {
    //                                     // if (in_array($get('type'), [
    //                                     //     'leave_partial',
    //                                     //     'extra_hours',
    //                                     //     'override_hours',
    //                                     //     'blocked_hours',
    //                                     // ])) {
    //                                         $set('end_date', $state);
    //                                     // }
    //                                 }),
    //                             DatePicker::make('end_date')
    //                                 ->required()
    //                                 ->native(false)
    //                                 ->placeholder(now()->startOfMonth()->format('M d, Y'))
    //                                 ->format('Y-m-d')
    //                                 ->disabledDates(function () {
    //                                     return disabled_sunday_dates();
    //                                 })
    //                                 ->closeOnDateSelection(false),

    //                             Select::make('start_time')
    //                                 ->options(time_options())
    //                                 ->visible(fn ($get) => $get('type') !== 'leave_full_day')
    //                                 ->dehydrateStateUsing(fn ($state, $get) =>
    //                                     $get('type') === 'leave_full_day' ? null : ($state ? $state : null)
    //                                 ),

    //                             Select::make('end_time')
    //                                 ->options(time_options())
    //                                 ->visible(fn ($get) => $get('type') !== 'leave_full_day')
    //                                 ->required(fn ($get) => $get('type') !== 'leave_full_day')
    //                                 ->dehydrateStateUsing(fn ($state, $get) =>
    //                                     $get('type') === 'leave_full_day' ? null : ($state ? $state : null)
    //                                 )
    //                                 ->rules([
    //                                     fn ($get) => function (string $attribute, $value, $fail) use ($get) {

    //                                         // Only apply for partial leave
    //                                         if ($get('type') !== 'leave_partial') {
    //                                             return;
    //                                         }

    //                                         $start = $get('start_time');

    //                                         if (! $start || ! $value) {
    //                                             return;
    //                                         }

    //                                         $startTime = Carbon::createFromTimeString($start . ':00');
    //                                         $endTime   = Carbon::createFromTimeString($value . ':00');

    //                                         // End must be after start
    //                                         if ($endTime->lessThanOrEqualTo($startTime)) {
    //                                             $fail('End time must be after start time.');
    //                                             return;
    //                                         }

    //                                         $minutes = $startTime->diffInMinutes($endTime);

    //                                         if ($minutes > 240) {
    //                                             $fail('Partial leave cannot exceed 4 hours.');
    //                                         }
    //                                     },
    //                                 ]),

    //                             Select::make('leave_type')
    //                                 ->required(fn ($get) => $get('type') === 'leave_full_day')
    //                                 ->options(LeaveType::class)
    //                                 ->visible(fn ($get) => str_starts_with((string)$get('type'), 'leave')),

    //                             Textarea::make('reason')->required()->columnSpanFull(),
    //                             Textarea::make('notes')->columnSpanFull(),

    //                         ])
    //                         ->columns(3),

    //             ])
    //             ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

    //             Section::make()
    //                 ->schema([
    //                     TextEntry::make('created_at')
    //                         ->label('Holiday created date')
    //                         ->state(fn ($record): ?string => $record->created_at?->diffForHumans()),

    //                     TextEntry::make('updated_at')
    //                         ->label('Last modified at')
    //                         ->state(fn ($record): ?string => $record->updated_at?->diffForHumans()),
    //                 ])
    //                 ->columnSpan(['lg' => 1])
    //                 ->hidden(fn ($record) => $record === null),
    //         ]);
    // }

}
