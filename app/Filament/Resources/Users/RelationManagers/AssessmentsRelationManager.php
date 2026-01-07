<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\Action;

use Filament\Infolists\Infolist;
use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;
use App\Filament\Resources\Assessments\Schemas\AssessmentInfolist;

class AssessmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assessments';

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('assessment')
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
                TextColumn::make('name')->placeholder('-')->searchable()->sortable(),
                TextColumn::make('selected_plan_type')->badge()->placeholder('-'),
                TextColumn::make('total_time')->searchable()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('createdBy.name')->label('Created By')->searchable(['first_name', 'last_name']),
                TextColumn::make('created_at')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'in_progress' => 'in_progress',
                        'pending' => 'pending',
                        'completed' => 'completed',
                        'incomplete' => 'incomplete',
                        'cancelled' => 'cancelled',
                        'overdue' => 'overdue',
                    ]),
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
            ->recordActions([
                // ViewAction::make(),
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading('Assessment Details')
                    ->infolist(
                        fn (Schema $schema, $record): Schema => AssessmentInfolist::configure($schema->record($record))
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                    
                Action::make('treatment-sessions')
                    ->label('Treatment Sessions')
                    // ->visible(fn ($record) => $record->hasRole('therapist'))
                    ->icon('heroicon-s-clipboard-document-list')
                    // ->iconButton()
                    ->color('info')
                    ->tooltip('Manage Treatment Sessions')
                    ->url(fn ($record) => route('filament.admin.resources.assessments.treatment-plans', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateDescription('Once you create your first assessment, it will appear here.');
    }
}
