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

class TreatmentSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentSessions';

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
                    ->schema(function ($record) {
                        $isIv = $record->assessment && in_array($record->assessment->assessment_type, ['iv', 'instant-iv']);

                        if ($isIv) {
                            return [
                                Tabs::make('Treatment Details')->tabs([
                                    Tab::make('IV Session')
                                        ->icon('heroicon-o-cpu-chip')
                                        ->schema([
                                            Grid::make(3)->schema([
                                                TextEntry::make('ivSession.selected_protocol_id')->label('Protocol ID'),
                                                TextEntry::make('ivSession.selected_option_type')->label('Option Type'),
                                                TextEntry::make('ivSession.is_plan')->label('Is Plan?')->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')->badge()->color(fn($state)=>$state?'success':'gray'),
                                                TextEntry::make('ivSession.plan_week_index')->label('Plan Week Index'),
                                                TextEntry::make('ivSession.status')->label('Status')->badge(),
                                            ]),
                                            TextEntry::make('ivSession.engine_versions')
                                                ->label('Engine Versions')
                                                ->getStateUsing(fn($record) => json_encode($record->ivSession?->engine_versions))
                                                ->columnSpanFull(),
                                        ]),

                                    Tab::make('Bags')
                                        ->icon('heroicon-o-beaker')
                                        ->schema([
                                            RepeatableEntry::make('ivSession.bags')
                                                ->label('')
                                                ->columns(4)
                                                ->schema([
                                                    TextEntry::make('bag_label')->label('Bag Label')->weight('bold')->color('primary'),
                                                    TextEntry::make('carrier')->label('Carrier'),
                                                    TextEntry::make('volume_ml')->label('Volume (mL)'),
                                                    TextEntry::make('min_duration_minutes')->label('Min Duration (mins)'),
                                                    TextEntry::make('rate_profile')->label('Rate Profile')->columnSpanFull(),
                                                ])
                                        ]),

                                    Tab::make('Ingredients')
                                        ->icon('heroicon-o-sparkles')
                                        ->schema([
                                            RepeatableEntry::make('ivSession.ingredients')
                                                ->label('')
                                                ->columns(5)
                                                ->schema([
                                                    TextEntry::make('bag.bag_label')->label('Bag')->placeholder('N/A')->badge()->color('info'),
                                                    TextEntry::make('ingredient_name')->label('Ingredient')->weight('bold'),
                                                    TextEntry::make('dose_value')->label('Dose Value'),
                                                    TextEntry::make('dose_unit')->label('Unit'),
                                                    TextEntry::make('is_hero')->label('Is Hero?')->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')->badge()->color(fn($state)=>$state?'success':'gray'),
                                                ])
                                        ]),

                                    Tab::make('Snapshots')
                                        ->icon('heroicon-o-camera')
                                        ->schema([
                                            RepeatableEntry::make('ivSession.snapshots')
                                                ->label('')
                                                ->columns(1)
                                                ->schema([
                                                    TextEntry::make('scoring_payload')
                                                        ->formatStateUsing(fn ($state) => '<pre style="white-space: pre-wrap; font-size: 12px; background: #f3f4f6; padding: 10px; border-radius: 8px; max-height: 300px; overflow-y: auto;">'.json_encode($state, JSON_PRETTY_PRINT).'</pre>')
                                                        ->html()
                                                        ->columnSpanFull(),
                                                    TextEntry::make('generation_output')
                                                        ->formatStateUsing(fn ($state) => '<pre style="white-space: pre-wrap; font-size: 12px; background: #f3f4f6; padding: 10px; border-radius: 8px; max-height: 300px; overflow-y: auto;">'.json_encode($state, JSON_PRETTY_PRINT).'</pre>')
                                                        ->html()
                                                        ->columnSpanFull(),
                                                    TextEntry::make('execution_output')
                                                        ->formatStateUsing(fn ($state) => '<pre style="white-space: pre-wrap; font-size: 12px; background: #f3f4f6; padding: 10px; border-radius: 8px; max-height: 300px; overflow-y: auto;">'.json_encode($state, JSON_PRETTY_PRINT).'</pre>')
                                                        ->html()
                                                        ->columnSpanFull(),
                                                    TextEntry::make('constraints_snapshot')
                                                        ->formatStateUsing(fn ($state) => '<pre style="white-space: pre-wrap; font-size: 12px; background: #f3f4f6; padding: 10px; border-radius: 8px; max-height: 300px; overflow-y: auto;">'.json_encode($state, JSON_PRETTY_PRINT).'</pre>')
                                                        ->html()
                                                        ->columnSpanFull(),
                                                ])
                                        ]),
                                ])
                            ];
                        }

                        $isStructured = false;
                        if ($record->steps && is_array($record->steps) && count($record->steps) > 0) {
                            $first = $record->steps[0];
                            if (is_array($first) && (isset($first['how_to_do']) || isset($first['step_number']))) {
                                $isStructured = true;
                            }
                        }

                        $isStructuredRoutine = false;
                        if ($record->daily_home_care_routine && is_array($record->daily_home_care_routine)) {
                            if (isset($record->daily_home_care_routine['morning']) || isset($record->daily_home_care_routine['evening'])) {
                                $isStructuredRoutine = true;
                            }
                        }

                        return [
                            Tabs::make('Treatment Details')->tabs([
                                // 🧰 Therapist Preparation
                                Tab::make('Therapist Checklist')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        ViewEntry::make('preparations_checklist_for_therapist')
                                            ->label('Preparation Checklist')
                                            ->view('filament.infolists.entries.prep-checklist')
                                            ->columnSpanFull(),
                                    ]),

                                // 💆 Concerns Addressed
                                Tab::make('Concerns')
                                    ->icon('heroicon-o-heart')
                                    ->schema(
                                        $isStructured
                                            ? [
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
                                            ]
                                            : [
                                                TextEntry::make('concerns_addressed')
                                                    ->label('Concerns Addressed')
                                                    ->listWithLineBreaks()
                                                    ->bulleted()
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                            ]
                                    ),

                                // ⚙️ Treatment Steps
                                Tab::make('Steps')
                                    ->icon('heroicon-o-clipboard-document-check')
                                    ->schema(
                                        $isStructured
                                            ? [
                                                RepeatableEntry::make('steps')
                                                    ->label('Treatment Steps')
                                                    ->columns(4)
                                                    ->schema([
                                                        TextEntry::make('step_number')
                                                            ->label('Step #')
                                                            ->badge()
                                                            ->color('primary'),

                                                        TextEntry::make('duration')
                                                            ->label('Duration')
                                                            ->suffix(' mins'),

                                                        TextEntry::make('ingredients_equipments')
                                                            ->label('Ingredients & Equipments')
                                                            ->columnSpan(2),

                                                        TextEntry::make('how_to_do')
                                                            ->label('How To Do')
                                                            ->columnSpanFull()
                                                            ->markdown()
                                                            ->placeholder('—'),
                                                    ])
                                                    ->placeholder('No treatment steps listed.')
                                                    ->columnSpanFull(),
                                            ]
                                            : [
                                                TextEntry::make('steps')
                                                    ->label('Treatment Steps')
                                                    ->listWithLineBreaks()
                                                    ->bulleted()
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                            ]
                                    ),

                                // 🏡 Daily Home Care Routine
                                Tab::make('Home Care Routine')
                                    ->icon('heroicon-o-home')
                                    ->schema(
                                        $isStructuredRoutine
                                            ? [
                                                TextEntry::make('daily_home_care_routine.morning')
                                                    ->label('Morning Routine')
                                                    ->listWithLineBreaks()
                                                    ->bulleted()
                                                    ->placeholder('—'),
                                                TextEntry::make('daily_home_care_routine.evening')
                                                    ->label('Evening Routine')
                                                    ->listWithLineBreaks()
                                                    ->bulleted()
                                                    ->placeholder('—'),
                                            ]
                                            : [
                                                TextEntry::make('daily_home_care_routine')
                                                    ->label('Home Care Instructions')
                                                    ->listWithLineBreaks()
                                                    ->bulleted()
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                            ]
                                    ),

                                // 🎙️ Therapist Audio Script
                                Tab::make('Audio Script')
                                    ->icon('heroicon-o-microphone')
                                    ->schema([
                                        TextEntry::make('audio_text')
                                            ->label('Voiceover Script')
                                            ->placeholder('—')
                                            ->markdown()
                                            ->columnSpanFull(),
                                    ]),

                                // 📸 Post-Treatment Reassessment
                                Tab::make('Post-Treatment Reassessment')
                                    ->icon('heroicon-o-camera')
                                    ->schema([
                                        RepeatableEntry::make('post_diagnosis.reassessment')
                                            ->label('')
                                            ->columnSpanFull()
                                            ->columns(4)
                                            ->schema([
                                                TextEntry::make('parameter_name')->label('Parameter')->weight('bold'),
                                                TextEntry::make('before_treatment_score_or_label')->label('Before'),
                                                TextEntry::make('post_treatment_score_or_label')->label('After'),
                                                TextEntry::make('result')
                                                    ->label('Result')
                                                    ->badge()
                                                    ->color(fn ($state) => match (strtolower($state ?? '')) {
                                                        'improved' => 'success',
                                                        'stable' => 'warning',
                                                        'declined' => 'danger',
                                                        default => 'gray',
                                                    }),
                                            ])
                                            ->placeholder('No reassessment data available yet for this session.')
                                    ]),
                            ]),
                        ];
                    }),

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

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('session_number', 'asc')
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
                // TextColumn::make('session_number')->badge()->color('info')->searchable()->sortable(),
                TextColumn::make('title')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (mb_strlen($state) <= 50) {
                            return null;
                        }

                        return $state;
                    })
                    ->searchable()
                    ->sortable(),
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
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ucwords(str_replace('_', ' ', $state)))
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
                // TextColumn::make('created_at')->dateTime(app_datetime_format())->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download_homecare')
                    ->label('Download Routine')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(function ($record) {
                        $routine = $record->daily_home_care_routine;
                        return !empty($routine) && (!empty($routine['morning']) || !empty($routine['evening']));
                    })
                    ->action(function ($record) {
                        $assessment = $record->assessment;
                        if (!$assessment) {
                            return;
                        }
                        $patient = $assessment->user;
                        $data = [];
                        $data['patient'] = $patient ? $patient->toArray() : [];
                        if ($patient) {
                            $data['patient']['name'] = $patient->name;
                            $data['patient']['age'] = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A';
                        } else {
                            $data['patient']['name'] = 'N/A';
                            $data['patient']['age'] = 'N/A';
                        }
                        $data['report_date'] = $assessment->created_at;
                        $data['session'] = [
                            'session_number' => $record->session_number,
                            'week' => $record->week,
                            'title' => $record->title,
                            'daily_home_care_routine' => $record->daily_home_care_routine ?? [],
                        ];

                        $html = view('pdf.facial.daily_homecare_routine', $data)->render();
                        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                        $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                        $mpdf->SetDisplayMode('fullpage');
                        $mpdf->shrink_tables_to_fit = 1;
                        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                        $mpdf->WriteHTML($html);

                        $patientName = $patient ? str_replace(' ', '_', strtolower($patient->name)) : 'patient';
                        $filename = $patientName . '_daily_homecare_routine_session_' . $record->session_number . '.pdf';

                        return response()->streamDownload(function () use ($mpdf) {
                            echo $mpdf->Output('', 'S');
                        }, $filename);
                    }),
                Action::make('download_reassessment')
                    ->label('Download Reassessment')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('success')
                    ->visible(fn ($record) => !empty($record->post_diagnosis))
                    ->action(function ($record, array $data) {
                        $assessment = $record->assessment;
                        if (!$assessment) {
                            return;
                        }

                        $pdfContent = \App\Services\ReportAssetHelper::getReassessmentPdfContent($assessment, $record);

                        $patient = $assessment->user;
                        $patientName = $patient ? str_replace(' ', '_', strtolower($patient->name)) : 'patient';
                        $filename = $patientName . '_facial_reassessment_session_' . $record->session_number . '_baseline_comparison.pdf';

                        return response()->streamDownload(function () use ($pdfContent) {
                            echo $pdfContent;
                        }, $filename);
                    }),
                Action::make('complete_session_actions')
                    ->label('Post-Session Actions')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->assessment !== null && $record->status === 'completed')
                    ->url(function ($record) {
                        return clinic_head_complete_session($record);
                    })
                    ->openUrlInNewTab(),
            ])
            ->emptyStateDescription('Once you create your first plan, it will appear here.');
    }
}
