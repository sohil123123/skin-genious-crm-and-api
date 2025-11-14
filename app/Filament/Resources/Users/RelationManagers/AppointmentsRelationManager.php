<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Hidden;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group as FromGroup;
use Filament\Tables\Grouping\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Indicator;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\Action;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;
use App\Filament\Resources\Appointments\AppointmentResource;

use App\Models\TreatmentSession;
use App\Models\Assessment;
use App\Models\User;
use App\Models\Holiday;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Rules\AppointmentAvailability;

class AppointmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'appointments';

    // public function getDefaultActiveTab(): string | int | null
    // {
    //     return 'pending'; // Default selected tab
    // }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-calendar-days')
                ->badge(fn () => $this->ownerRecord->appointments()->count())
                ->badgeColor('gray'),

            'scheduled' => Tab::make('Scheduled')
                ->icon('heroicon-o-clock')
                ->badge(fn () => $this->ownerRecord->appointments()
                    ->where('status', AppointmentStatus::Scheduled->value)
                    ->count()
                )
                ->badgeColor('gray')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', AppointmentStatus::Scheduled->value)
                ),

            'confirmed' => Tab::make('Confirmed')
                ->icon('heroicon-o-check-circle')
                ->badge(fn () => $this->ownerRecord->appointments()
                    ->where('status', AppointmentStatus::Confirmed->value)
                    ->count()
                )
                ->badgeColor('info')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', AppointmentStatus::Confirmed->value)
                ),

            'in_progress' => Tab::make('In Progress')
                ->icon('heroicon-o-arrow-path')
                ->badge(fn () => $this->ownerRecord->appointments()
                    ->where('status', AppointmentStatus::InProgress->value)
                    ->count()
                )
                ->badgeColor('warning')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', AppointmentStatus::InProgress->value)
                ),

            'completed' => Tab::make('Completed')
                ->icon('heroicon-o-check-badge')
                ->query(fn (Builder $query) => $applyRoleScope($query)->where('status', 'completed'))
                ->badge(fn () => $this->ownerRecord->appointments()
                    ->where('status', AppointmentStatus::Completed->value)
                    ->count()
                )
                ->badgeColor('success')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', AppointmentStatus::Completed->value)
                ),

            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-o-x-circle')
                ->badge(fn () => $this->ownerRecord->appointments()
                    ->where('status', AppointmentStatus::Cancelled->value)
                    ->count()
                )
                ->badgeColor('danger')
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('status', AppointmentStatus::Cancelled->value)
                ),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FromGroup::make()
                    ->schema([
                        Section::make('Appointment Type')
                            ->schema([
                                ToggleButtons::make('type')
                                    ->inline()
                                    ->options(AppointmentType::class)
                                    ->default('treatment')
                                    ->live()
                                    ->required(),
                            ])
                            ->columns(2),

                        Section::make('Choose Client, Therapist and Date')
                            ->schema([
                                Hidden::make('clinic_id')->default($this->getOwnerRecord()->clinic_id),

                                Select::make('therapist_id')
                                    ->label('Therapist')
                                    ->options(function (callable $get) {
                                        $clinicId = $this->getOwnerRecord()->clinic_id;
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
                                        Assessment::where('user_id', $this->getOwnerRecord()->id)->pluck('id', 'id')
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
                                    ->placeholder('Select Appointment Date & Time')
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
                                    ->rules([
                                        fn (callable $get, $record) => new AppointmentAvailability(
                                            therapistId: $get('therapist_id'),
                                            clinicId: $get('clinic_id'),
                                            start: Carbon::parse($get('appointment_datetime')),
                                            end: Carbon::parse($get('appointment_datetime')),
                                            excludeId: $record?->id,
                                        ),
                                    ])
                                    ->allowHtmlValidationMessages(),

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
                            ])
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

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()->label('New Appointment')->icon('heroicon-o-plus'),
            ])
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort('appointment_datetime', 'asc')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->icon('heroicon-o-building-office')
                    ->color('info')
                    ->placeholder('Unassigned')
                    ->sortable()
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
                TextColumn::make('client.name')->label('Client')
                    // ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('therapist.name')->label('Therapist')
                    // ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('assessment.id')->searchable()->placeholder('-'),
                TextColumn::make('treatmentSession.title')->searchable()->placeholder('-'),
                TextColumn::make('appointment_datetime')
                    ->dateTime('d M Y, h:i A')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('deleted_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // 1) Status and TypeFilter
                Filter::make('status_and_type')
                    ->label('Status And Type Filters')
                    ->form([
                        Section::make('Status & Type')
                            ->icon('heroicon-o-building-office-2')
                            ->description('Filter by statu and type.')
                            ->schema([
                                Grid::make(2) // 2-column grid for better space utilization
                                    ->schema([
                                        // Type
                                        Select::make('type')
                                            ->label('Appointment Type')
                                            ->options([
                                                'consult' => 'Consultation',
                                                'treatment' => 'Treatment',
                                            ])
                                            ->placeholder('All Types'),
                                            // ->visible(!auth()->user()->hasRole('therapist')),

                                        // Status
                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'scheduled' => 'Scheduled',
                                                'confirmed' => 'Confirmed',
                                                'in_progress' => 'In Progress',
                                                'completed' => 'Completed',
                                                'cancelled' => 'Cancelled',
                                            ])
                                            ->placeholder('All Statuses'),
                                    ]),
                            ])
                            ->columns(1)
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['type'], fn (Builder $query) => $query->where('type', '=', $data['type']))
                            ->when($data['status'], fn (Builder $query) => $query->where('status', '=', $data['status']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['type'] ?? null) {
                            $indicators[] = Indicator::make('Type: ' . $data['type'])->removeField('type');
                        }

                        if ($data['status'] ?? null) {
                            $indicators[] = Indicator::make('Status: ' . $data['status'])->removeField('status');
                        }

                        return $indicators;
                    }),

                // 1) Quick Filters: Enhanced with better layout and indicators
                Filter::make('quick')
                    ->label('Quick Date Filters')
                    ->form([
                        Section::make('Select Date Ranges')
                            ->icon('heroicon-o-calendar-days')
                            ->description('Choose predefined date ranges for quick filtering.')
                            ->schema([
                                CheckboxList::make('ranges')
                                    ->label('Date Ranges')
                                    ->options([
                                        'today' => 'Today',
                                        'yesterday' => 'Yesterday',
                                        'this_week' => 'This Week',
                                        'this_month' => 'This Month',
                                        'this_year' => 'This Year',
                                    ])
                                    ->columns(5) // Increased to 3 for better horizontal spread
                                    ->bulkToggleable()
                                    ->reactive(), // Enables live updates if needed
                            ])
                            ->collapsible() // Allows collapsing to save space
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $ranges = collect($data['ranges'] ?? []);
                        if ($ranges->isEmpty()) {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($ranges) {
                            if ($ranges->contains('today')) {
                                $q->orWhereDate('appointment_datetime', Carbon::today());
                            }
                            if ($ranges->contains('yesterday')) {
                                $q->orWhereDate('appointment_datetime', Carbon::today()->subDay());
                            }
                            if ($ranges->contains('this_week')) {
                                $q->orWhereBetween('appointment_datetime', [
                                    now()->startOfWeek(),
                                    now()->endOfWeek()
                                ]);
                            }
                            if ($ranges->contains('this_month')) {
                                $q->orWhereMonth('appointment_datetime', now()->month)
                                ->whereYear('appointment_datetime', now()->year);
                            }
                            if ($ranges->contains('this_year')) {
                                $q->orWhereYear('appointment_datetime', now()->year);
                            }
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $ranges = collect($data['ranges'] ?? []);
                        return $ranges->map(fn ($key) => Indicator::make(ucfirst(str_replace('_', ' ', $key))))
                                    ->filter()
                                    ->values()
                                    ->toArray();
                    }),

                // 2) Custom Date Range: Integrated with quick filters via toggle-like behavior
                Filter::make('date_range')
                    ->label('Custom Date Range')
                    ->form([
                        Section::make('Custom Date Selection')
                            ->icon('heroicon-o-calendar')
                            ->description('Override quick filters with a specific date range.')
                            ->schema([
                                Grid::make(2) // 2-column layout for compact design
                                    ->schema([
                                        DatePicker::make('from')
                                            ->label('From Date')
                                            ->minDate(Carbon::today())
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
                            ])
                            ->columns(1)
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query) => $query->where('appointment_datetime', '>=', $data['from']))
                            ->when($data['to'], fn (Builder $query) => $query->where('appointment_datetime', '<=', $data['to']));
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

                // 3) Other Filters: Improved layout with 2-column grid, dependencies, and role-based visibility
                Filter::make('advanced')
                    ->label('Advanced Filters')
                    ->form([
                        Section::make('Clinic & Assignments')
                            ->icon('heroicon-o-building-office-2')
                            ->description('Filter by clinic and assigned users.')
                            ->schema([
                                Grid::make(3) // 2-column grid for better space utilization
                                    ->schema([
                                        // Therapist (depends on clinic)
                                        Select::make('therapist_id')
                                            ->label('Therapist')
                                            ->options(function (callable $get) {
                                                $clinicId = $this->getOwnerRecord()->clinic_id;
                                                return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                            })
                                            ->reactive()
                                            ->searchable()
                                            ->placeholder('Select Therapist')
                                            ->noSearchResultsMessage('No therapists found for selected clinic.')
                                            ->native(false)
                                            ->visible(!auth()->user()->hasRole('therapist')),

                                        // Assessment
                                        Select::make('assessment_id')
                                            ->label('Assessment')
                                            ->options(function (callable $get) {
                                                $clientId = $this->getOwnerRecord()->id;
                                                return Assessment::where('user_id', $clientId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->id]);
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select Assessment')
                                            ->native(false)
                                            ->live(),

                                        // Treatment Session
                                        Select::make('treatment_session_id')
                                            ->label('Treatment Session')
                                            ->options(fn (callable $get) =>
                                                $get('assessment_id')
                                                    ? TreatmentSession::where('assessment_id', $get('assessment_id'))
                                                        ->pluck('name', 'id')
                                                    : []
                                            )
                                            ->live()
                                            ->placeholder('Select Treatment Session'),
                                    ]),
                            ])
                            ->columns(1)
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['therapist_id'] ?? null, fn ($q, $id) => $q->where('therapist_id', $id))
                            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['therapist_id'] ?? null) {
                            $user = User::find($data['therapist_id']);
                            if ($user) {
                                $indicators[] = Indicator::make('Therapist: ' . $user->name)->removeField('therapist_id');
                            }
                        }

                        if ($data['status'] ?? null) {
                            $indicators[] = Indicator::make('Status: ' . ucfirst($data['status']))->removeField('status');
                        }

                        return $indicators;
                    }),

                // Basic toggles: Trashed and Type (grouped visually in modal)
                TrashedFilter::make()->label('Include Deleted Records'),
            ],layout: FiltersLayout::Modal)
            ->filtersFormColumns(2) // Reduced to 2 for better readability in modal; adjust as needed
            ->filtersFormWidth('md:max-w-4xl')
            ->filtersTriggerAction(
                fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            )
            
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->groups([
                Group::make('therapist_id')
                    ->label('Therapist')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->therapist_id ?? 'no_therapist')
                    ->getTitleFromRecordUsing(fn ($record) => $record->therapist?->first_name ?? 'Unassigned'),
                Group::make('status')->label('Status')->collapsible(),
                Group::make('appointment_datetime')->label('Appointment Date')->date(),
                Group::make('created_at')->date(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first appointment, it will appear here.');
    }
}
