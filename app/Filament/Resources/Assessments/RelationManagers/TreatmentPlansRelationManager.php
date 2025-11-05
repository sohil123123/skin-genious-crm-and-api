<?php

namespace App\Filament\Resources\Assessments\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Tabs;
use Filament\Infolists\Components\ViewEntry;

use Filament\Resources\RelationManagers\RelationManager;

class TreatmentPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentPlans';

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // 🧍 Basic Info
                Section::make('Basic Information')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('plan_type')
                                ->label('Plan Type')
                                ->badge()
                                ->color(fn ($state) => match ($state) {
                                    'single' => 'success',
                                    'multiple' => 'warning',
                                    default => 'gray',
                                }),

                            TextEntry::make('week')
                                ->label('Week')
                                ->suffix(fn ($state) => $state ? ' week' : null)
                                ->placeholder('—'),

                            TextEntry::make('treatment_time')
                                ->label('Duration')
                                ->placeholder('—'),
                        ]),
                    ]),
                
                Section::make(fn ($record) => 'Session ' . $record->session_number . ': ' . ucfirst($record->title))
                    ->collapsible()
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Tabs::make('Treatment Details')->tabs([
                            // 🧰 Therapist Preparation
                            Tab::make('Therapist Checklist Checklist')
                                ->icon('heroicon-o-user')
                                ->schema([
                                    // KeyValueEntry::make('preparations_checklist_for_therapist')
                                    //     ->label('Preparation Checklist')
                                    //     ->columnSpanFull()
                                    //     ->placeholder('No medical history recorded'),
                                    ViewEntry::make('preparations_checklist_for_therapist')
                                        ->label('Preparation Checklist')
                                        ->view('filament.infolists.entries.prep-checklist')
                                        ->columnSpanFull(),
                                ]),
                            
                            // 💆 Concerns Addressed
                            Tab::make('Concerns')
                                ->icon('heroicon-o-heart')
                                ->schema([
                                    RepeatableEntry::make('concerns_addressed')
                                        ->label('Concerns Addressed')
                                        ->columns(3)
                                        ->schema([
                                            TextEntry::make('concern')
                                                ->label('Concern')
                                                ->color('primary')
                                                ->weight('bold'),

                                            TextEntry::make('current_value')
                                                ->label('Current Value')
                                                ->icon('heroicon-o-arrow-trending-down')
                                                ->color('danger')
                                                ->placeholder('—'),

                                            TextEntry::make('target_value')
                                                ->label('Target Value')
                                                ->icon('heroicon-o-arrow-trending-up')
                                                ->color('success')
                                                ->placeholder('—'),
                                        ])
                                        ->placeholder('No concerns listed.')
                                        ->columnSpanFull(),
                                ]),

                            // ⚙️ Treatment Steps
                            Tab::make('Steps')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->schema([
                                    RepeatableEntry::make('steps')
                                        ->label('Treatment Steps')
                                        ->columns(1)
                                        ->schema([
                                            Section::make('Treatment Step')
                                                ->icon('heroicon-o-sparkles')
                                                ->schema([
                                                    TextEntry::make('step_number')
                                                        ->label('Step #')
                                                        ->badge()
                                                        ->color('primary'),
                                                    Grid::make(3)->schema([
                                                        TextEntry::make('duration')
                                                            ->label('Duration')
                                                            ->suffix(' mins'),
                                                        
                                                        TextEntry::make('ingredients_equipments')
                                                            ->label('Ingredients & Equipments')
                                                            ->columnSpan(2),
                                                    ]),

                                                    TextEntry::make('how_to_do')
                                                        ->label('How To Do')
                                                        ->columnSpanFull()
                                                        ->markdown()
                                                        ->placeholder('—'),
                                                ]),
                                        ])
                                        ->placeholder('No treatment steps listed.')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                    ]),

                // 🕒 Meta Info
                Section::make('Record Metadata')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->label('Created At')
                                ->dateTime('d M Y, h:i A'),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime('d M Y, h:i A'),
                        ]),
                    ]),
            ])->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            // ->recordTitleAttribute('treatment plans')
            ->columns([
                TextColumn::make('plan_type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'single' => 'success',
                        'multiple' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()->sortable(),
                TextColumn::make('session_number')->badge()->color('info')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('week')
                    ->label('Week')
                    ->suffix(fn ($state) => $state ? ' week' : null)
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('treatment_time')
                    ->label('Duration')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')->dateTime('d M Y, h:i A')->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateDescription('Once you create your first plan, it will appear here.');
    }
}
