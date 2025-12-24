<?php

namespace App\Filament\Widgets;

use Filament\Actions\BulkActionGroup;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Notifications\Notification;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\DeleteAction;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

// use App\Filament\Resources\Appointments\AppointmentResource;

use Carbon\Carbon;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Assessment;
use App\Models\TreatmentSession;

class TodayAppointments extends TableWidget
{
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '5s';

    // protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            // ->query(AppointmentResource::getEloquentQuery()->whereDate('start_datetime', Carbon::today()))
            // ->query(AppointmentResource::getEloquentQuery()->whereDate('start_datetime', '<=', Carbon::today())->whereDate('end_datetime', '>=', Carbon::today()))
            ->query(fn (): Builder =>
                Appointment::query()
                    ->when(!check_role('super_admin'), fn($q) => $q->where('clinic_id', auth()->user()->clinic_id))
                    ->whereDate('start_datetime', '<=', Carbon::today())
                    ->whereDate('end_datetime', '>=', Carbon::today())
                    ->orderBy('start_datetime', 'asc')
            )
            ->defaultPaginationPageOption(5)
            ->defaultSort('start_datetime', 'desc')
            ->columns([
                TextColumn::make('type')->badge()->sortable(),
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
                TextColumn::make('assessment.id')->label('Assessment ID')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('treatmentSession.title')->wrap()->searchable()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('start_datetime')
                    ->dateTime('d M Y, h:i A')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state . ' minutes')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')->badge(),
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
            // ->filters([
            //     // 1) Status and TypeFilter
            //     Filter::make('status_and_type')
            //         ->label('Status And Type Filters')
            //         ->form([
            //             Section::make('Status & Type')
            //                 ->icon('heroicon-o-building-office-2')
            //                 ->description('Filter by statu and type.')
            //                 ->schema([
            //                     Grid::make(2) // 2-column grid for better space utilization
            //                         ->schema([
            //                             // Type
            //                             Select::make('type')
            //                                 ->label('Appointment Type')
            //                                 ->options([
            //                                     'consult' => 'Consultation',
            //                                     'treatment' => 'Treatment',
            //                                 ])
            //                                 ->placeholder('All Types'),
            //                                 // ->visible(!auth()->user()->hasRole('therapist')),

            //                             // Status
            //                             Select::make('status')
            //                                 ->label('Status')
            //                                 ->options([
            //                                     'scheduled' => 'Scheduled',
            //                                     'confirmed' => 'Confirmed',
            //                                     'in_progress' => 'In Progress',
            //                                     'completed' => 'Completed',
            //                                     'cancelled' => 'Cancelled',
            //                                 ])
            //                                 ->placeholder('All Statuses'),


            //                         ]),
            //                 ])
            //                 ->columns(1)
            //                 ->collapsible(),
            //         ])
            //         ->query(function (Builder $query, array $data): Builder {
            //             return $query
            //                 ->when($data['type'], fn (Builder $query) => $query->where('type', '=', $data['type']))
            //                 ->when($data['status'], fn (Builder $query) => $query->where('status', '=', $data['status']));
            //         })
            //         ->indicateUsing(function (array $data): array {
            //             $indicators = [];

            //             if ($data['type'] ?? null) {
            //                 $indicators[] = Indicator::make('Type: ' . $data['type'])->removeField('type');
            //             }

            //             if ($data['status'] ?? null) {
            //                 $indicators[] = Indicator::make('Status: ' . $data['status'])->removeField('status');
            //             }

            //             return $indicators;
            //         }),

            //     // 3) Other Filters: Improved layout with 2-column grid, dependencies, and role-based visibility
            //     Filter::make('advanced')
            //         ->label('Advanced Filters')
            //         ->form([
            //             Section::make('Clinic & Assignments')
            //                 ->icon('heroicon-o-building-office-2')
            //                 ->description('Filter by clinic and assigned users.')
            //                 ->schema([
            //                     Grid::make(3) // 2-column grid for better space utilization
            //                         ->schema([
            //                             // Clinic
            //                             Select::make('clinic_id')
            //                                 ->label('Clinic')
            //                                 ->relationship('clinic', 'name')
            //                                 ->searchable()
            //                                 ->preload()
            //                                 ->placeholder('Select clinic')
            //                                 ->native(false)
            //                                 ->live() // Triggers reactive updates on dependents
            //                                 ->visible(fn () => auth()->user()->hasRole('super_admin')),

            //                             // Client
            //                             Select::make('user_id')
            //                                 ->label('Client')
            //                                 ->options(function (callable $get) {
            //                                     $clinicId = $get('clinic_id');
            //                                     if (!$clinicId)
            //                                         $clinicId = auth()->user()->clinic_id;

            //                                     return User::active()->role('client')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
            //                                 })
            //                                 ->reactive()
            //                                 ->searchable()
            //                                 ->placeholder('Select Client'),

            //                             // Therapist (depends on clinic)
            //                             Select::make('therapist_id')
            //                                 ->label('Therapist')
            //                                 ->options(function (callable $get) {
            //                                     $clinicId = $get('clinic_id');
            //                                     if (!$clinicId)
            //                                         $clinicId = auth()->user()->clinic_id;

            //                                     return User::active()->role('therapist')->where('clinic_id', $clinicId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->name]);
            //                                 })
            //                                 ->reactive()
            //                                 ->searchable()
            //                                 ->placeholder('Select Therapist')
            //                                 ->noSearchResultsMessage('No therapists found for selected clinic.')
            //                                 ->native(false)
            //                                 ->visible(!auth()->user()->hasRole('therapist')),

            //                             // Assessment
            //                             Select::make('assessment_id')
            //                                 ->label('Assessment')
            //                                 ->options(function (callable $get) {
            //                                     $userId = $get('user_id');
            //                                     if (!$userId)
            //                                         $clinicId = auth()->user()->clinic_id;

            //                                     return Assessment::where('user_id', $userId)->get()->mapWithKeys(fn ($u) => [$u->id => $u->id]);
            //                                 })
            //                                 ->searchable()
            //                                 ->preload()
            //                                 ->placeholder('Select Assessment')
            //                                 ->native(false)
            //                                 ->live(),

            //                             // Treatment Session
            //                             Select::make('treatment_session_id')
            //                                 ->label('Treatment Session')
            //                                 ->options(fn (callable $get) =>
            //                                     $get('assessment_id')
            //                                         ? TreatmentSession::where('assessment_id', $get('assessment_id'))
            //                                             ->pluck('title', 'id')
            //                                         : []
            //                                 )
            //                                 ->live()
            //                                 ->placeholder('Select Treatment Session'),
            //                         ]),
            //                 ])
            //                 ->columns(1)
            //                 ->collapsible(),
            //         ])
            //         ->query(function (Builder $query, array $data): Builder {
            //             return $query
            //                 ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
            //                 ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            //                 ->when($data['therapist_id'] ?? null, fn ($q, $id) => $q->where('therapist_id', $id))
            //                 ->when($data['assessment_id'] ?? null, fn ($q, $id) => $q->where('assessment_id', $id))
            //                 ->when($data['treatment_session_id'] ?? null, fn ($q, $id) => $q->where('treatment_session_id', $id));
            //         })
            //         ->indicateUsing(function (array $data): array {
            //             $indicators = [];

            //             if ($data['clinic_id'] ?? null) {
            //                 $clinic = Clinic::find($data['clinic_id']);
            //                 if ($clinic) {
            //                     $indicators[] = Indicator::make('Clinic: ' . $clinic->name)->removeField('clinic_id');
            //                 }
            //             }

            //             if ($data['user_id'] ?? null) {
            //                 $user = User::find($data['user_id']);
            //                 if ($user) {
            //                     $indicators[] = Indicator::make('Client: ' . $user->name)->removeField('user_id');
            //                 }
            //             }

            //             if ($data['therapist_id'] ?? null) {
            //                 $user = User::find($data['therapist_id']);
            //                 if ($user) {
            //                     $indicators[] = Indicator::make('Therapist: ' . $user->name)->removeField('therapist_id');
            //                 }
            //             }

            //             if ($data['assessment_id'] ?? null) {
            //                 $assessment = Assessment::find($data['assessment_id']);
            //                 if ($assessment) {
            //                     $indicators[] = Indicator::make('Assessment ID: ' . $assessment->id)->removeField('assessment_id');
            //                 }
            //             }

            //             if ($data['treatment_session_id'] ?? null) {
            //                 $treatment_session = TreatmentSession::find($data['treatment_session_id']);
            //                 if ($treatment_session) {
            //                     $indicators[] = Indicator::make('Treatment Session: ' . $treatment_session->title)->removeField('treatment_session_id');
            //                 }
            //             }

            //             return $indicators;
            //         }),

            // ],layout: FiltersLayout::Modal)
            // ->filtersFormColumns(2) // Reduced to 2 for better readability in modal; adjust as needed
            // ->filtersFormWidth('md:max-w-4xl')
            // ->filtersTriggerAction(
            //     fn (Action $action) => $action->button()->color('primary')->label('Filters')->icon('heroicon-o-funnel')
            // )
            ->headerActions([
                //
            ])
            ->recordActions([
                // Action::make('new_assessment')
                //     ->label('Create Assessment')
                //     ->visible(fn ($record) => can_create_assessment($record))
                //     ->icon('heroicon-o-plus')
                //     ->color('info')
                //     ->button()
                //     ->action(function ($record) {
                //         $assessmentUrl = new_assessment($record->client, $record);
                //         return redirect($assessmentUrl);
                //     })
                //     ->requiresConfirmation(),
                // Action::make('start_session')
                //     ->label('Start Session')
                //     ->visible(fn ($record) => can_start_session($record))
                //     ->icon('heroicon-o-plus')
                //     ->color('warning')
                //     ->button()
                //     ->action(function ($record) {
                //         $startSessionUrl = start_session($record);
                //         return redirect($startSessionUrl);
                //     })
                //     ->requiresConfirmation(),
                // ViewAction::make(),
                // EditAction::make(),
                DeleteAction::make()
                    ->successNotification(function ($record) {
                        return Notification::make()
                            ->title('Appointment Deleted 🎉')
                            ->body("The User **{$record->user->name}** appointment has been removed successfully.")
                            ->success();
                    }),
                RestoreAction::make()
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ])
            ->emptyStateDescription('Once you create your first appointment, it will appear here.');
    }
}
