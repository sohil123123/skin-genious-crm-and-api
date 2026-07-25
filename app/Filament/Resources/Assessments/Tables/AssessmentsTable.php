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
use Filament\Actions\ActionGroup;

use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Mpdf\Mpdf;

use App\Filament\Resources\Clinics\Schemas\ClinicInfolist;

use App\Models\Clinic;
use App\Models\User;
use App\Models\Assessment;

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
                            ->record(fn($record) => $record->clinic)
                            ->infolist(
                                fn(Schema $schema, $record): Schema => ClinicInfolist::configure($schema->record($record->clinic))
                            )
                            ->modal()
                            ->modalHeading(fn($record) => $record->clinic?->name ?? 'No Clinic Assigned')
                            ->visible(fn($record) => $record->clinic !== null)
                    )
                    ->visible(fn() => check_role('super_admin'))
                    ->toggleable(),
                TextColumn::make('user.name')->label('User Name')->searchable(['first_name', 'last_name']),
                TextColumn::make('name')->placeholder('-')->searchable()->sortable(),
                TextColumn::make('assessment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'normal' => 'success',
                        'iv' => 'info',
                        'instant-normal' => 'warning',
                        'instant-iv' => 'primary',
                        'pigmentation' => 'success',
                        default => 'info',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'normal' => 'Facial',
                        'iv' => 'IV',
                        'instant-normal' => 'Instant Facial',
                        'instant-iv' => 'Instant IV',
                        default => $state ? ucfirst(str_replace(['-', '_'], ' ', $state)) : '-',
                    }),
                TextColumn::make('selected_plan_type')->label('Selected Plan')->badge()->placeholder('-'),
                // TextColumn::make('total_time')->searchable()->placeholder('-'),
                TextColumn::make('status')->badge(),
                TextColumn::make('createdBy.name')->label('Created By')->searchable(['first_name', 'last_name']),
                TextColumn::make('created_at')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters(
                [
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
                                                return User::whereHas('roles', fn($q) => $q->where('name', 'client'))
                                                    ->where('clinic_id', $clinicId)
                                                    ->get()
                                                    ->mapWithKeys(fn($u) => [$u->id => $u->name]);

                                            })
                                            ->reactive()
                                            ->searchable()
                                            ->placeholder('All users'),
                                    ]),
                                ]),
                        ])
                        ->query(function (Builder $query, array $data): Builder {
                            return $query
                                ->when($data['clinic_id'] ?? null, fn($q, $id) => $q->where('clinic_id', $id))
                                ->when($data['user_id'] ?? null, fn($q, $id) => $q->where('user_id', $id));
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
                layout: FiltersLayout::Modal
            )
            ->filtersFormColumns(3)
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->color('primary')
                    ->label('Filters')
                    ->icon('heroicon-o-funnel')
            )
            ->recordActions([
                ActionGroup::make([
                    // --- Facial Reports (Normal Type) ---
                    Action::make('diagnosis_pdf')
                        ->label('Facial Skin Analysis Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('Facial Skin Analysis Report')
                        ->visible(fn($record) => $record->assessment_type === 'normal' || $record->assessment_type === 'instant-normal')
                        ->action(function (Assessment $record) {

                            $data['patient'] = $record->user->toArray();
                            $data['patient']['name'] = $record->user->name;
                            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                            $data['data'] = $record;
                            $data['diagnosis'] = $record->diagnosis;
                            $data['key_parametrs'] = collect($record->parameters_with_abnormal_scores['parameters_with_abnormal_scores'] ?? []);
                            $data['assessmentImages'] = $record->images;

                            $html = view('pdf.facial.skin_analysis', $data)->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->showImageErrors = true;
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_facial_skin_analysis_report.pdf');
                        }),

                    Action::make('visual_comparison_pdf')
                        ->label('Facial Re-Assessment & Progress Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('Facial Re-Assessment & Progress Report')
                        ->visible(function (Assessment $record) {
                            $hasSessionReassessment = \App\Models\TreatmentSession::where('assessment_id', $record->id)
                                ->where('status', 'completed')
                                ->whereNotNull('post_diagnosis')
                                ->exists();

                            return $record->assessment_type === 'normal' && ($record->post_diagnosis || $hasSessionReassessment) && $record->images;
                        })
                        ->action(function (Assessment $record) {
                            $data['patient'] = $record->user->toArray();
                            $data['patient']['name'] = $record->user->name;
                            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';

                            // Fetch latest completed treatment session if available
                            $latestSession = \App\Models\TreatmentSession::where('assessment_id', $record->id)
                                ->where('status', 'completed')
                                ->whereNotNull('post_diagnosis')
                                ->orderBy('session_number', 'desc')
                                ->first();

                            if ($latestSession) {
                                $data['report_date'] = $latestSession->updated_at ?? $record->created_at;
                                $reassessment = $latestSession->post_diagnosis['reassessment'] ?? [];
                                $postAssessmentImages = $latestSession->post_images ?? $record->post_images;
                            } else {
                                $data['report_date'] = $record->created_at;
                                $reassessment = $record->post_diagnosis['reassessment'] ?? [];
                                $postAssessmentImages = $record->post_images;
                            }

                            $baselineDiagnosis = $record->diagnosis['diagnosis_report'] ?? [];

                            foreach ($reassessment as $key => &$item) {
                                $baselineScore = null;
                                if (isset($baselineDiagnosis[$key])) {
                                    $baselineScore = $baselineDiagnosis[$key]['score_or_label'] ?? null;
                                }
                                if ($baselineScore !== null) {
                                    $item['before_treatment_score_or_label'] = $baselineScore;

                                    // Recalculate result against baseline
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

                            $data['reassessment'] = $reassessment;
                            $data['counts'] = collect($data['reassessment'])
                                ->pluck('result')
                                ->countBy();

                            $data['assessmentImages'] = $record->images;
                            $data['postAssessmentImages'] = $postAssessmentImages;
                            $data['assessment'] = $record;
                            $data['compare_to'] = 'baseline';

                            $html = view('pdf.facial.reassessment', $data)->render();
                            $mpdf = new Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->showImageErrors = true;
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_facial_reassessment_report.pdf');
                        }),

                    Action::make('session_reassessment_pdf')
                        ->label('Facial Re-Assessment Report')
                        ->tooltip('Facial Re-Assessment Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn ($record) =>
                            in_array($record->assessment_type, ['normal', 'instant-normal']) &&
                            \App\Models\TreatmentSession::where('assessment_id', $record->id)
                                ->where('status', 'completed')
                                ->whereNotNull('post_diagnosis')
                                ->exists()
                        )
                        ->form(function (Assessment $record) {
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
                        ->action(function (Assessment $record, array $data) {
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
                            $mpdf->AddFontDirectory(__DIR__ . '/../../../Http/Controllers/Api/' . config('project.mpdf_font_dir'));
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

                    Action::make('treatment_plan_pdf')
                        ->label('Treatment Plan PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn($record) => $record->assessment_type === 'normal')
                        ->action(function (Assessment $record) {
                            $data['patient'] = $record->user->toArray();
                            $data['patient']['name'] = $record->user->name;
                            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                            $data['report_date'] = $record->created_at;
                            $data['sessions'] = $record->treatmentSessions['treatments'];
                            $data['treatment_goals'] = collect($record->treatmentSessions['treatments'])
                                ->pluck('concerns_addressed')
                                ->flatten(1)
                                ->unique('concern')
                                ->values()
                                ->toArray();

                            $html = view('pdf.facial.treatment_protocol', $data)->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_treatment_plan.pdf');
                        }),

                    Action::make('download_treatment_plan')
                        ->label('Treatment Plan Json')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(
                            fn($record) =>
                            $record->assessment_type === 'normal' &&
                            Storage::disk('files')->exists("treatment-plans/treatment_plans_#{$record->id}.json")
                        )
                        ->action(function ($record) {
                            $name = $record->user->name . '_treatment_plan.json';
                            $filePath = "treatment-plans/treatment_plans_#{$record->id}.json";
                            return response()->download(Storage::disk('files')->path($filePath), $name);
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
                        ->action(function (Assessment $record) {
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
                            $mpdf->AddFontDirectory( __DIR__ . '/../../../Http/Controllers/Api/' . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name. '_client_journey.pdf');
                        }),

                    // --- IV Reports (IV Type) ---
                    Action::make('iv_wellness_analysis_pdf')
                        ->label('IV Wellness Analysis Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('IV Wellness Analysis Report')
                        ->visible(fn($record) => ($record->assessment_type === 'iv' || $record->assessment_type === 'instant-iv') && $record->diagnosis && $record->diagnosis['iv_scoring_output'])
                        ->action(function (Assessment $record) {
                            $labels = [
                                'FENS' => 'Fluid & Electrolyte Need',
                                'PCCS' => 'Perfusion & Circulation Constraint',
                                'ASLS' => 'Autonomic Stress & Load',
                                'MONS' => 'Mitochondrial Output Need',
                                'ODS' => 'Oxidative / Detox Burden',
                                'ILS' => 'Inflammation / Immune Load',
                                'MSGS' => 'Metabolic Stability / Glycation',
                                'DGS' => 'Dermal Glow / Barrier Support',
                            ];
                            $what_it_means = $record->diagnosis['iv_scoring_output']['what_it_means'];
                            $primary_signals_reviewed = $record->diagnosis['iv_scoring_output']['primary_signals_reviewed'];
                            $scores = $record->diagnosis['iv_scoring_output']['scores_public_0_100'];

                            $iv_scors = collect($scores)->map(function ($value, $key) use ($labels, $what_it_means, $primary_signals_reviewed) {
                                return [
                                    'code' => $key,
                                    'label' => $labels[$key] ?? null,
                                    'score' => $value,
                                    'what_it_means' => $what_it_means[$key] ?? null,
                                    'primary_signals_reviewed' => $primary_signals_reviewed[$key] ?? null,
                                ];
                            })->values();

                            $data['data'] = $record;
                            $data['patient'] = $record->user->toArray();
                            $data['patient']['name'] = $record->user->name;
                            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                            $data['iv_scors'] = $iv_scors;
                            $data['telemetry'] = $record->diagnosis['iv_scoring_output']['telemetry'] ?? null;

                            $html = view('pdf.iv.wellness-analysis-report', $data)->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_IV_Wellness_Analysis_Report.pdf');
                        }),

                    Action::make('iv_recommendation_pdf')
                        ->label('IV Recommendation Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('IV Recommendation Report')
                        ->visible(fn($record) => $record->assessment_type === 'iv' && $record->iv_treatment_plan)
                        ->action(function (Assessment $record) {

                            $data['patient'] = $record->user->toArray();
                            $data['patient']['name'] = $record->user->name;
                            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                            $data['report_date'] = $record->created_at;
                            $data['options'] = $record->iv_treatment_plan['treatment_generation_output']['options'];

                            $html = view('pdf.iv.iv-recommendation-report', $data)->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);
                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_IV_Recommendation_Report.pdf');
                        }),

                    Action::make('iv_program_roadmap_pdf')
                        ->label('IV Program Roadmap Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('IV Program Roadmap Report')
                        ->visible(fn($record) => $record->assessment_type === 'iv' && $record->iv_selected_option)
                        ->action(function (Assessment $record) {
                            $selected_plan = $record->iv_selected_option;

                            // If it's a single session option (not the multi-session roadmap 'plan_option')
                            if (isset($selected_plan['option_type']) && $selected_plan['option_type'] !== 'plan_option') {

                                $data['patient'] = $record->user->toArray();
                                $data['patient']['name'] = $record->user->name;
                                $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                                $data['report_date'] = $record->created_at;
                                $data['program'] = $selected_plan;

                                $html = view('pdf.iv.iv-single-session-report', $data)->render();
                            } else {
                                $data['patient'] = $record->user->toArray();
                                $data['patient']['name'] = $record->user->name;
                                $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                                $data['report_date'] = $record->created_at;
                                $data['program'] = $selected_plan;
                                $data['program']['sessions'] = $selected_plan['protocols'][0]['sessions'] ?? [];

                                $html = view('pdf.iv.iv-multi-session-report', $data)->render();
                            }

                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_IV_Program_Roadmap.pdf');
                        }),

                    // --- Pigmentation Reports (Pigmentation Type) ---
                    Action::make('pigmentation_diagnosis_pdf')
                        ->label('Pigmentation Skin Analysis Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('Pigmentation Skin Analysis Report')
                        ->visible(fn($record) => $record->assessment_type === 'pigmentation')
                        ->action(function (Assessment $record) {
                            $html = view('pdf.pigmentation.diagnosis', ['record' => $record])->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $mpdf->showImageErrors = true;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_pigmentation_skin_analysis_report.pdf');
                        }),

                    Action::make('pigmentation_reassessment_pdf')
                        ->label('Pigmentation Re-Assessment & Progress Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('Pigmentation Re-Assessment & Progress Report')
                        ->visible(function (Assessment $record) {
                            return $record->assessment_type === 'pigmentation' && $record->post_diagnosis && $record->images && $record->post_images;
                        })
                        ->form(function (Assessment $record) {
                            $options = Assessment::where('user_id', $record->user_id)
                                ->where('assessment_type', 'pigmentation')
                                ->where('id', '!=', $record->id)
                                ->whereNotNull('diagnosis')
                                ->orderBy('created_at', 'desc')
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    return [$item->id => "Session on " . $item->created_at->format('d M Y, h:i A') . " (ID: #{$item->id})"];
                                })
                                ->toArray();

                            return [
                                Select::make('compare_id')
                                    ->label('Compare Current Session With')
                                    ->options(array_merge(
                                        ['baseline' => 'Baseline (Pre-treatment images of this assessment)'],
                                        $options
                                    ))
                                    ->default('baseline')
                                    ->required(),
                            ];
                        })
                        ->action(function (Assessment $record, array $data) {
                            $compareId = $data['compare_id'] ?? 'baseline';

                            $compareRecord = null;
                            if ($compareId !== 'baseline') {
                                $compareRecord = Assessment::find($compareId);
                            }

                            $viewData['patient'] = $record->user;
                            $viewData['post_diagnosis'] = $record->post_diagnosis;
                            $viewData['record'] = $record;

                            $viewData['assessmentImages'] = $compareRecord 
                                ? (count($compareRecord->post_images) > 0 ? $compareRecord->post_images : $compareRecord->images)
                                : $record->images;
                            $viewData['postAssessmentImages'] = $record->post_images;
                            $viewData['compareRecord'] = $compareRecord;
                            $viewData['compare_type'] = $compareId;

                            $html = view('pdf.pigmentation.post-treatment', $viewData)->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->showImageErrors = true;
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_pigmentation_reassessment_report.pdf');
                        }),

                    Action::make('pigmentation_treatment_plan_pdf')
                        ->label('Treatment Plan PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn($record) => $record->assessment_type === 'pigmentation')
                        ->action(function (Assessment $record) {
                            $html = view('pdf.pigmentation.treatment-plan', [
                                'client' => [
                                    'name' => $record->user->name,
                                    'age' => $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A',
                                    'gender' => $record->user->gender,
                                    'clinic' => $record->clinic->name ?? 'Main Clinic',
                                ],
                                'summary' => [
                                    'duration' => $record->total_time,
                                    'total_sessions' => count($record->treatmentSessions['treatments'] ?? []),
                                ],
                                'sessions' => $record->treatmentSessions,
                                'recommended_full_plan' => $record->recommended_full_plan,
                            ])->render();

                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $mpdf->SetTitle('Treatment Plan');
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name . '_pigmentation_treatment_plan.pdf');
                        }),
                ])
                    ->icon('heroicon-o-arrow-down-tray'),

                ActionGroup::make([
                    Action::make('edit_assessment')
                        ->label('Edit')
                        ->icon('heroicon-o-pencil')
                        ->color('primary')
                        ->tooltip('Edit Assessment')
                        ->action(function ($record) {
                            $assessmentUrl = edit_assessment($record, $record);
                            return redirect($assessmentUrl);
                        })
                        ->requiresConfirmation(),

                    Action::make('treatment_sessions')
                        ->label('Manage Treatment Sessions')
                        ->icon('heroicon-o-clipboard-document-check')
                        // ->iconButton()
                        ->color('primary')
                        ->tooltip('Manage Treatment Sessions')
                        ->url(fn($record) => route('filament.admin.resources.assessments.treatment-plans', ['record' => $record]))
                        ->visible(fn($record) => $record->assessment_type === 'normal' || $record->assessment_type === 'pigmentation'),
                ]),
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
                    ->getKeyFromRecordUsing(fn($record) => $record->clinic_id ?? 'no_clinic')
                    ->getTitleFromRecordUsing(fn($record) => $record->clinic?->name ?? 'Unassigned'),
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
            ->headerActions([
                \Filament\Tables\Actions\Action::make('download_all_clients_reassessment_zip')
                    ->label('Download Reassessment Reports (ZIP)')
                    ->icon('heroicon-o-archive-box')
                    ->color('success')
                    ->tooltip('Download all available reassessment reports for filtered clients as a ZIP file')
                    ->action(function ($livewire) {
                        $query = $livewire->getFilteredTableQuery();

                        // We only want normal and instant-normal assessments with completed treatment sessions having post_diagnosis
                        $assessments = $query->whereIn('assessment_type', ['normal', 'instant-normal'])
                            ->whereHas('treatmentSessions', function ($q) {
                                $q->where('status', 'completed')
                                  ->whereNotNull('post_diagnosis');
                            })
                            ->get();

                        if ($assessments->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('No completed reassessment sessions found for matching clients.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $service = new \App\Services\ReassessmentReportService();
                        $zipFilePath = $service->generateZipOfMultipleAssessments($assessments);

                        if (!$zipFilePath) {
                            \Filament\Notifications\Notification::make()
                                ->title('No completed reassessment reports generated for matching clients.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $zipName = 'clients_facial_reassessment_reports.zip';

                        return response()->download($zipFilePath, $zipName)->deleteFileAfterSend(true);
                    }),
            ])
            ->emptyStateDescription('Once you create your first assessment, it will appear here.');
    }
}
