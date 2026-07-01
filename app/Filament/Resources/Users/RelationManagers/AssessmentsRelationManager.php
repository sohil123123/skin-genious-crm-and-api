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

                Action::make('session_reassessment_pdf')
                    ->label('Facial Re-Assessment Report')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn ($record) => 
                        in_array($record->assessment_type, ['normal', 'instant-normal']) &&
                        \App\Models\TreatmentSession::where('assessment_id', $record->id)
                            ->where('status', 'completed')
                            ->whereNotNull('post_diagnosis')
                            ->exists()
                    )
                    ->form(function (\App\Models\Assessment $record) {
                        $completedSessions = \App\Models\TreatmentSession::where('assessment_id', $record->id)
                            ->where('status', 'completed')
                            ->whereNotNull('post_diagnosis')
                            ->orderBy('session_number', 'asc')
                            ->get();
                        
                        $sessionOptions = [];
                        foreach ($completedSessions as $sess) {
                            $sessionOptions[$sess->id] = "Session " . $sess->session_number . ": " . $sess->title;
                        }
                        
                        return [
                            \Filament\Forms\Components\Select::make('session_id')
                                ->label('Select Session')
                                ->options($sessionOptions)
                                ->required()
                                ->reactive(),
                                
                            \Filament\Forms\Components\Select::make('compare_to')
                                ->label('Comparison Base')
                                ->options(function (callable $get) {
                                    $sessId = $get('session_id');
                                    if (!$sessId) {
                                        return [
                                            'previous' => 'Compare to Previous Session',
                                            'baseline' => 'Compare to Baseline',
                                        ];
                                    }
                                    $sess = \App\Models\TreatmentSession::find($sessId);
                                    if ($sess && $sess->session_number == 1) {
                                        return [
                                            'baseline' => 'Compare to Baseline',
                                        ];
                                    }
                                    return [
                                        'previous' => 'Compare to Previous Session',
                                        'baseline' => 'Compare to Baseline',
                                    ];
                                })
                                ->default('previous')
                                ->required(),
                        ];
                    })
                    ->action(function (\App\Models\Assessment $record, array $data) {
                        $sessionId = $data['session_id'];
                        $compareTo = $data['compare_to'] ?? 'previous';
                        
                        $session = \App\Models\TreatmentSession::find($sessionId);
                        if (!$session) {
                            return;
                        }
                        
                        $patient = $record->user;
                        $pdfData = [];
                        $pdfData['patient'] = $patient ? $patient->toArray() : [];
                        if ($patient) {
                            $pdfData['patient']['name'] = $patient->name;
                            $pdfData['patient']['age'] = $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'N/A';
                        } else {
                            $pdfData['patient']['name'] = 'N/A';
                            $pdfData['patient']['age'] = 'N/A';
                        }
                        
                        $pdfData['assessment'] = $record;
                        $pdfData['report_date'] = $session->updated_at;
                        $reassessment = $session->post_diagnosis['reassessment'] ?? [];
                        
                        if ($compareTo === 'baseline') {
                            $baselineDiagnosis = $record->diagnosis['diagnosis_report'] ?? [];
                            foreach ($reassessment as $key => &$item) {
                                $baselineScore = null;
                                if (isset($baselineDiagnosis[$key])) {
                                    $baselineScore = $baselineDiagnosis[$key]['score_or_label'] ?? null;
                                }
                                if ($baselineScore !== null) {
                                    $item['before_treatment_score_or_label'] = $baselineScore;
                                    
                                    // Recalculate result
                                    $before = $baselineScore;
                                    $after = $item['post_treatment_score_or_label'] ?? '';
                                    $result = 'stable';
                                    if (strtolower(trim($before)) !== strtolower(trim($after))) {
                                        preg_match('/\d+/', $before, $mBefore);
                                        preg_match('/\d+/', $after, $mAfter);
                                        
                                        if (isset($mBefore[0]) && isset($mAfter[0])) {
                                            $valBefore = intval($mBefore[0]);
                                            $valAfter = intval($mAfter[0]);
                                            
                                            if (strpos(strtolower($key), 'glow') !== false || strpos(strtolower($key), 'luminosity') !== false) {
                                                $result = $valAfter > $valBefore ? 'improved' : ($valAfter < $valBefore ? 'declined' : 'stable');
                                            } else {
                                                $result = $valAfter < $valBefore ? 'improved' : ($valAfter > $valBefore ? 'declined' : 'stable');
                                            }
                                        } else {
                                            if (strtolower($before) === 'present' && strtolower($after) === 'absent') {
                                                $result = 'improved';
                                            } else if (strtolower($before) === 'absent' && strtolower($after) === 'present') {
                                                $result = 'declined';
                                            }
                                        }
                                    }
                                    $item['result'] = $result;
                                }
                            }
                            unset($item);
                        }

                        $pdfData['reassessment'] = $reassessment;
                        $pdfData['counts'] = collect($pdfData['reassessment'])->pluck('result')->countBy();
                        
                        $postAssessmentImages = $session->post_images;

                        if ($compareTo === 'baseline' || $session->session_number == 1) {
                            $assessmentImages = $record->images;
                        } else {
                            $prevSession = \App\Models\TreatmentSession::where('assessment_id', $record->id)
                                ->where('session_number', $session->session_number - 1)
                                ->first();
                            $assessmentImages = $prevSession ? $prevSession->post_images : $record->images;
                        }

                        $pdfData['assessmentImages'] = $assessmentImages;
                        $pdfData['postAssessmentImages'] = $postAssessmentImages;
                        $pdfData['compare_to'] = $compareTo;

                        $html = view('pdf.facial.reassessment', $pdfData)->render();
                        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                        $mpdf->AddFontDirectory(__DIR__ . '/../../Assessments/Tables/' . config('project.mpdf_font_dir'));
                        $mpdf->SetDisplayMode('fullpage');
                        $mpdf->shrink_tables_to_fit = 1;
                        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                        $mpdf->WriteHTML($html);

                        $patientName = $patient ? str_replace(' ', '_', strtolower($patient->name)) : 'patient';
                        $filename = $patientName . '_facial_reassessment_session_' . $session->session_number . '_' . $compareTo . '_comparison.pdf';

                        return response()->streamDownload(function () use ($mpdf) {
                            echo $mpdf->Output('', 'S');
                        }, $filename);
                    }),

                Action::make('client_journey_pdf')
                    ->label('Client Journey PDF')
                    ->icon('heroicon-o-document-chart-bar')
                    ->color('success')
                    ->visible(fn ($record) => 
                        in_array($record->assessment_type, ['normal', 'instant-normal']) &&
                        \App\Models\TreatmentSession::where('assessment_id', $record->id)
                            ->where('status', 'completed')
                            ->whereNotNull('post_diagnosis')
                            ->exists()
                    )
                    ->action(function ($record) {
                        $data['patient'] = $record->user->toArray();
                        $data['patient']['name'] = $record->user->name;
                        $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                        $data['report_date'] = now();
                        $data['assessment'] = $record;

                        // Fetch all completed treatment sessions with post_diagnosis
                        $sessions = \App\Models\TreatmentSession::where('assessment_id', $record->id)
                            ->where('status', 'completed')
                            ->whereNotNull('post_diagnosis')
                            ->orderBy('session_number', 'asc')
                            ->get();

                        $data['sessions'] = $sessions;

                        $html = view('pdf.facial.client_journey', $data)->render();
                        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                        $mpdf->AddFontDirectory( __DIR__ . '/../../Assessments/Tables/' . config('project.mpdf_font_dir'));
                        $mpdf->SetDisplayMode('fullpage');
                        $mpdf->shrink_tables_to_fit = 1;
                        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                        $mpdf->WriteHTML($html);

                        return response()->streamDownload(function () use ($mpdf) {
                            echo $mpdf->Output('', 'S');
                        }, $record->user->name. '_client_journey.pdf');
                    }),
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
