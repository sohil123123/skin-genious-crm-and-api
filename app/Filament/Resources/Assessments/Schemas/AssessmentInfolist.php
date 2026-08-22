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
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Group;

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
                            
                            TextEntry::make('status')->badge(),

                            TextEntry::make('therapist_notes')
                                ->label('Therapist Notes')
                                ->columnSpanFull()
                                ->placeholder('No notes added'),
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

                //  Diagnosis
                Section::make('Skin Analysis Report')
                    ->icon('heroicon-o-user')
                    ->schema(function ($record) {
                        $data = $record->diagnosis ?? [];
                        $isIv = !in_array($record->assessment_type ?? 'normal', ['normal', 'instant-normal']);

                        if ($isIv) {
                            $buildIvSchema = function ($arrayData, $prefix = '', $depth = 0, $isScoreSection = false) use (&$buildIvSchema) {
                                $schema = [];
                                if (!is_array($arrayData)) return $schema;

                                foreach ($arrayData as $key => $value) {
                                    $name = $prefix . $key;
                                    $label = ucfirst(str_replace('_', ' ', (string)$key));
                                    $keyLower = strtolower($key);
                                    $isCurrentScoreContainer = str_contains($keyLower, 'score') || str_contains($keyLower, '0_100');
                                    $currentlyInScores = $isScoreSection || $isCurrentScoreContainer;

                                    if (is_array($value)) {
                                        if (\Illuminate\Support\Arr::isAssoc($value) || empty($value)) {
                                            $innerSchema = $buildIvSchema($value, $name . '_', $depth + 1, $currentlyInScores);
                                            
                                            $section = \Filament\Schemas\Components\Section::make($label)
                                                ->schema($innerSchema)
                                                ->columns(3)
                                                ->compact();

                                            if ($isCurrentScoreContainer) {
                                                $section->icon('heroicon-o-chart-bar')
                                                    ->extraAttributes([
                                                        'class' => 'bg-sky-50/50 dark:bg-sky-900/20 ring-1 ring-sky-500/30 rounded-xl'
                                                    ]);
                                                if (str_contains($keyLower, '0_100')) {
                                                    $section->description('Score analysis and breakdown (0-100 scale)');
                                                }
                                            } else {
                                                $section->extraAttributes([
                                                    'class' => 'shadow-none ring-1 ring-gray-200 dark:ring-gray-800 bg-transparent'
                                                ]);
                                            }

                                            $schema[] = $section->columnSpanFull();
                                        } else {
                                            $schema[] = \Filament\Infolists\Components\TextEntry::make($name)
                                                ->label($label)
                                                ->getStateUsing(fn() => json_encode($value))
                                                ->columnSpanFull();
                                        }
                                    } else {
                                        if (($currentlyInScores || str_contains($keyLower, 'score')) && is_numeric($value)) {
                                            $schema[] = \Filament\Infolists\Components\TextEntry::make($name)
                                                ->label(strtoupper(str_replace('_', ' ', $key)))
                                                ->html()
                                                ->getStateUsing(function() use ($value) {
                                                    $val = max(0, min(100, (float) $value));
                                                    $colorHex = $val < 40 ? '#ef4444' : ($val < 70 ? '#f59e0b' : '#22c55e');
                                                    return new \Illuminate\Support\HtmlString("
                                                        <div class=\"flex flex-col gap-1 w-full max-w-[200px] mt-1\">
                                                            <div class=\"flex items-center justify-between mb-1\">
                                                                <span class=\"text-sm font-bold\" style=\"color: {$colorHex}\">" . round($val, 1) . " / 100</span>
                                                            </div>
                                                            <div class=\"w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700 overflow-hidden\">
                                                                <div class=\"h-2 rounded-full\" style=\"width: {$val}%; background-color: {$colorHex}\"></div>
                                                            </div>
                                                        </div>
                                                    ");
                                                });
                                        } else {
                                            $schema[] = \Filament\Infolists\Components\TextEntry::make($name)
                                                ->label($label)
                                                ->getStateUsing(fn() => is_bool($value) ? ($value ? 'Yes' : 'No') : ((string) $value ?: '-'));
                                        }
                                    }
                                }
                                return $schema;
                            };

                            $ivDiagRaw = is_string($record->diagnosis) ? json_decode($record->diagnosis, true) : ($record->diagnosis ?? []);
                            $ivDiag = is_array($ivDiagRaw) ? $ivDiagRaw : [];

                            $tabs = [];
                            $generalSchema = [];
                            
                            foreach ($ivDiag as $topKey => $topValue) {
                                $topLabel = ucfirst(str_replace('_', ' ', (string)$topKey));
                                $topKeyLower = strtolower($topKey);
                                $isCurrentScoreContainer = str_contains($topKeyLower, 'score') || str_contains($topKeyLower, '0_100');
                                
                                if (is_array($topValue)) {
                                    $tabSchema = $buildIvSchema($topValue, 'iv_diag_' . $topKey . '_', 1, $isCurrentScoreContainer);
                                    
                                    $tab = \Filament\Schemas\Components\Tabs\Tab::make($topLabel)
                                        ->schema([
                                            \Filament\Schemas\Components\Grid::make(3)->schema($tabSchema)
                                        ]);
                                        
                                    if ($isCurrentScoreContainer) {
                                        $tab->icon('heroicon-o-chart-bar');
                                    }
                                    $tabs[] = $tab;
                                } else {
                                    $generalSchema[] = \Filament\Infolists\Components\TextEntry::make('iv_diag_' . $topKey)
                                        ->label($topLabel)
                                        ->getStateUsing(fn() => is_bool($topValue) ? ($topValue ? 'Yes' : 'No') : ((string) $topValue ?: '-'));
                                }
                            }
                            
                            if (!empty($generalSchema)) {
                                array_unshift($tabs, \Filament\Schemas\Components\Tabs\Tab::make('General')
                                    ->schema([
                                        \Filament\Schemas\Components\Grid::make(3)->schema($generalSchema)
                                    ])
                                );
                            }

                            return [
                                \Filament\Schemas\Components\Tabs::make('IV Diagnosis Tabs')
                                    ->tabs($tabs)
                                    ->columnSpanFull()
                                    ->contained(false)
                            ];
                        }

                        $diagnosisReport = $data['diagnosis_report'] ?? [];
                        $abnormalScores = $data['treatable_concerns_summary']['parameters_with_abnormal_scores'] ?? [];

                        $diagnosisSections = collect($diagnosisReport)->map(function ($item, $key) {
                            $safeKey = is_string($key) ? $key : 'item_' . $key;
                            return Section::make($item['parameter_name'] ?? ucfirst(str_replace('_', ' ', $safeKey)))
                                ->schema([
                                    TextEntry::make('score_' . $safeKey)
                                        ->label('Score / Label')
                                        ->getStateUsing(fn () => $item['score_or_label'] ?? '-'),

                                    TextEntry::make('description_' . $safeKey)
                                        ->label('Description')
                                        ->getStateUsing(fn () => $item['description'] ?? '-'),

                                    TextEntry::make('possible_causes_' . $safeKey)
                                        ->label('Possible Causes')
                                        ->getStateUsing(fn () => implode(', ', $item['possible_causes'] ?? [])),

                                    TextEntry::make('score_explanation_' . $safeKey)
                                        ->label('Score Explanation')
                                        ->getStateUsing(fn () => $item['score_explanation'] ?? '-'),

                                    TextEntry::make('affected_area_image_' . $safeKey)
                                        ->label('Image Area ID')
                                        ->getStateUsing(fn () => $item['affected_area_image'] ?? '-'),
                                ])
                                ->collapsed()
                                ->collapsible()
                                ->columns(2);
                        });

                        $abnormalSection = Section::make('Treatable Concerns (Abnormal Scores)')
                            ->schema(
                                collect($abnormalScores)->map(function ($item, $key) {
                                    $safeKey = is_string($key) ? $key : 'abnormal_' . $key;
                                    return Group::make([
                                        TextEntry::make('parameter_' . $safeKey)
                                            ->label('Parameter')
                                            ->getStateUsing(fn () => $item['parameter'] ?? '-'),

                                        TextEntry::make('current_score_' . $safeKey)
                                            ->label('Current Score')
                                            ->getStateUsing(fn () => $item['current_score'] ?? '-'),
                                    ])->columns(3);
                                })->toArray()
                            )
                            ->collapsed()
                            ->collapsible();

                        return [
                            Section::make('Diagnosis Report')->schema($diagnosisSections->toArray())->collapsible(),
                            $abnormalSection,
                        ];
                    })
                    ->collapsed()
                    ->collapsible(),

                // Section::make('Recommended Full Plans')
                //     ->icon('heroicon-o-clipboard-document-list')
                //     ->schema([
                //         Grid::make(2)->schema([
                //             TextEntry::make('selected_plan_type')
                //                 ->badge()
                //                 ->color(fn ($state) => match ($state) {
                //                     'single' => 'success',
                //                     'multiple' => 'warning',
                //                     default => 'gray',
                //                 })
                //                 ->label('Plan Type'),

                //             TextEntry::make('recommended_total_time')
                //                 ->label('Total Time')
                //                 ->placeholder('—'),
                //         ]),
                //         RepeatableEntry::make('recommended_treatments_list')
                //             ->label('')
                //             ->columns(1)
                //             ->schema([
                //                 // Header: Session Title and Meta
                //                 Section::make('Session Details')
                //                     ->collapsible()
                //                     ->collapsed()
                //                     ->icon('heroicon-o-sparkles')
                //                     ->schema([
                //                         Grid::make(4)->schema([
                //                             TextEntry::make('session_number')
                //                                 ->label('Session Number')
                //                                 ->placeholder('—'),

                //                             TextEntry::make('title')
                //                                 ->label('Title')
                //                                 ->placeholder('—'),
                                            
                //                             TextEntry::make('week')
                //                                 ->label('Week')
                //                                 ->suffix(fn ($state) => $state ? ' week' : null)
                //                                 ->placeholder('—'),

                //                             TextEntry::make('treatment_time')
                //                                 ->label('Duration')
                //                                 ->placeholder('—'),
                //                         ]),

                //                         Tabs::make('Treatment Details')->tabs([
                //                             // 💆 Concerns Addressed
                //                             Tab::make('Concerns')
                //                                 ->icon('heroicon-o-heart')
                //                                 ->schema([
                //                                     RepeatableEntry::make('concerns_addressed')
                //                                         ->label('Concerns Addressed')
                //                                         ->columns(3)
                //                                         ->schema([
                //                                             TextEntry::make('concern')
                //                                                 ->label('Concern')
                //                                                 ->color('primary')
                //                                                 ->weight('bold'),

                //                                             TextEntry::make('current_value')
                //                                                 ->label('Current Value')
                //                                                 ->icon('heroicon-o-arrow-trending-down')
                //                                                 ->color('danger')
                //                                                 ->placeholder('—'),

                //                                             TextEntry::make('target_value')
                //                                                 ->label('Target Value')
                //                                                 ->icon('heroicon-o-arrow-trending-up')
                //                                                 ->color('success')
                //                                                 ->placeholder('—'),
                //                                         ])
                //                                         ->placeholder('No concerns listed.')
                //                                         ->columnSpanFull(),
                //                                 ]),

                //                             // ⚙️ Treatment Steps
                //                             Tab::make('Steps')
                //                                 ->icon('heroicon-o-clipboard-document-check')
                //                                 ->schema([
                //                                     RepeatableEntry::make('steps')
                //                                         ->label('Treatment Steps')
                //                                         ->columns(4)
                //                                         ->schema([
                //                                             // Section::make('Treatment Step')
                //                                             //     ->icon('heroicon-o-sparkles')
                //                                             //     ->schema([
                //                                                     // Grid::make(3)->schema([
                //                                                         TextEntry::make('step_number')
                //                                                             ->label('Step #')
                //                                                             ->badge()
                //                                                             ->color('primary'),

                //                                                         TextEntry::make('duration')
                //                                                             ->label('Duration')
                //                                                             ->suffix(' mins'),
                                                                        
                //                                                         TextEntry::make('ingredients_equipments')
                //                                                             ->label('Ingredients & Equipments')
                //                                                             ->columnSpan(2),
                //                                                     // ]),

                //                                                     TextEntry::make('how_to_do')
                //                                                         ->label('How To Do')
                //                                                         ->columnSpanFull()
                //                                                         ->markdown()
                //                                                         ->placeholder('—'),
                //                                                 // ]),
                //                                         ])
                //                                         ->placeholder('No treatment steps listed.')
                //                                         ->columnSpanFull(),
                //                                 ]),
                //                         ]),
                                        
                //                     ]),
                //             ])
                //             ->placeholder('No treatment plans found for this assessment.'),
                //     ])
                //     ->collapsed()
                //     ->collapsible(),

                // Section::make('Treatment Sessions')
                //     ->icon('heroicon-o-clipboard-document-list')
                //     ->schema([
                //         Grid::make(2)->schema([
                //             TextEntry::make('selected_plan_type')
                //                 ->badge()
                //                 ->color(fn ($state) => match ($state) {
                //                     'single' => 'success',
                //                     'multiple' => 'warning',
                //                     default => 'gray',
                //                 })
                //                 ->label('Plan Type'),

                //             TextEntry::make('total_time')
                //                 ->label('Total Time')
                //                 ->placeholder('—'),
                //         ]),
                //         RepeatableEntry::make('treatmentSessions')
                //             ->label('')
                //             ->columns(1)
                //             ->schema([
                //                 // Header: Session Title and Meta
                //                 Section::make(fn ($record) => 'Session ' . $record->session_number . ': ' . ucfirst($record->title))
                //                     ->collapsible()
                //                     ->collapsed()
                //                     ->icon('heroicon-o-sparkles')
                //                     ->schema([
                //                         Grid::make(3)->schema([
                //                             TextEntry::make('plan_type')
                //                                 ->label('Plan Type')
                //                                 ->badge()
                //                                 ->color(fn ($state) => match ($state) {
                //                                     'single' => 'success',
                //                                     'multiple' => 'warning',
                //                                     default => 'gray',
                //                                 }),

                //                             TextEntry::make('week')
                //                                 ->label('Week')
                //                                 ->suffix(fn ($state) => $state ? ' week' : null)
                //                                 ->placeholder('—'),

                //                             TextEntry::make('treatment_time')
                //                                 ->label('Duration')
                //                                 ->placeholder('—'),
                //                         ]),

                //                         Tabs::make('Treatment Details')->tabs([
                //                             // 🧰 Therapist Preparation
                //                             Tab::make('Therapist Checklist Checklist')
                //                                 ->icon('heroicon-o-user')
                //                                 ->schema([
                //                                     ViewEntry::make('preparations_checklist_for_therapist')
                //                                         ->label('Preparation Checklist')
                //                                         ->view('filament.infolists.entries.prep-checklist')
                //                                         ->columnSpanFull(),
                //                                     // TextEntry::make('preparations_checklist_for_therapist')
                //                                     //     ->label('Preparation Checklist')
                //                                     //     ->state(function ($record) {
                //                                     //         $items = $record->preparations_checklist_for_therapist;
                //                                     //         if (is_array($items)) {
                //                                     //             return implode(', ', $items);
                //                                     //         }
                //                                     //         return $items ?: '—';
                //                                     //     })
                //                                     //     ->columnSpanFull(),
                //                                 ]),
                                            
                //                             // 💆 Concerns Addressed
                //                             Tab::make('Concerns')
                //                                 ->icon('heroicon-o-heart')
                //                                 ->schema([
                //                                     RepeatableEntry::make('concerns_addressed')
                //                                         ->label('Concerns Addressed')
                //                                         ->columns(3)
                //                                         ->schema([
                //                                             TextEntry::make('concern')
                //                                                 ->label('Concern')
                //                                                 ->color('primary')
                //                                                 ->weight('bold'),

                //                                             TextEntry::make('current_value')
                //                                                 ->label('Current Value')
                //                                                 ->icon('heroicon-o-arrow-trending-down')
                //                                                 ->color('danger')
                //                                                 ->placeholder('—'),

                //                                             TextEntry::make('target_value')
                //                                                 ->label('Target Value')
                //                                                 ->icon('heroicon-o-arrow-trending-up')
                //                                                 ->color('success')
                //                                                 ->placeholder('—'),
                //                                         ])
                //                                         ->placeholder('No concerns listed.')
                //                                         ->columnSpanFull(),
                //                                 ]),

                //                             // ⚙️ Treatment Steps
                //                             Tab::make('Steps')
                //                                 ->icon('heroicon-o-clipboard-document-check')
                //                                 ->schema([
                //                                     RepeatableEntry::make('steps')
                //                                         ->label('Treatment Steps')
                //                                         ->columns(4)
                //                                         ->schema([
                //                                                 TextEntry::make('step_number')
                //                                                     ->label('Step #')
                //                                                     ->badge()
                //                                                     ->color('primary'),
                //                                                 TextEntry::make('duration')
                //                                                     ->label('Duration')
                //                                                     ->suffix(' mins'),
                                                                
                //                                                 TextEntry::make('ingredients_equipments')
                //                                                     ->label('Ingredients & Equipments')
                //                                                     ->columnSpan(2),

                //                                                 TextEntry::make('how_to_do')
                //                                                     ->label('How To Do')
                //                                                     ->columnSpanFull()
                //                                                     ->markdown()
                //                                                     ->placeholder('—'),
                //                                         ])
                //                                         ->placeholder('No treatment steps listed.')
                //                                         ->columnSpanFull(),
                //                                 ]),
                //                         ]),
                                        
                //                     ]),
                //             ])
                //             ->placeholder('No treatment plans found for this assessment.'),
                //     ])
                //     ->collapsed()
                //     ->collapsible(),

                // 📈 Parameters / Scores
                Section::make(fn ($record) => !in_array($record?->assessment_type ?? 'normal', ['normal', 'instant-normal']) ? 'Dermatological AI Inputs' : 'Parameters With Abnormal Scores')
                    ->icon('heroicon-o-presentation-chart-line')
                    ->schema(function ($record) {
                        $isIv = !in_array($record->assessment_type ?? 'normal', ['normal', 'instant-normal']);

                        if ($isIv) {
                            $ivDataRaw = is_string($record->parameters_with_abnormal_scores) ? json_decode($record->parameters_with_abnormal_scores, true) : ($record->parameters_with_abnormal_scores ?? []);
                            $ivData = is_array($ivDataRaw) ? $ivDataRaw : [];
                            
                            $flattenData = function ($array, $prefix = '') use (&$flattenData) {
                                $result = [];
                                foreach ($array as $key => $value) {
                                    $label = ucfirst(str_replace('_', ' ', (string)$key));
                                    $newPrefix = $prefix ? $prefix . ' > ' . $label : $label;
                                    
                                    if (is_array($value)) {
                                        if (empty($value)) {
                                            $result[$newPrefix] = 'None';
                                        } else {
                                            $result = array_merge($result, $flattenData($value, $newPrefix));
                                        }
                                    } else {
                                        $result[$newPrefix] = is_bool($value) ? ($value ? 'Yes' : 'No') : ((string) $value ?: '-');
                                    }
                                }
                                return $result;
                            };

                            return [
                                \Filament\Infolists\Components\KeyValueEntry::make('iv_dermatological_data')
                                    ->label('')
                                    ->getStateUsing(fn() => $flattenData($ivData))
                                    ->keyLabel('Parameter')
                                    ->valueLabel('Value')
                                    ->columnSpanFull()
                            ];
                        }

                        // Decode JSON
                        $data = is_string($record->parameters_with_abnormal_scores) ? json_decode($record->parameters_with_abnormal_scores, true) : ($record->parameters_with_abnormal_scores ?? []);
                        $abnormal = $data['parameters_with_abnormal_scores'] ?? [];

                        // Build UI
                        return collect($abnormal)->map(function ($item, $key) {
                            $safeKey = is_string($key) ? $key : 'abnormal_param_' . $key;
                            return Group::make([
                                TextEntry::make('parameter_' . $safeKey)
                                    ->label('Parameter')
                                    ->getStateUsing(fn () => $item['parameter'] ?? '-'),

                                TextEntry::make('current_score_' . $safeKey)
                                    ->label('Current Score')
                                    ->getStateUsing(fn () => $item['current_score'] ?? '-'),

                                TextEntry::make('target_score_' . $safeKey)
                                    ->label('Target Score')
                                    ->getStateUsing(fn () => $item['target_score'] ?? '-'),

                                TextEntry::make('is_primary_concern_' . $safeKey)
                                    ->label('Primary?')
                                    ->getStateUsing(fn () => ($item['is_primary_concern'] ?? false) ? 'Yes' : 'No'),
                            ])
                            ->columns(4)
                            ->columnSpanFull()
                            ->extraAttributes([
                                'class' => 'p-4 rounded-lg bg-gray-50 border border-gray-200',
                            ]);

                        })->toArray();
                    })
                    ->collapsed()
                    ->collapsible(),

                // 🕒 Meta Info
                Section::make('Record Metadata')
                    ->icon('heroicon-o-clock')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->label('Created At')
                                ->dateTime(app_datetime_format()),

                            TextEntry::make('updated_at')
                                ->label('Last Updated')
                                ->dateTime(app_datetime_format()),
                        ]),
                    ]),
            ])->columns(1);
    }
}
