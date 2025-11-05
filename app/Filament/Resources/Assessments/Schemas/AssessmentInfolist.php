<?php

namespace App\Filament\Resources\Assessments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

use Filament\Infolists;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Tabs;


class AssessmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // 🧍 Patient Info
                Section::make('Patient Information')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('user.name')->label('Patient Name'),
                            TextEntry::make('clinic.name')->label('Clinic'),
                            TextEntry::make('age')->suffix(' years')->placeholder('—'),
                            TextEntry::make('createdBy.name')->label('Created By')->placeholder('System'),
                        ]),
                    ])
                    ->collapsible(),

                // 🩺 Assessment Details
                Section::make('Assessment Details')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('daily_sun_exposure_hours')
                                ->label('Daily Sun Exposure')
                                ->suffix(' hrs')
                                ->placeholder('Not specified'),

                            TextEntry::make('social_event')
                                ->badge()
                                ->color(fn ($state) => $state === 'yes' ? 'success' : 'gray'),

                            TextEntry::make('upcoming_travel')
                                ->badge()
                                ->color(fn ($state) => $state === 'yes' ? 'warning' : 'gray'),

                            TextEntry::make('is_pregnant')
                                ->label('Pregnant')
                                ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                ->color(fn ($state) => $state ? 'danger' : 'gray'),

                            TextEntry::make('breastfeeding')
                                ->badge()
                                ->color(fn ($state) => $state === 'yes' ? 'warning' : 'gray'),
                        ]),
                    ])
                    ->collapsible(),

                // 💊 Medical History
                Section::make('Medical Background')
                    ->icon('heroicon-o-heart')
                    ->schema([
                        KeyValueEntry::make('medical_history')
                            ->label('Medical History')
                            ->columnSpanFull()
                            ->placeholder('No medical history recorded'),

                        KeyValueEntry::make('allergies')
                            ->label('Allergies')
                            ->columnSpanFull()
                            ->placeholder('No allergies recorded'),
                    ])
                    ->collapsible(),

                // 🧠 Diagnosis
                Section::make('Diagnosis')
                    ->icon('heroicon-o-user')
                    ->schema([
                        RepeatableEntry::make('diagnosis')
                            ->label('Diagnosis Details')
                            ->columns(1)
                            ->schema([
                                TextEntry::make('condition')->label('Condition'),
                                TextEntry::make('notes')->label('Notes'),
                            ])
                            ->placeholder('No diagnosis data recorded')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Treatment Plans')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('selected_plan_type')
                                ->badge()
                                ->color(fn ($state) => match ($state) {
                                    'single' => 'success',
                                    'multiple' => 'warning',
                                    default => 'gray',
                                })
                                ->label('Plan Type'),

                            TextEntry::make('total_time')
                                ->label('Total Time')
                                ->placeholder('—'),
                        ]),
                        RepeatableEntry::make('treatmentPlans')
                            ->label('')
                            ->columns(1)
                            ->schema([
                                // Header: Session Title and Meta
                                Section::make(fn ($record) => 'Session ' . $record->session_number . ': ' . ucfirst($record->title))
                                    ->collapsible()
                                    ->collapsed()
                                    ->icon('heroicon-o-sparkles')
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

                                        Tabs::make('Treatment Details')->tabs([
                                            // 🧰 Therapist Preparation
                                            Tab::make('Therapist Prep')
                                                ->icon('heroicon-o-user')
                                                ->schema([
                                                    TextEntry::make('preparations_checklist_for_therapist')
                                                        ->label('Preparation Checklist')
                                                        ->state(function ($record) {
                                                            $items = $record->preparations_checklist_for_therapist;
                                                            if (is_array($items)) {
                                                                return implode(', ', $items);
                                                            }
                                                            return $items ?: '—';
                                                        })
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
                            ])
                            ->placeholder('No treatment plans found for this assessment.'),
                    ])
                ->collapsible(),

                // 📈 Parameters / Scores
                Section::make('Parameters With Abnormal Scores')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->schema([
                        KeyValueEntry::make('parameters_with_abnormal_scores')
                            ->columnSpanFull()
                            ->placeholder('No abnormal parameters'),
                    ])
                    ->collapsible(),

                // 🧾 Therapist Notes & Status
                Section::make('Summary')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => match ($state) {
                                'in_progress' => 'info',
                                'pending' => 'gray',
                                'completed' => 'success',
                                'incomplete' => 'warning',
                                'cancelled' => 'danger',
                                'overdue' => 'danger',
                                default => 'gray',
                            }),

                        TextEntry::make('therapist_notes')
                            ->label('Therapist Notes')
                            ->columnSpanFull()
                            ->placeholder('No notes added'),
                    ])
                    ->collapsible(),

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
}
