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
use Filament\Tables\Columns\IconColumn;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

use App\Models\Clinic;
use App\Models\User;
use App\Models\Assessment;
use App\Models\TreatmentSession;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->recordUrl(null)
            ->defaultSort(function (Builder $query): Builder {
                return $query
                    ->orderByRaw("CASE WHEN status = 'confirmed' THEN 0 ELSE 1 END")
                    ->orderBy('start_datetime', 'desc');
            })
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray'),
                TextColumn::make('clinic.name')
                    ->label('Clinic')
                    ->badge()
                    ->visible(fn () => check_role(config('project.roles.super_admin')))
                    ->icon('heroicon-o-building-office')
                    // ->color('gray')
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
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
                TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->badge()
                    ->icon('heroicon-o-user')
                    // ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->badge()
                    ->icon('heroicon-o-user')
                    // ->sortable(query: fn ($query, $direction) => $query->orderBy('first_name', $direction))
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('assessment.id')
                    ->label('Assessment ID')
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('treatmentSession.title')
                    ->wrap()
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('start_datetime')
                    ->dateTime('d M Y, h:i A')
                    ->badge()
                    ->icon('heroicon-o-clock')
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
                    ->sortable(),
                TextColumn::make('end_datetime')
                    ->dateTime('d M Y, h:i A')
                    ->badge()
                    ->icon('heroicon-o-clock')
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->badge()
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray')
                    ->formatStateUsing(fn ($state) => $state . ' minutes')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_emergency')
                    ->label('Emergency Override')
                    ->icon(function (bool $state) {
                        return $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle';
                    })
                    ->badge()
                    ->color(fn (bool $state) => $state ? 'danger' : 'success')
                    ->formatStateUsing(fn (bool $state) => $state ? 'Yes' : 'No'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($record) => $record->status?->getColor() ?? 'gray'),
                TextColumn::make('deleted_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                                                'express' => 'Express',
                                                'other' => 'Other',
                                            ])
                                            ->placeholder('All Types'),

                                        // Status
                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'pending' => 'Pending',
                                                'confirmed' => 'Confirmed',
                                                'in_progress' => 'In Progress',
                                                'completed' => 'Completed',
                                                'cancelled' => 'Cancelled',
                                                'no_show' => 'No Show',
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
                                $q->orWhereDate('start_datetime', Carbon::today());
                            }
                            if ($ranges->contains('yesterday')) {
                                $q->orWhereDate('start_datetime', Carbon::today()->subDay());
                            }
                            if ($ranges->contains('this_week')) {
                                $q->orWhereBetween('start_datetime', [
                                    now()->startOfWeek(),
                                    now()->endOfWeek()
                                ]);
                            }
                            if ($ranges->contains('this_month')) {
                                $q->orWhereMonth('start_datetime', now()->month)->whereYear('start_datetime', now()->year);
                            }
                            if ($ranges->contains('this_year')) {
                                $q->orWhereYear('start_datetime', now()->year);
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
                            ])
                            ->columns(1)
                            ->collapsible(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query) => $query->whereDate('start_datetime', '>=', $data['from']))
                            ->when($data['to'], fn (Builder $query) => $query->whereDate('end_datetime', '<=', $data['to']));
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
                                Grid::make(3)
                                    ->schema([
                                        // Clinic
                                        Select::make('clinic_id')
                                            ->label('Clinic')
                                            ->relationship('clinic', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->placeholder('Select clinic')
                                            ->native(true)
                                            ->live()
                                            ->afterStateUpdated(fn (callable $set) => $set('user_id', null))
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
                                                $userId = $get('user_id');
                                                if (!$userId)
                                                    $clinicId = auth()->user()->clinic_id;

                                                return Assessment::where('user_id', $userId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->id]);
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
                                                        ->pluck('title', 'id')
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
                            ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                            ->when($data['therapist_id'] ?? null, fn ($q, $id) => $q->where('therapist_id', $id))
                            ->when($data['assessment_id'] ?? null, fn ($q, $id) => $q->where('assessment_id', $id))
                            ->when($data['treatment_session_id'] ?? null, fn ($q, $id) => $q->where('treatment_session_id', $id));
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

                        if ($data['assessment_id'] ?? null) {
                            $assessment = Assessment::find($data['assessment_id']);
                            if ($assessment) {
                                $indicators[] = Indicator::make('Assessment ID: ' . $assessment->id)->removeField('assessment_id');
                            }
                        }

                        if ($data['treatment_session_id'] ?? null) {
                            $treatment_session = TreatmentSession::find($data['treatment_session_id']);
                            if ($treatment_session) {
                                $indicators[] = Indicator::make('Treatment Session: ' . $treatment_session->title)->removeField('treatment_session_id');
                            }
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
                Action::make('new_iv_assessment')
                    ->label('Create IV Assessment')
                    ->visible(fn ($record) => can_create_assessment($record))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->button()
                    ->action(function ($record) {
                        $assessmentUrl = new_assessment($record->client, 'iv', $record);
                        return redirect($assessmentUrl);
                    })
                    ->requiresConfirmation(),
                Action::make('new_assessment')
                    ->label('Create Assessment')
                    ->visible(fn ($record) => can_create_assessment($record))
                    ->icon('heroicon-o-plus')
                    ->color('info')
                    ->button()
                    ->action(function ($record) {
                        $assessmentUrl = new_assessment($record->client, 'assessment', $record);
                        return redirect($assessmentUrl);
                    })
                    ->requiresConfirmation(),
                Action::make('start_session')
                    ->label('Start Session')
                    ->visible(fn ($record) => can_start_session($record))
                    ->icon('heroicon-o-plus')
                    ->color('warning')
                    ->button()
                    ->action(function ($record) {
                        $startSessionUrl = start_session($record);
                        return redirect($startSessionUrl);
                    })
                    ->requiresConfirmation(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Appointment Deleted 🎉')
                            ->body("Appointment has been removed successfully.")
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
                Group::make('created_at')->date(),
            ])
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         DeleteBulkAction::make(),
            //         ForceDeleteBulkAction::make(),
            //         RestoreBulkAction::make(),
            //     ]),
            // ])
            ->emptyStateDescription('Once you create your first appointment, it will appear here.');
    }
}
