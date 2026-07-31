<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

use App\Models\Assessment;

class ReportController extends BaseApiController
{
    // ─────────────────────────────────────────────
    //  1. Download Facial Report
    // ─────────────────────────────────────────────
    public function downloadFacialReport($type, $assessment_id)
    {
        if($type == 'skin-analysis') {
            return $this->skinAnalysis($assessment_id);
        }else if($type == 'reassessment') {
            return $this->reassessment($assessment_id, request('session_id'));
        }else if($type == 'treatment-plan') {
            return $this->treatmentProtocol($assessment_id);
        }
    }

    // ─────────────────────────────────────────────
    //  1. Skin Analysis Diagnostic Report
    // ─────────────────────────────────────────────
    public function skinAnalysis($assessment_id)
    {

        $record = Assessment::find($assessment_id);

        $data['patient'] = $record->user->toArray();
        $data['patient']['name'] = $record->user->name;
        $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
        $data['data'] = $record;
        $data['diagnosis'] = $record->diagnosis;
        $data['key_parametrs'] = collect($record->parameters_with_abnormal_scores['parameters_with_abnormal_scores'] ?? []);
        $data['assessmentImages'] = $record->images;

        $html  = view('pdf.facial.skin_analysis', $data)->render();
        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('skin_analysis.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="skin_analysis.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  2. Personalized Treatment Protocol
    // ─────────────────────────────────────────────
    public function treatmentProtocol($assessment_id)
    {
        $record = Assessment::find($assessment_id);

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

        return response($mpdf->Output('treatment_protocol.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="treatment_protocol.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  3. Re-Assessment & Progress Report
    // ─────────────────────────────────────────────
    public function reassessment($assessment_id, $session_id = null)
    {
        $session_id = $session_id ?: request('session_id');
        $compare_to = request('compare_to', 'previous');
        $record = Assessment::find($assessment_id);
        $data['patient'] = $record->user->toArray();
        $data['patient']['name'] = $record->user->name;
        $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
        $data['assessment'] = $record;

        if ($session_id) {
            $session = \App\Models\TreatmentSession::findOrFail($session_id);
            $data['report_date'] = $session->updated_at;
            $reassessment = $session->post_diagnosis['reassessment'] ?? [];

            if ($compare_to === 'baseline') {
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

            $data['reassessment'] = $reassessment;
            $data['counts'] = collect($data['reassessment'])->pluck('result')->countBy();

            $postAssessmentImages = $session->post_images;

            if ($compare_to === 'baseline' || $session->session_number == 1) {
                $assessmentImages = $record->images;
            } else {
                $prevSession = \App\Models\TreatmentSession::where('assessment_id', $assessment_id)
                    ->where('session_number', $session->session_number - 1)
                    ->first();
                $assessmentImages = $prevSession ? $prevSession->post_images : $record->images;
            }
        } else {
            $data['report_date'] = $record->created_at;
            $data['reassessment'] = $record->post_diagnosis['reassessment'] ?? [];
            $data['counts'] = collect($data['reassessment'])->pluck('result')->countBy();
            $assessmentImages = $record->images;
            $postAssessmentImages = $record->post_images;
        }

        $data['assessmentImages'] = $assessmentImages;
        $data['postAssessmentImages'] = $postAssessmentImages;
        $data['compare_to'] = $compare_to;

        $html = view('pdf.facial.reassessment', $data)->render();
        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('reassessment.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reassessment.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  3.5. Download Client Journey Report
    // ─────────────────────────────────────────────
    public function downloadClientJourney($assessment_id)
    {
        $record = Assessment::findOrFail($assessment_id);
        $data['patient'] = $record->user->toArray();
        $data['patient']['name'] = $record->user->name;
        $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
        $data['report_date'] = now();
        $data['assessment'] = $record;

        // Fetch all completed treatment sessions with post_diagnosis
        $sessions = \App\Models\TreatmentSession::where('assessment_id', $assessment_id)
            ->where('status', 'completed')
            ->whereNotNull('post_diagnosis')
            ->orderBy('session_number', 'asc')
            ->get();

        $data['sessions'] = $sessions;

        $html = view('pdf.facial.client_journey', $data)->render();
        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('client_journey.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="client_journey.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  4. Download IV Report
    // ─────────────────────────────────────────────
    public function downloadIvReport($type, $assessment_id)
    {
        if($type == 'skin-analysis') {
            return $this->ivSkinAnalysis($assessment_id);
        }else if($type == 'plans') {
            return $this->ivPlans($assessment_id);
        }else if($type == 'program-roadmap') {
            return $this->programRoadmap($assessment_id);
        }else if($type == 'reassessment') {
            return $this->ivReassessment($assessment_id);
        }
    }

    // ─────────────────────────────────────────────
    //  5. Download IV Report
    // ─────────────────────────────────────────────
    public function ivSkinAnalysis($assessment_id)
    {
        $record = Assessment::find($assessment_id);

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

        return response($mpdf->Output('wellness-analysis-report.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="wellness-analysis-report.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  6. Download IV Plans
    // ─────────────────────────────────────────────
    public function ivPlans($assessment_id)
    {
        $record = Assessment::find($assessment_id);

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

        return response($mpdf->Output('iv-recommendation-report.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="iv-recommendation-report.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  7. Download IV Program Roadmap
    // ─────────────────────────────────────────────
    public function programRoadmap($assessment_id)
    {
        $record = Assessment::find($assessment_id);
        $selected_plan = $record->iv_selected_option;
        // echo '<pre>';
        // print_r($selected_plan);
        // die;
        // If it's a single session option (not the multi-session roadmap 'plan_option')
        if (isset($selected_plan['option_type']) && $selected_plan['option_type'] !== 'plan_option') {

            $data['patient'] = $record->user->toArray();
            $data['patient']['name'] = $record->user->name;
            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
            $data['report_date'] = $record->created_at;
            $data['program'] = $selected_plan;

            $html = view('pdf.iv.iv-single-session-report', $data)->render();

            $filename = 'iv-single-session-report.pdf';
        } else {

            $data['patient'] = $record->user->toArray();
            $data['patient']['name'] = $record->user->name;
            $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
            $data['report_date'] = $record->created_at;
            $data['program'] = $selected_plan;
            $data['program']['sessions'] = $selected_plan['protocols'][0]['sessions'] ?? [];

            $html = view('pdf.iv.iv-multi-session-report', $data)->render();

            $filename = 'iv-multi-session-report.pdf';
        }

        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory(__DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  8. Download IV Reassessment Report
    // ─────────────────────────────────────────────
    public function ivReassessment($assessment_id)
    {
        $record = Assessment::find($assessment_id);

        $data['patient'] = $record->user->toArray();
        $data['patient']['name'] = $record->user->name;
        $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
        $data['report_date'] = $record->created_at;
        $data['reassessment'] = $record->post_diagnosis['reassessment'];
        $data['counts'] = collect($data['reassessment'])
            ->pluck('result')
            ->countBy();

        $html = view('pdf.iv.iv-progress-reassessment-report', $data)->render();
        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('iv-progress-reassessment-report.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="iv-progress-reassessment-report.pdf"',
        ]);
    }


    // ─────────────────────────────────────────────
    //  9. Download Daily Homecare Routine
    // ─────────────────────────────────────────────
    public function downloadHomeCareRoutine($assessment_id, $session_id)
    {
        $record = Assessment::find($assessment_id);

        $data['patient'] = $record->user->toArray();
        $data['patient']['name'] = $record->user->name;
        $data['patient']['age'] = $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A';
        $data['report_date'] = $record->created_at;
        $data['session'] = $record->treatmentSessions['treatments']
            ->firstWhere('id', $session_id);

        $html = view('pdf.facial.daily_homecare_routine', $data)->render();
        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('daily_homecare_routine.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="daily_homecare_routine.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  10. Pigmentation Diagnosis Report
    // ─────────────────────────────────────────────
    public function downloadPigmentationDiagnosis($id)
    {
        $record = Assessment::with(['user', 'clinic'])->findOrFail($id);
        $diagnosis = $record->diagnosis ?? [];



        // Resolve patient images by mode
        $images = $record->images ?? collect();
        $getImageByMode = function($mode) use ($images) {
            $img = $images->first(function($item) use ($mode) {
                return ($item['custom_properties']['mode'] ?? $item['custom_properties']['type'] ?? '') === $mode || ($item['name'] ?? '') === $mode;
            });
            return $img ? $img['url'] : public_path('images/no-image.jpg');
        };

        $whiteImg = $getImageByMode('white');

        $mapUrl = $whiteImg;
        $whiteMedia = $record->getMedia("pigmentation_pre_assessment_images")->filter(function($item) {
            return ($item->custom_properties['mode'] ?? $item->custom_properties['type'] ?? '') === 'white' || $item->name === 'white';
        })->first();

        if ($whiteMedia && file_exists($whiteMedia->getPath())) {
            $destPath = storage_path('app/public/report_maps/map_' . $record->id . '.jpg');
            if (!is_dir(dirname($destPath))) {
                @mkdir(dirname($destPath), 0777, true);
            }
            try {
                $mapper = new \App\Services\ImageMappingService();
                $mapper->generateMap($whiteMedia->getPath(), $destPath, $diagnosis['diagnostic_components'] ?? []);
                $mapUrl = url('storage/report_maps/map_' . $record->id . '.jpg');
            } catch (\Exception $e) {
                \Log::error("Failed to generate dynamic map: " . $e->getMessage());
            }
        }

        $assets = [
            'cover_main' => $whiteImg,
            'map' => $mapUrl,
            'finding_full' => $whiteImg,
            'finding_cheek' => $whiteImg,
            'finding_eye' => $whiteImg,
            'snapshots' => [
                'white' => $getImageByMode('white'),
                'surface_polarized' => $getImageByMode('surface_polarized'),
                'subsurface_polarized' => $getImageByMode('subsurface_polarized'),
                'red' => $getImageByMode('red'),
                'woods_uv' => $getImageByMode('woods_uv')
            ],
            'modes' => [
                'white' => $getImageByMode('white'),
                'surface_polarized' => $getImageByMode('surface_polarized'),
                'subsurface_polarized' => $getImageByMode('subsurface_polarized'),
                'red' => $getImageByMode('red'),
                'woods_uv' => $getImageByMode('woods_uv')
            ]
        ];

        $components = $diagnosis['diagnostic_components'] ?? [];
        $overall = $this->findPigmentComponent($components, 'DC_001');
        $under = $this->findPigmentComponent($components, 'DC_002');
        $cheek = $this->findPigmentComponent($components, 'DC_003');
        $chin = $this->findPigmentComponent($components, 'DC_004');
        $shadowComp = $this->findPigmentComponent($components, 'DC_005');
        $dry = $this->findPigmentComponent($components, 'DC_006');
        $red = $this->findPigmentComponent($components, 'DC_009');
        $metrics = $diagnosis['immutable_image_metrics'] ?? [];
        $risk = $diagnosis['risk_profile'] ?? [];
        $summary = (string)($diagnosis['summaries']['patient_summary'] ?? '');
        $short = (string)($diagnosis['summaries']['patient_summary_short'] ?? 'Main concerns today are cheek brown spots, under-eye darkness and an overall uneven background tone.');
        $melanin = (int)($diagnosis['scores']['melanin_load_index'] ?? $metrics['global_background_melanin_load_index'] ?? 0);
        $erythema = (int)($diagnosis['scores']['erythema_load_index'] ?? $metrics['global_background_erythema_load_index'] ?? 0);

        $main = [
            [
                'title' => 'Overall Sun-Related Uneven Facial Tone', 'short_title' => 'Overall Uneven Tone',
                'copy' => $this->patientPigmentCopy($overall, 'Diffuse uneven tone is visible across the face.', 185),
                'short_copy' => 'Diffuse sun-related background pigmentation across the face.',
                'where' => $this->shortenPigmentText((string)($overall['clinical_location_text'] ?? 'Forehead, cheeks, nose and around the mouth.'), 105),
                'why' => 'This background tone can make focal spots appear more prominent.',
                'image' => $assets['finding_full'], 'icon' => 'icon_sun', 'colour' => 'gold', 'border' => 'gold'
            ],
            [
                'title' => 'Cheek Brown Spots / Photo-Induced Macules', 'short_title' => 'Cheek Brown Spots',
                'copy' => $this->patientPigmentCopy($cheek, 'Multiple flat brown spots are visible on both cheeks.', 185),
                'short_copy' => 'Multifocal flat tan-brown macules on both cheeks.',
                'where' => $this->shortenPigmentText((string)($cheek['clinical_location_text'] ?? 'Both malar and lower-cheek regions.'), 105),
                'why' => 'These persistent spots can darken over time without targeted care.',
                'image' => $assets['finding_cheek'], 'icon' => 'icon_pores', 'colour' => 'gold', 'border' => 'gold'
            ],
            [
                'title' => 'Under-Eye Pigmentation (Dark Circles with Mixed Causes)', 'short_title' => 'Under-Eye Darkness',
                'copy' => $this->patientPigmentCopy($under, 'Under-eye darkness includes pigment plus contour shadow.', 185),
                'short_copy' => 'Mixed dark circles with pigment plus contour shadow.',
                'where' => $this->shortenPigmentText((string)($under['clinical_location_text'] ?? 'Both lower eyelids and upper malar areas.'), 105),
                'why' => 'Pigment treatment may improve only the pigment component, not structural shadow.',
                'image' => $assets['finding_eye'], 'icon' => 'icon_eye', 'colour' => 'purple', 'border' => 'purple'
            ],
        ];

        $drivers = [];
        $driverColours = ['gold', 'cyan', 'purple'];
        foreach (array_slice($diagnosis['key_drivers'] ?? [], 0, 3) as $i => $driver) {
            $name = (string)($driver['driver'] ?? '');
            $title = match ($name) { 'sun' => 'Sun Exposure', 'structural' => 'Structural Shadowing', 'post_inflammatory' => 'Post-Inflammatory Tendency', default => ucwords(str_replace(['_', '-'], ' ', trim($name))) };
            $drivers[] = [
                'title' => $title,
                'copy' => $this->shortenPigmentText(implode(' ', array_slice($driver['basis'] ?? [], 0, 2)), 130),
                'colour' => $driverColours[$i] ?? 'cyan'
            ];
        }
        while (count($drivers) < 3) {
            $drivers[] = ['title' => 'Clinical Context', 'copy' => 'History and imaging together guide the final treatment sequence.', 'colour' => $driverColours[count($drivers)] ?? 'cyan'];
        }

        $modeMeta = [
            'white' => ['title' => 'White Light', 'caption' => 'Overall visible pigment and tone', 'colour' => 'gold', 'body' => ['Shows overall tone, visible pigmentation and the balance of light and shadow.', 'Diffuse uneven tone, cheek spots and under-eye darkness are clearly visible.']],
            'surface_polarized' => ['title' => 'Surface Polarized', 'caption' => 'Surface spots and texture', 'colour' => 'cyan', 'body' => ['Reduces surface reflection to highlight surface pigment and texture.', 'Cheek spots and surface irregularities are easier to compare.']],
            'subsurface_polarized' => ['title' => 'Subsurface Polarized', 'caption' => 'Deeper-looking pigment patterns', 'colour' => 'purple', 'body' => ['Reveals deeper-looking pigment distribution beneath surface reflection.', 'Pigment around the cheeks and under-eye area remains visible.']],
            'red' => ['title' => 'Red Mode', 'caption' => 'Visible redness and vascular activity', 'colour' => 'coral', 'body' => ['Enhances red tones to reveal visible irritation and vascular activity.', 'A focal red patch is visible on patient left, which is viewer right.']],
            'woods_uv' => ['title' => 'Woods UV', 'caption' => 'Superficial pigment and fluorescence', 'colour' => 'blue', 'body' => ['Highlights superficial pigment and fluorescence patterns.', 'Under-eye interpretation is limited by dryness and surface speckling.']],
        ];

        $modes = [];
        foreach ($modeMeta as $key => $meta) {
            $modes[] = ['key' => $key, 'title' => $meta['title'], 'caption' => $meta['caption'], 'colour' => $meta['colour'], 'lines' => $meta['body'], 'image' => $assets['modes'][$key]];
        }

        $fitz = $this->fitzpatrickFromDiagnosis($diagnosis);
        $depth = ucwords(str_replace(['_','-'], ' ', trim((string)($diagnosis['depth_assessment']['verdict'] ?? 'Mixed'))));
        $recurrence = ucwords(str_replace(['_','-'], ' ', trim((string)($risk['recurrence_risk'] ?? 'Moderate'))));
        $procedure = ucwords(str_replace(['_','-'], ' ', trim((string)($risk['procedure_risk'] ?? 'Moderate'))));
        $sunConsistency = ucwords(str_replace(['_','-'], ' ', trim((string)($risk['sunscreen_compliance_risk'] ?? 'Moderate'))));

        $profileRows = [
            [
                ['label' => 'Fitzpatrick Type', 'value' => $fitz, 'icon' => 'icon_user', 'colour' => 'gold', 'border' => 'gold'],
                ['label' => 'Dominant Pattern', 'value' => 'Melanin-Dominant', 'icon' => 'icon_pores', 'colour' => 'gold', 'border' => 'gold']
            ],
            [
                ['label' => 'Depth Pattern', 'value' => $depth, 'icon' => 'icon_renewal', 'colour' => 'purple', 'border' => 'purple'],
                ['label' => 'Recurrence Tendency', 'value' => $recurrence, 'icon' => 'icon_stable', 'colour' => 'cyan', 'border' => 'cyan']
            ],
            [
                ['label' => 'Procedure Sensitivity', 'value' => $procedure, 'icon' => 'icon_barrier', 'colour' => 'gold', 'border' => 'gold'],
                ['label' => 'Sun Protection Consistency', 'value' => $sunConsistency, 'icon' => 'icon_sun', 'colour' => 'cyan', 'border' => 'cyan']
            ]
        ];

        // Dynamic Cautions
        $cautions = [];
        $cautionNumber = 1;
        foreach ($components as $c) {
            if (($c['component_role'] ?? '') === 'treatment_modifier' || ($c['component_role'] ?? '') === 'pigmentation_contributor') {
                $icon = 'icon_target';
                $colour = 'cyan';
                $border = 'cyan';

                if (($c['family_code'] ?? '') === 'barrier_or_scale_modifier') {
                    $icon = 'icon_hydration';
                    $colour = 'cyan';
                    $border = 'cyan';
                }
                if (($c['family_code'] ?? '') === 'active_inflammatory_process') {
                    $icon = 'icon_stable';
                    $colour = 'coral';
                    $border = 'coral';
                }
                if (($c['subtype_code'] ?? '') === 'structural_shadow') {
                    $icon = 'icon_eye';
                    $colour = 'purple';
                    $border = 'purple';
                }

                $title = $c['patient_title'] ?? ucwords(str_replace('_', ' ', $c['subtype_code'] ?? 'Modifier'));
                $copy = $c['patient_explanation'] ?? $c['treatment_relevant_morphology'] ?? 'An important local finding that affects how treatment should be planned.';

                $cautions[] = [
                    'number' => (string)$cautionNumber++,
                    'title' => $this->shortenPigmentText($title, 40),
                    'copy' => $copy,
                    'icon' => $icon,
                    'colour' => $colour,
                    'border' => $border
                ];
            }
        }
        if (empty($cautions)) {
            $cautions[] = ['number' => '1', 'title' => 'Standard Care', 'copy' => 'No specific modifiers were flagged. Standard treatment pathways apply.', 'icon' => 'icon_target', 'colour' => 'cyan', 'border' => 'cyan'];
        }
        // Dynamic Care Cards
        $careCards = [];
        if (($risk['sunscreen_compliance_risk'] ?? '') === 'high') {
            $careCards[] = ['title' => 'Protect from sun consistently', 'copy' => 'Sun exposure can worsen pigmentation.', 'icon' => 'icon_sun', 'colour' => 'gold'];
        }
        foreach ($components as $c) {
            if (($c['family_code'] ?? '') === 'barrier_or_scale_modifier') {
                $careCards[] = ['title' => 'Go gently on dry areas', 'copy' => 'Use barrier-supportive care.', 'icon' => 'icon_barrier', 'colour' => 'purple'];
            }
            if (($c['family_code'] ?? '') === 'active_inflammatory_process') {
                $careCards[] = ['title' => 'Settle inflammation first', 'copy' => 'Active spots need to calm down before pigment treatment.', 'icon' => 'icon_stable', 'colour' => 'coral'];
            }
        }
        if (count($careCards) < 3) $careCards[] = ['title' => 'Use different approaches', 'copy' => 'Pigment, shadow, and dryness need different care.', 'icon' => 'icon_target', 'colour' => 'cyan'];

        // Dynamic Standouts
        $standouts = [];
        $standouts[] = ['title' => 'Spots are most visible in surface views', 'copy' => 'White and surface-polarized images show the focal macules most clearly.', 'colour' => 'gold'];
        foreach ($components as $c) {
            if (($c['subtype_code'] ?? '') === 'structural_shadow') {
                $standouts[] = ['title' => 'Darkness has mixed contributors', 'copy' => 'The darkness persists across modes, supporting pigment plus shadow.', 'colour' => 'cyan'];
            }
            if (($c['family_code'] ?? '') === 'active_inflammatory_process') {
                $standouts[] = ['title' => 'Red area needs a gentle approach', 'copy' => 'Inflammation is visible on red mode.', 'colour' => 'coral'];
            }
        }
        if (count($standouts) < 3) $standouts[] = ['title' => 'Background tone is diffuse', 'copy' => 'Uneven tone is visible across multiple regions.', 'colour' => 'gold'];

        // Dynamic Summary Columns
        $foundPoints = [];
        $focusPoints = [];
        foreach ($components as $c) {
            if (($c['component_role'] ?? '') === 'primary_pigment_target') {
                $foundPoints[] = $c['subtype_label'] ?? 'Pigmentation finding';
            } else if (($c['component_role'] ?? '') === 'treatment_modifier' || ($c['component_role'] ?? '') === 'pigmentation_contributor') {
                $focusPoints[] = 'Address ' . ($c['subtype_label'] ?? 'modifier');
            }
        }
        if (empty($foundPoints)) $foundPoints = ['Diffuse uneven tone.'];
        if (empty($focusPoints)) $focusPoints = ['Follow standard care.'];

        $summaryColumns = [
            ['title' => 'What We Found', 'icon' => 'icon_target', 'colour' => 'gold', 'border' => 'gold', 'points' => array_slice($foundPoints, 0, 3)],
            ['title' => 'What to Focus on First', 'icon' => 'icon_barrier', 'colour' => 'purple', 'border' => 'purple', 'points' => array_slice($focusPoints, 0, 3)],
            ['title' => 'What Will Matter Most', 'icon' => 'icon_stable', 'colour' => 'cyan', 'border' => 'cyan', 'points' => ['Daily sun protection.', 'Consistency with the staged plan.', 'Reassessment as the skin responds.']]
        ];

        // Dynamic Cautions
        $dynamicCautions = [];
        $cautionIndex = 1;
        foreach ($components as $c) {
            if (($c['component_role'] ?? '') === 'treatment_modifier' || ($c['component_role'] ?? '') === 'pigmentation_contributor') {
                $icon = 'icon_target';
                $colour = 'cyan';
                $border = 'cyan';

                if (($c['family_code'] ?? '') === 'barrier_or_scale_modifier') {
                    $icon = 'icon_hydration';
                    $colour = 'cyan';
                    $border = 'cyan';
                }
                if (($c['family_code'] ?? '') === 'active_inflammatory_process') {
                    $icon = 'icon_stable';
                    $colour = 'coral';
                    $border = 'coral';
                }
                if (($c['subtype_code'] ?? '') === 'structural_shadow') {
                    $icon = 'icon_eye';
                    $colour = 'purple';
                    $border = 'purple';
                }

                $dynamicCautions[] = [
                    'number' => (string)$cautionIndex++,
                    'title' => ucwords($c['subtype_label'] ?? 'Caution'),
                    'copy' => $c['patient_explanation'],
                    'icon' => $icon,
                    'colour' => $colour,
                    'border' => $border
                ];
            }
        }
        if (empty($dynamicCautions)) {
            $dynamicCautions[] = ['number' => '1', 'title' => 'Sun Exposure', 'copy' => 'Consistent sun protection is required to maintain results.', 'icon' => 'icon_sun', 'colour' => 'gold', 'border' => 'gold'];
        }

        $viewData = [
            'assets' => $assets,
            'patient' => [
                'name' => $record->user->name ?? 'Patient',
                'age' => $record->user->date_of_birth ? \Carbon\Carbon::parse($record->user->date_of_birth)->age : 'N/A',
                'gender' => $record->user->gender ? strtoupper(substr($record->user->gender, 0, 1)) : 'N/A'
            ],
            'reportDate' => date('d M Y'),
            'fitz' => $fitz,
            'summary' => $this->shortenPigmentText($summary, 520),
            'shortSummary' => $this->shortenPigmentText($short, 310),
            'melanin' => $melanin, 'erythema' => $erythema,
            'melaninBand' => $this->scorePigmentBand($melanin), 'erythemaBand' => $this->scorePigmentBand($erythema),
            'profileRows' => $profileRows,
            'main' => $main, 'drivers' => $drivers, 'modes' => $modes,
            'cautions' => array_slice($dynamicCautions, 0, 4),
            'snapshotOrder' => array_values(array_map(fn($key) => ['key' => $key, 'title' => $modeMeta[$key]['title'], 'colour' => $modeMeta[$key]['colour']], array_keys($modeMeta))),
            'careCards' => array_slice($careCards, 0, 3),
            'standouts' => array_slice($standouts, 0, 3),
            'summaryColumns' => $summaryColumns,
            'journey' => [
                ['title' => 'Today’s Diagnosis', 'copy' => 'Comprehensive skin and pigment analysis completed.', 'icon' => 'icon_calendar', 'colour' => 'gold'],
                ['title' => 'Personalised Treatment Plan', 'copy' => 'A staged plan tailored to the confirmed findings.', 'icon' => 'icon_target', 'colour' => 'purple'],
                ['title' => 'Guided Sessions', 'copy' => 'In-clinic treatment plus home-care guidance.', 'icon' => 'icon_user', 'colour' => 'blue'],
                ['title' => 'Reassessment', 'copy' => 'Progress review and plan refinement.', 'icon' => 'icon_stable', 'colour' => 'cyan'],
            ]
        ];

        // Dynamic Map Generation
        $mapLeft = [];
        $mapRight = [];
        $mapLegend = [];
        $legendColors = [];

        foreach ($components as $c) {
            $side = 'Right'; // default
            $regions = $c['regions'] ?? [];
            $loc = $c['clinical_location_text'] ?? '';

            $isLeft = in_array('left_malar_cheek', $regions) || in_array('left_periocular', $regions) || stripos($loc, 'left') !== false;
            $isRight = in_array('right_malar_cheek', $regions) || in_array('right_periocular', $regions) || stripos($loc, 'right') !== false;

            if ($isLeft && $isRight) $side = 'Both';
            else if ($isLeft) $side = 'Left';

            $colour = 'gold'; // default for primary_pigment_target
            $legendTitle = 'Main Pigment Finding';
            if (($c['component_role'] ?? '') === 'treatment_modifier') {
                if (($c['family_code'] ?? '') === 'active_inflammatory_process') {
                    $colour = 'coral';
                    $legendTitle = 'Settle First';
                } else if (($c['family_code'] ?? '') === 'barrier_or_scale_modifier') {
                    $colour = 'purple';
                    $legendTitle = 'Support Barrier First';
                } else {
                    $colour = 'cyan';
                    $legendTitle = 'Do Not Treat Directly';
                }
            }
            if (($c['component_role'] ?? '') === 'pigmentation_contributor') {
                $colour = 'purple';
                $legendTitle = 'Non-Pigment Contributor';
            }

            $item = [
                'title' => ucwords($c['subtype_label'] ?? 'Finding'),
                'copy' => $this->shortenPigmentText($c['treatment_relevant_morphology'] ?? $loc, 100),
                'colour' => $colour
            ];

            if ($side === 'Left' || $side === 'Both') {
                $mapLeft[] = $item;
            }
            if ($side === 'Right' || $side === 'Both') {
                $mapRight[] = $item;
            }

            if (!isset($legendColors[$colour])) {
                $legendColors[$colour] = true;
                $mapLegend[] = [
                    'title' => $legendTitle,
                    'copy' => 'Highlighted based on diagnosis role.',
                    'colour' => $colour
                ];
            }
        }
        $viewData['mapLeft'] = array_slice($mapLeft, 0, 4);
        $viewData['mapRight'] = array_slice($mapRight, 0, 4);
        $viewData['mapLegend'] = $mapLegend;

        // "What This Means" cards for page 2
        $whatThisMeans = [
            ['title' => 'Gradual improvement is realistic', 'copy' => 'Pigment change should be assessed over a staged course.', 'colour' => 'cyan']
        ];
        if (($risk['sunscreen_compliance_risk'] ?? '') === 'high') {
            $whatThisMeans[] = ['title' => 'Sun protection is essential', 'copy' => 'It helps prevent fresh pigment and rapid recurrence.', 'colour' => 'gold'];
        }
        foreach ($components as $c) {
            if (($c['family_code'] ?? '') === 'barrier_or_scale_modifier') {
                $whatThisMeans[] = ['title' => 'Care must be gentle', 'copy' => 'Barrier support comes before stronger treatment.', 'colour' => 'purple'];
                break;
            }
        }
        if (count($whatThisMeans) < 3) {
            $whatThisMeans[] = ['title' => 'Consistency is key', 'copy' => 'Follow the staged plan carefully.', 'colour' => 'purple'];
        }
        $viewData['whatThisMeans'] = array_slice($whatThisMeans, 0, 3);

        // Priorities for page 1
        $viewData['priorities'] = array_slice($main, 0, 3);

        $html = view('pdf.pigmentation.diagnosis', $viewData)->render();

        $config = config('project.mpdf_config');
        // Override margins and format for this specific report
        $config['margin_left'] = 7;
        $config['margin_right'] = 7;
        $config['margin_top'] = 6;
        $config['margin_bottom'] = 16;
        $config['format'] = [215.9, 279.4];

        // Register custom fonts
        $fontDirs = array_values(array_unique(array_merge($config['fontDir'] ?? [], [
            resource_path('views/fonts')
        ])));
        $config['fontDir'] = $fontDirs;

        $fontData = $config['fontdata'] ?? [];
        $fontData['montserratreportv36'] = [
            'R' => 'Montserrat-Regular.ttf',
            'B' => 'Montserrat-Bold.ttf',
            'I' => 'Montserrat-Italic.ttf',
            'BI' => 'Montserrat-BoldItalic.ttf'
        ];
        $fontData['playfairreportv36'] = [
            'R' => 'PlayfairDisplay-Regular.ttf',
            'I' => 'PlayfairDisplay-Italic.ttf',
            'B' => 'PlayfairDisplay-Bold.ttf',
            'BI' => 'PlayfairDisplay-BoldItalic.ttf'
        ];
        $config['fontdata'] = $fontData;
        $config['default_font'] = 'montserratreportv36';

        $mpdf = new \Mpdf\Mpdf($config);
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->showImageErrors = true;

        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        $patientName = $record->user ? str_replace(' ', '_', strtolower($record->user->name)) : 'patient';
        $filename = $patientName . '_pigmentation_diagnosis_report.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  11. Pigmentation Treatment Plan Report
    // ─────────────────────────────────────────────
    public function downloadPigmentationTreatmentPlan($id)
    {
        $record = Assessment::findOrFail($id);

        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->SetTitle('Treatment Plan');

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

        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('treatment-plan.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="treatment-plan.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  12. Pigmentation Reassessment (Post-Diagnosis) Report
    // ─────────────────────────────────────────────
    public function downloadPigmentationPostDiagnosis($id)
    {
        $record = Assessment::findOrFail($id);
        $sessionId = request('session_id');
        $compareTo = request('compare_to', 'baseline');

        $session = null;
        if ($sessionId) {
            $session = \App\Models\TreatmentSession::find($sessionId);
        }

        $data['patient'] = $record->user;
        $data['record'] = $record;

        if ($session) {
            $data['post_diagnosis'] = $session->post_diagnosis;
            $data['report_date'] = $session->updated_at;
            $postAssessmentImages = $session->post_images;

            if ($compareTo === 'baseline' || $session->session_number == 1) {
                $assessmentImages = $record->images;
            } else {
                $prevSession = \App\Models\TreatmentSession::where('assessment_id', $record->id)
                    ->where('session_number', $session->session_number - 1)
                    ->first();
                $assessmentImages = $prevSession ? $prevSession->post_images : $record->images;
            }
        } else {
            $data['post_diagnosis'] = $record->post_diagnosis;
            $data['report_date'] = $record->updated_at;
            $assessmentImages = $record->images;
            $postAssessmentImages = $record->post_images;
        }

        $data['assessmentImages'] = $assessmentImages;
        $data['postAssessmentImages'] = $postAssessmentImages;
        $data['compareRecord'] = null;
        $data['compare_type'] = $compareTo;

        $html = view('pdf.pigmentation.post-treatment', $data)->render();

        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        $patientName = $record->user ? str_replace(' ', '_', strtolower($record->user->name)) : 'patient';
        $filename = $patientName . '_pigmentation_reassessment' . ($session ? '_session_' . $session->session_number : '') . '_' . $compareTo . '_comparison.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    // --- Helper methods for Pigmentation Diagnosis ---
    private function shortenPigmentText(string $text, int $limit): string {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $limit) return $text;
        $cut = mb_substr($text, 0, $limit);
        $p = mb_strrpos($cut, ' ');
        if ($p !== false) $cut = mb_substr($cut, 0, $p);
        return rtrim($cut, ',;: ') . '.';
    }

    private function fitzpatrickFromDiagnosis(array $d): string {
        foreach ([($d['summaries']['clinical_summary_for_doctor'] ?? ''), ($d['validation_metadata']['reasoning'] ?? '')] as $text) {
            if (preg_match('/Fitzpatrick\s+([IVX]+)/i', (string)$text, $m)) return strtoupper($m[1]);
        }
        return 'IV';
    }

    private function findPigmentComponent(array $components, string $id): array {
        foreach ($components as $c) if (($c['diagnostic_component_id'] ?? '') === $id) return $c;
        return [];
    }

    private function patientPigmentCopy(array $c, string $fallback, int $limit): string {
        return $this->shortenPigmentText((string)($c['patient_explanation'] ?? $fallback), $limit);
    }

    private function scorePigmentBand(int $score): string {
        return match (true) {
            $score <= 10 => 'Minimal',
            $score <= 35 => 'Mild',
            $score <= 65 => 'Moderate',
            $score <= 85 => 'High',
            default => 'Very High'
        };
    }
}
