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
use App\Enums\AvailabilityExceptionType;

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
                // ->options(fn ($get) =>
                //     check_role('super_admin') || check_role('clinic_manager')
                //         ? [
                //             User::class   => 'Therapist',
                //             Clinic::class => 'Clinic (Holiday)',
                //         ]
                //         : [
                //             User::class   => 'Therapist',
                //         ]
                // )
                ->options([
                    User::class   => 'Therapist',
                    Clinic::class => 'Clinic (Holiday)',
                ])
                ->default(User::class)
                ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                    $set('exceptionable_id', null);
                    $set('clinic_id', null);
                    // $set('type', null);
                    $set('start_date', null);
                    $set('end_date', null);
                    $set('leave_type', null);

                    // Force leave_full_day when clinic selected
                    // if ($state === Clinic::class) {
                        $set('type', 'leave_full_day');
                    // }
                })
                ->live()
                ->required(),
        ];
    }

    public static function getClinicAndTherapistComponents()
    {
        return [
            Grid::make(2)->schema([
                Select::make('clinic_id')
                    ->label('Clinic')
                    ->relationship('clinic', 'name')
                    ->required()
                    ->placeholder('Select Clinic')
                    ->visible(fn ($get) => check_role('super_admin') && $get('exceptionable_type') == User::class)
                    ->live(),

                Select::make('exceptionable_id')
                    ->label(fn ($get) => $get('exceptionable_type') == Clinic::class ? 'Clinic' : 'Therapist')
                    ->options(function (callable $get) {
                        if ($get('exceptionable_type') == Clinic::class)
                            return Clinic::active()->pluck('name', 'id');

                        $clinicId = auth()->user()->clinic_id ?? $get('clinic_id');

                        return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->live()
                    // ->visible(fn ($get) => ! check_role('therapist') && $get('exceptionable_type') == User::class),
                    ->visible(fn ($get) => check_role('super_admin') || (check_role('clinic_manager') && $get('exceptionable_type') == User::class)),
            ])
            ->visible(fn ($get) => check_role('super_admin') || check_role('clinic_manager')),

            Grid::make(2)->schema([
                ToggleButtons::make('type')
                    ->inline()
                    ->options(AvailabilityExceptionType::class)
                    ->default('leave_full_day')
                    ->afterStateUpdated(function ($state, callable $set, $get, $livewire) {
                        // Keep your existing logic
                        $set('end_date', $get('start_date'));
                        $livewire->validateOnly('start_date');
                    })
                    ->live()
                    ->required(),

                ToggleButtons::make('leave_type')
                    ->inline()
                    ->options(LeaveType::class)
                    ->default(LeaveType::Paid->value)
                    ->required(fn ($get) => $get('type')->value === AvailabilityExceptionType::LeaveFullDay->value)
                    ->visible(fn ($get) => str_starts_with((string)$get('type')->value, 'leave') && $get('exceptionable_type') == User::class),

            ])
            ->visible(fn ($get) => $get('exceptionable_type') === User::class),

            Grid::make(4)->schema([

                DatePicker::make('start_date')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection(true)
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
                                $exceptionableId   = check_role('therapist') ? auth()->id() : $get('exceptionable_id');
                                $type              = $get('type')->value;

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

                                    if ($exists)
                                        $fail('A clinic holiday already exists for this date range.');

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
                                $baseQuery = AvailabilityException::query()
                                    ->where('exceptionable_type', User::class)
                                    ->where('exceptionable_id', $exceptionableId)
                                    ->where('status', '<>', 'rejected')
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

                                if ($type === 'leave_full_day') {
                                    /**
                                     * 2️⃣ Other leave types (no leave_full_day)
                                     */
                                    $conflicts = (clone $baseQuery)->where('type', '!=', 'leave_full_day');
                                    if ($conflicts->exists()) {
                                        $details = "";
                                        foreach ($conflicts->get() as $c) {
                                            $sd = Carbon::parse($c->start_date);
                                            $ed = Carbon::parse($c->end_date);
                                            $details .= "• <strong>{$c->type->value}</strong> already exists for <b>{$sd->format('M d, Y')}: {$c->start_time} → {$ed->format('M d, Y')}: {$c->end_time}</b><br>";
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

                                if (! $startTime || ! $endTime) return;

                                $startTime = Carbon::createFromTimeString($startTime . ':00');
                                $endTime   = Carbon::createFromTimeString($endTime . ':00');

                                $conflicts = (clone $baseQuery)
                                    ->where('type', '!=', 'leave_full_day')
                                    ->where(function ($q) use ($startTime, $endTime) {
                                        // overlap rule: existing.start < new.end AND existing.end > new.start
                                        $q->where('start_time', '<', $endTime->format('H:i:s'))
                                        ->where('end_time',   '>', $startTime->format('H:i:s'));
                                    });

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
                    ->closeOnDateSelection(true)
                    ->afterOrEqual('start_date')
                    ->readOnly(fn ($get) => $get('type')->value !== 'leave_full_day')
                    ->rules([
                        fn ($get) => function (string $attribute, $value, $fail) use ($get) {
                            $startDate = $get('start_date');

                            if (! $startDate || ! $value) return;

                            if (Carbon::parse($value)->lt(Carbon::parse($startDate))) {
                                $fail('End date must be the same as or after the start date.');
                            }
                        },
                    ]),

                Select::make('start_time')
                    ->options(time_options())
                    ->visible(fn ($get) => $get('type')->value !== 'leave_full_day')
                    ->required(fn ($get) => $get('type')->value !== 'leave_full_day')
                    ->dehydrateStateUsing(fn ($state, $get) =>
                        $get('type')->value === 'leave_full_day' ? null : ($state ? $state : null)
                    ),

                Select::make('end_time')
                    ->options(time_options())
                    ->visible(fn ($get) => $get('type')->value !== 'leave_full_day')
                    ->required(fn ($get) => $get('type')->value !== 'leave_full_day')
                    ->dehydrateStateUsing(fn ($state, $get) =>
                        $get('type')->value === 'leave_full_day' ? null : ($state ? $state : null)
                    )
                    ->rules([
                        fn ($get) => function (string $attribute, $value, $fail) use ($get) {

                            // Only apply for partial leave
                            if ($get('type')->value !== 'leave_partial') return;

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
            ]),

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
                            ->visible(fn ($get) => check_role('super_admin'))
                            ->columns(2),

                        Section::make()
                            ->schema(static::getClinicAndTherapistComponents())
                            ->columns(1),
                    ])
                    ->columnSpan(['lg' => 3]),
                    // ->columnSpan(['lg' => fn ($record) => $record === null ? 3 : 2]),

                // Section::make()
                //     ->schema([
                //         TextEntry::make('created_at')
                //             ->label('Availability created')
                //             ->state(fn ($record): ?string => $record->created_at?->diffForHumans()),

                //         TextEntry::make('updated_at')
                //             ->label('Last modified')
                //             ->state(fn ($record): ?string => $record->updated_at?->diffForHumans()),
                //     ])
                //     ->columnSpan(['lg' => 1])
                //     ->hidden(fn ($record) => $record === null),
                    ]);
            // ->columns(3);
    }

}
