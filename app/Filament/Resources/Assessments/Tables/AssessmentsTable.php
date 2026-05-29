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
                TextColumn::make('assessment_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'normal' => 'success',
                        'iv' => 'info',
                        'instant-normal' => 'warning',
                        'instant-iv' => 'primary',
                        default => 'info',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'normal' => 'Facial',
                        'iv' => 'IV',
                        'instant-normal' => 'Instant Facial',
                        'instant-iv' => 'Instant IV',
                        default => '-',
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
                 Action::make('edit_assessment')
                    ->icon('heroicon-o-pencil')
                    ->iconButton()
                    ->color('primary')
                    ->tooltip('Edit Assessment')
                    ->action(function ($record) {
                        $assessmentUrl = edit_assessment($record,  $record);
                        return redirect($assessmentUrl);
                    })
                    ->requiresConfirmation(),

                Action::make('treatment_sessions')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->iconButton()
                    ->color('primary')
                    ->tooltip('Manage Treatment Sessions')
                    ->url(fn ($record) => route('filament.admin.resources.assessments.treatment-plans', ['record' => $record]))
                    ->visible(fn ($record) => $record->assessment_type === 'normal'),

                ActionGroup::make([
                    // --- Facial Reports (Normal Type) ---
                    Action::make('diagnosis_pdf')
                        ->label('Facial Skin Analysis Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('Facial Skin Analysis Report')
                        ->visible(fn ($record) => $record->assessment_type === 'normal' || $record->assessment_type === 'instant-normal')
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
                            $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->showImageErrors = true;
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name. '_facial_skin_analysis_report.pdf');
                        }),

                    Action::make('visual_comparison_pdf')
                        ->label('Facial Re-Assessment & Progress Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('Facial Re-Assessment & Progress Report')
                        ->visible(function (Assessment $record) {
                            return $record->assessment_type === 'normal' && $record->post_diagnosis && $record->images && $record->post_images;
                        })
                        ->action(function (Assessment $record) {
                            $data['patient'] = $record->user->toArray();
                            $data['patient']['name'] = $record->user->name;
                            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                            $data['report_date'] = $record->created_at;
                            $data['reassessment'] = $record->post_diagnosis['reassessment'];
                            $data['counts'] = collect($data['reassessment'])
                            ->pluck('result')
                            ->countBy();
                            $assessmentImages = $record->images;
                            $postAssessmentImages = $record->post_images;

                            $data['assessmentImages'] = $assessmentImages;
                            $data['postAssessmentImages'] = $postAssessmentImages;

                            $html = view('pdf.facial.reassessment', $data)->render();
                            $mpdf = new Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->showImageErrors = true;
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name. '_facial_reassessment_report.pdf');
                        }),

                    Action::make('treatment_plan_pdf')
                        ->label('Treatment Plan PDF')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn ($record) => $record->assessment_type === 'normal')
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
                            $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name. '_treatment_plan.pdf');
                        }),

                    Action::make('download_treatment_plan')
                        ->label('Treatment Plan Json')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn ($record) =>
                            $record->assessment_type === 'normal' &&
                            Storage::disk('files')->exists("treatment-plans/treatment_plans_#{$record->id}.json")
                        )
                        ->action(function ($record) {
                            $name = $record->user->name. '_treatment_plan.json';
                            $filePath = "treatment-plans/treatment_plans_#{$record->id}.json";
                            return response()->download(Storage::disk('files')->path($filePath), $name);
                        }),

                    // --- IV Reports (IV Type) ---
                    Action::make('iv_wellness_analysis_pdf')
                        ->label('IV Wellness Analysis Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('IV Wellness Analysis Report')
                        ->visible(fn ($record) => ($record->assessment_type === 'iv' || $record->assessment_type === 'instant-iv') && $record->diagnosis && $record->diagnosis['iv_scoring_output'])
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
                                    'code'  => $key,
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

                            $html  = view('pdf.iv.wellness-analysis-report', $data)->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);

                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name.'_IV_Wellness_Analysis_Report.pdf');
                        }),

                    Action::make('iv_recommendation_pdf')
                        ->label('IV Recommendation Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('IV Recommendation Report')
                        ->visible(fn ($record) => $record->assessment_type === 'iv' && $record->iv_treatment_plan)
                        ->action(function (Assessment $record) {

                            $data['patient'] = $record->user->toArray();
                            $data['patient']['name'] = $record->user->name;
                            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
                            $data['report_date'] = $record->created_at;
                            $data['options'] = $record->iv_treatment_plan['treatment_generation_output']['options'];

                            $html  = view('pdf.iv.iv-recommendation-report', $data)->render();
                            $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
                            $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
                            $mpdf->SetDisplayMode('fullpage');
                            $mpdf->shrink_tables_to_fit = 1;
                            $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
                            $mpdf->WriteHTML($html);
                            return response()->streamDownload(function () use ($mpdf) {
                                echo $mpdf->Output('', 'S');
                            }, $record->user->name.'_IV_Recommendation_Report.pdf');
                        }),

                    Action::make('iv_program_roadmap_pdf')
                        ->label('IV Program Roadmap Report')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->tooltip('IV Program Roadmap Report')
                        ->visible(fn ($record) => $record->assessment_type === 'iv' && $record->iv_selected_option)
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
                            }, $record->user->name.'_IV_Program_Roadmap.pdf');
                        }),
                ])
                ->icon('heroicon-o-arrow-down-tray'),
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
