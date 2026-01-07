<?php

namespace App\Filament\Resources\Assessments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Select;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Indicator;

use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

use App\Models\Clinic;
use App\Models\User;

class AssessmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('created_at', 'desc')
            ->columns([
                // TextColumn::make('parent_id')->label('Parent Assessment ID')->numeric()->placeholder('Parent')->sortable(),
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
                    ->visible(fn () => check_role('super_admin'))
                    ->toggleable(),
                TextColumn::make('user.name')->label('User Name')->searchable(['first_name', 'last_name']),
                TextColumn::make('name')->placeholder('-')->searchable()->sortable(),
                TextColumn::make('selected_plan_type')->badge()->placeholder('-'),
                TextColumn::make('total_time')->searchable()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('createdBy.name')->label('Created By')->searchable(['first_name', 'last_name']),
                TextColumn::make('created_at')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                // SelectFilter::make('parent_id')
                //     ->label('Parent Assessment')
                //     ->relationship(
                //         name: 'parentAssessment',
                //         titleAttribute: 'id',
                //         modifyQueryUsing: fn ($query) => $query->whereNull('parent_id')
                //     )
                //     ->searchable()
                //     ->preload(),

                SelectFilter::make('status')
                    ->options([
                        'in_progress' => 'in_progress',
                        'pending' => 'pending',
                        'completed' => 'completed',
                        'incomplete' => 'incomplete',
                        'cancelled' => 'cancelled',
                        'overdue' => 'overdue',
                    ]),

                Filter::make('extra')
                    ->label('Other Filters')
                    ->form([
                        Section::make('Other Filters')
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                Grid::make(1)->schema([
                                    // Clinic
                                    Select::make('clinic_id')
                                        ->label('Clinics')
                                        ->relationship('clinic', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->placeholder('Select a clinic')
                                        ->native(false),

                                    // User
                                    Select::make('user_id')
                                        ->label('Users')
                                        ->options(function (callable $get) {
                                            $clinicId = $get('clinic_id');
                                            if (!$clinicId) {
                                                return [];
                                            }
                                            return User::whereHas('roles', fn ($q) => $q->where('name', 'client'))
                                                    ->where('clinic_id', $clinicId)
                                                    ->get()
                                                    ->mapWithKeys(fn ($u) => [$u->id => $u->name]);

                                        })
                                        ->reactive()
                                        ->searchable()
                                        ->placeholder('All users'),
                                ]),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['clinic_id'] ?? null, fn ($q, $id) => $q->where('clinic_id', $id))
                            ->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id));
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
                                $indicators[] = Indicator::make('Therapist: ' . $user->name)->removeField('user_id');
                            }
                        }
                        return $indicators;
                    })
            ],
            layout: FiltersLayout::Modal)
            ->filtersFormColumns(3)
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->color('primary')
                    ->label('Filters')
                    ->icon('heroicon-o-funnel')
            )
            ->recordActions([
                // ViewAction::make(),
                Action::make('treatment-sessions')
                    ->label('Treatment Sessions')
                    // ->visible(fn ($record) => $record->hasRole('therapist'))
                    ->icon('heroicon-s-clipboard-document-list')
                    // ->iconButton()
                    ->color('info')
                    ->tooltip('Manage Treatment Sessions')
                    ->url(fn ($record) => route('filament.admin.resources.assessments.treatment-plans', ['record' => $record])),
            ])
            ->groups([
                // Group::make('parent_id')
                //     ->label('Assessment')
                //     ->collapsible()
                //     ->getKeyFromRecordUsing(fn ($record) => $record->parent_id ?? 'no_assessment')
                //     ->getTitleFromRecordUsing(fn ($record) => $record->parentAssessment?->id ?? 'Parent'),
                Group::make('user.first_name')
                    ->label('User')
                    ->collapsible(),
                Group::make('clinic_id')
                    ->label('Clinic')
                    ->collapsible()
                    ->getKeyFromRecordUsing(fn ($record) => $record->clinic_id ?? 'no_clinic')
                    ->getTitleFromRecordUsing(fn ($record) => $record->clinic?->name ?? 'Unassigned'),
                Group::make('status')->label('Status')->collapsible(),
                Group::make('created_at')->date(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first assessment, it will appear here.');;
    }
}
