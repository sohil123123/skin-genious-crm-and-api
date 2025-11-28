<?php

namespace App\Filament\Resources\Appointments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Indicator;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;
// use App\Enums\AppointmentStatus;
// use App\Enums\AppointmentType;

use App\Models\Clinic;
use App\Models\TreatmentSession;
use App\Models\User;
use App\Models\Assessment;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort('appointment_datetime', 'asc')
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->icon('heroicon-o-building-office')
                    ->color('gray')
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
                // TextColumn::make('assessment.id')->searchable()->placeholder('-'),
                TextColumn::make('treatmentSession.title')->wrap()->searchable()->placeholder('-'),
                TextColumn::make('appointment_datetime')
                    ->dateTime('d M Y, h:i A')
                    ->badge()
                    ->color('warning')
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
                                        // Clinic
                                        Select::make('clinic_id')
                                            ->label('Clinic')
                                            ->relationship('clinic', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select clinic')
                                            ->native(false)
                                            ->live() // Triggers reactive updates on dependents
                                            ->visible(fn () => auth()->user()->hasRole('super_admin')),

                                        // Client
                                        Select::make('user_id')
                                            ->label('Client')
                                            ->options(function (callable $get) {
                                                $clinicId = $get('clinic_id');
                                                if (!$clinicId)
                                                    $clinicId = auth()->user()->clinic_id;
                                                
                                                return User::active()->role('client')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
                                            })
                                            ->reactive()
                                            ->searchable()
                                            ->placeholder('Select Client'),

                                        // Therapist (depends on clinic)
                                        Select::make('therapist_id')
                                            ->label('Therapist')
                                            ->options(function (callable $get) {
                                                $clinicId = $get('clinic_id');
                                                if (!$clinicId)
                                                    $clinicId = auth()->user()->clinic_id;
                                                
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
                                                $clientId = $get('client_id');
                                                if (!$clientId)
                                                    $clinicId = auth()->user()->clinic_id;
                                                
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
                            ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
                            ->when($data['client_id'] ?? null, fn ($q, $id) => $q->where('client_id', $id))
                            ->when($data['therapist_id'] ?? null, fn ($q, $id) => $q->where('therapist_id', $id))
                            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['clinic_id'] ?? null) {
                            $clinic = Clinic::find($data['clinic_id']);
                            if ($clinic) {
                                $indicators[] = Indicator::make('Clinic: ' . $clinic->name)->removeField('clinic_id');
                            }
                        }

                        if ($data['user_id'] ?? null) {
                            $user = User::find($data['user_id']);
                            if ($user) {
                                $indicators[] = Indicator::make('Client: ' . $user->name)->removeField('user_id');
                            }
                        }

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
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Appointment Deleted 🎉')
                            ->body("The User **{$record->user->name}** appointment has been removed successfully.")
                            ->success();
                    }),
                RestoreAction::make()
            ])
            ->groups([
                Group::make('clinic_id')
                    ->label('Clinic')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->clinic_id ?? 'no_clinic')
                    ->getTitleFromRecordUsing(fn ($record) => $record->clinic?->name ?? 'Unassigned'),
                Group::make('client_id')
                    ->label('Client')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->client_id ?? 'no_client')
                    ->getTitleFromRecordUsing(fn ($record) => $record->client?->first_name ?? 'Unassigned'),
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
