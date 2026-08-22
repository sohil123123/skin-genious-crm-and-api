<?php

namespace App\Filament\Resources\Clinics\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Indicator;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

use App\Enums\AppointmentStatus;

use App\Models\Clinic;
use App\Models\AvailabilityException;
use App\Models\Appointment;

class HolidaysRelationManager extends RelationManager
{
    protected static string $relationship = 'holidays';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                Grid::make(2)->schema([
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
                                                    $exceptionableType = Clinic::class;
                                                    $exceptionableId   = $this->ownerRecord->id;
                                                    $type              = 'leave_full_day';

                                                    if (! $exceptionableType || ! $exceptionableId || ! $type)
                                                        return;

                                                    $newStart = Carbon::parse($value);
                                                    $newEnd   = Carbon::parse($get('end_date') ?? $value);

                                                    /**
                                                     * --------------------------------------------------
                                                     * CHECK FOR CONFLICTING APPOINTMENTS
                                                     * --------------------------------------------------
                                                     */

                                                    $conflictQuery = Appointment::query()
                                                        ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
                                                        ->where('clinic_id', $exceptionableId);

                                                    $shouldCheck = false;
                                                    $appCheckStart = $newStart->copy()->startOfDay();
                                                    $appCheckEnd   = $newEnd->copy()->endOfDay();

                                                    if ($appCheckStart && $appCheckEnd) {
                                                        $conflictQuery->where(function ($q) use ($appCheckStart, $appCheckEnd) {
                                                            $q->where('start_datetime', '<', $appCheckEnd)
                                                                ->where('end_datetime', '>', $appCheckStart);
                                                        });
                                                        $shouldCheck = true;
                                                    }

                                                    if ($shouldCheck && $conflictQuery->exists()) {
                                                        $details = "";
                                                        foreach ($conflictQuery->get() as $c) {
                                                            $sd = Carbon::parse($c->start_datetime);
                                                            $ed = Carbon::parse($c->end_datetime);
                                                            $details .= "• Appointment <b>{$sd->format('M d, H:i')} - {$ed->format('H:i')}</b> ({$c->status->value})<br>";
                                                        }
                                                        
                                                        $msg = "❌ <strong>Conflicting appointments:</strong><br>{$details}";
                                                            
                                                        $fail($msg);
                                                    }

                                                    $exists = AvailabilityException::query()
                                                        ->where('exceptionable_type', $exceptionableType)
                                                        ->where('exceptionable_id', $exceptionableId)
                                                        ->whereDate('start_date', '<=', $newEnd)
                                                        ->whereDate('end_date', '>=', $newStart)
                                                        ->when($record, fn ($q) => $q->whereKeyNot($record->id))
                                                        ->exists();

                                                    if ($exists)
                                                        $fail('A clinic holiday already exists for this date range.');

                                                    return;
                                                },
                                        ]),

                                    DatePicker::make('end_date')
                                        ->required()
                                        ->native(false)
                                        ->placeholder(now()->startOfMonth()->format('M d, Y'))
                                        ->format('Y-m-d')
                                        ->live()
                                        ->afterStateUpdated(fn ($livewire) => $livewire->validateOnly('start_date'))
                                        ->minDate(fn ($get) => $get('start_date'))
                                        ->disabledDates(function () {
                                            return disabled_sunday_dates();
                                        })
                                        ->closeOnDateSelection(true)
                                        ->rules([
                                            fn ($get) => function (string $attribute, $value, $fail) use ($get) {
                                                $startDate = $get('start_date');

                                                if (! $startDate || ! $value) return;

                                                if (Carbon::parse($value)->lt(Carbon::parse($startDate))) {
                                                    $fail('End date must be the same as or after the start date.');
                                                }
                                            },
                                        ]),
                                ]),
                                Grid::make(1)->schema([
                                    Textarea::make('notes')->rows(4)->placeholder('Notes')->required(),
                                ])
                            ]),
                    ])
                    ->columnSpan(['lg' => fn (?AvailabilityException $record) => $record === null ? 3 : 2]),

                Section::make()
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created date')
                            ->state(fn (AvailabilityException $record): ?string => $record->created_at?->diffForHumans()),

                        TextEntry::make('updated_at')
                            ->label('Last modified at')
                            ->state(fn (AvailabilityException $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?AvailabilityException $record) => $record === null),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordTitleAttribute('type')
            ->defaultSort('start_date', 'asc')
            ->columns([
                TextColumn::make('start_datetime')
                    ->label('Start')
                    ->badge()
                    ->color('success')
                    ->state(function ($record) {
                        return Carbon::parse($record->start_date)->format('M d, Y');
                    })
                    ->sortable(query: function ($query, $direction) {
                        $query->orderBy('start_date', $direction)->orderBy('start_time', $direction);
                    }),

                TextColumn::make('end_datetime')
                    ->label('End')
                    ->badge()
                    ->color('gray')
                    ->state(function ($record) {
                        return Carbon::parse($record->end_date)->format('M d, Y');
                    })
                    ->sortable(query: function ($query, $direction) {
                        $query->orderBy('end_date', $direction)->orderBy('end_time', $direction);
                    }),
                TextColumn::make('notes')->limit(50)->searchable()->toggleable()->placeholder('Note for holiday'),
                TextColumn::make('created_at')->dateTime(app_datetime_format())->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // 1) ✅ Quick checkboxes (single filter with a CheckboxList)
                Filter::make('quick')
                    ->label('Quick Filters')
                    ->form([
                        Section::make('Quick Filters')
                            ->icon('heroicon-o-clock')
                            ->schema([
                                CheckboxList::make('ranges')
                                    ->options([
                                        'today'      => 'Today',
                                        'yesterday'  => 'Yesterday',
                                        'this_week'  => 'This Week',
                                        'this_month' => 'This Month',
                                        'this_year'  => 'This Year',
                                    ])
                                    ->columns(2)
                                    ->bulkToggleable(),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $ranges = collect($data['ranges'] ?? []);
                        if ($ranges->isEmpty()) {
                            return $query;
                        }

                        // Combine selected quick ranges with OR logic
                        return $query->where(function (Builder $q) use ($ranges) {
                            if ($ranges->contains('today')) {
                                $q->orWhereDate('start_date', today());
                            }
                            if ($ranges->contains('yesterday')) {
                                $q->orWhereDate('start_date', today()->subDay());
                            }
                            if ($ranges->contains('this_week')) {
                                $q->orWhereBetween('start_date', [now()->startOfWeek(), now()->endOfWeek()]);
                            }
                            if ($ranges->contains('this_month')) {
                                $q->orWhereMonth('start_date', now()->month);
                            }
                            if ($ranges->contains('this_year')) {
                                $q->orWhereYear('start_date', now()->year);
                            }
                        });
                    })
                    // show nice chips for the selected quick filters
                    ->indicateUsing(function (array $data) {
                        $ranges = collect($data['ranges'] ?? []);
                        return $ranges->map(fn ($key) => match ($key) {
                            'today' => 'Today',
                            'yesterday' => 'Yesterday',
                            'this_week' => 'This Week',
                            'this_month' => 'This Month',
                            'this_year' => 'This Year',
                            default => null,
                        })->filter()->all();
                    }),

                // 2) 📅 Date range section (From / To)
                Filter::make('date_range')
                    ->form([
                        Section::make('Date Range')
                            ->icon('heroicon-o-calendar')
                            ->schema([
                                DatePicker::make('from')
                                    ->label('From Date')
                                    // ->minDate(Carbon::today())
                                    ->maxDate(fn ($get) => $get('to'))
                                    ->closeOnDateSelection()
                                    ->native(false)
                                    ->placeholder('From Date')
                                    ->reactive(),
                                DatePicker::make('to')
                                    ->label('To Date')
                                    ->minDate(fn ($get) => $get('from') ?? Carbon::today())
                                    ->closeOnDateSelection()
                                    ->native(false)
                                    ->placeholder('To Date')
                                    ->reactive(),
                            ])
                            ->columns(1)
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query) => $query->where('end_date', '>=', $data['from']))
                            ->when($data['to'], fn (Builder $query) => $query->where('start_date', '<=', $data['to']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From Date ' . Carbon::parse($data['from'])->toFormattedDateString())->removeField('from');
                        }

                        if ($data['to'] ?? null) {
                            $indicators[] = Indicator::make('To Date ' . Carbon::parse($data['to'])->toFormattedDateString())->removeField('to');
                        }

                        return $indicators;
                    }),
            ],
            layout: FiltersLayout::Modal)
            ->filtersFormColumns(2)
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->color('primary')
                    ->label('Filters')
                    ->icon('heroicon-o-funnel')
            )
            ->headerActions([
                CreateAction::make()
                    ->label('Add Holiday')
                    ->icon('heroicon-o-plus')
                    ->createAnother(false)
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['clinic_id'] = $this->ownerRecord->id;
                        $data['type'] = 'leave_full_day';
                        $data['status'] = 'approved';
                        $data['approved_by'] = auth()->id();
                        $data['approved_at'] = now();

                        return $data;
                    })
                    ->successNotification(
                        Notification::make()
                            ->title('Holiday Created')
                            ->body('The holiday has been added successfully.')
                            ->success()
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->successNotification(
                        Notification::make()
                            ->title('Holiday Updated')
                            ->body('Changes have been saved successfully.')
                            ->success()
                    ),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Holiday Deleted')
                            ->body("The holiday **" . Carbon::parse($record->start_date)->toFormattedDateString() . "** has been removed successfully.")
                            ->success();
                    }),
            ])
            ->emptyStateDescription('Once you create your first holiday, it will appear here.');
    }
}
