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
            return $this->reassessment($assessment_id);
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
        // dd(collect($record->parameters_with_abnormal_scores['parameters_with_abnormal_scores']));
        $html  = view('pdf.facial.skin_analysis',
            [
                'data' => $record,
                'diagnosis' => $record->diagnosis,
                'key_parametrs' => collect($record->parameters_with_abnormal_scores['parameters_with_abnormal_scores']),
                'assessmentImages' => $record->images,
                'patient' => $record->user
            ]
        )->render();
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
        $data = [
            'patient' => [
                'name'   => 'Swati Mishra',
                'age'    => 46,
                'gender' => 'Female',
            ],
            'protocol' => [
                'total_sessions' => 4,
                'duration'       => '6 Weeks',
                'goals' => [
                    'Refine pore size and smooth visible texture in the T-zone.',
                    'Even out superficial pigmentation and enhance skin radiance.',
                    'Improve hydration support and barrier resilience.',
                    'Support jawline definition and overall firmness.',
                ],
                'modalities' => 'This plan uses Hydrafacial, chemical peels, radiofrequency tightening, Q-Switch laser, and LED light therapy, integrated into a cohesive protocol calibrated for visible results, comfort, and progressive skin improvement.',
                // Use collect() so ->chunk(2) works in Blade
                'sessions' => collect([
                    [
                        'number'           => 1,
                        'week'             => 0,
                        'name'             => 'Pore Decongestion + Resurfacing + RF Lift',
                        'duration'         => '70 min',
                        'focus'            => 'Pore refinement, texture smoothing, jawline support',
                        'expected_outcome' => 'Improved pore clarity and reduced congestion',
                    ],
                    [
                        'number'           => 2,
                        'week'             => 2,
                        'name'             => 'Carbon Facial (Q-Switch) + Tone Evening',
                        'duration'         => '60 min',
                        'focus'            => 'Pore clarity, radiance enhancement, oil control',
                        'expected_outcome' => 'Enhanced radiance and more refined texture',
                    ],
                    [
                        'number'           => 3,
                        'week'             => 4,
                        'name'             => 'RF Lift + Infusion + Under-eye',
                        'duration'         => '60 min',
                        'focus'            => 'Firmness, hydration, dark circle reduction',
                        'expected_outcome' => 'Improved jawline support and reduced dark circles',
                    ],
                    [
                        'number'           => 4,
                        'week'             => 6,
                        'name'             => 'Combination Peel + Hydration Finish',
                        'duration'         => '60 min',
                        'focus'            => 'Results consolidation and sustained improvement',
                        'expected_outcome' => 'Overall quality improvement with sustained results',
                    ],
                    [
                        'number'           => 3,
                        'week'             => 4,
                        'name'             => 'RF Lift + Infusion + Under-eye',
                        'duration'         => '60 min',
                        'focus'            => 'Firmness, hydration, dark circle reduction',
                        'expected_outcome' => 'Improved jawline support and reduced dark circles',
                    ],
                    [
                        'number'           => 4,
                        'week'             => 6,
                        'name'             => 'Combination Peel + Hydration Finish',
                        'duration'         => '60 min',
                        'focus'            => 'Results consolidation and sustained improvement',
                        'expected_outcome' => 'Overall quality improvement with sustained results',
                    ],
                ]),
            ],
        ];
        // dd($record->treatmentSessions['treatments']);
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
    public function reassessment($assessment_id)
    {

        $record = Assessment::find($assessment_id);
        $data['patient'] = $record->user;
        $data['reassessment'] = $record->post_diagnosis['reassessment'];
        $data['counts'] = collect($data['reassessment'])
        ->pluck('status')
        ->countBy();
        // dd($data);
        $assessmentImages = $record->images;
        $postAssessmentImages = $record->post_images;

        $data['assessmentImages'] = $assessmentImages;
        $data['postAssessmentImages'] = $postAssessmentImages;

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

        $html  = view('pdf.iv.wellness-analysis-report',
            [
                'data' => $record,
                'patient' => $record->user,
                'iv_scors' => $iv_scors,
            ]
        )->render();
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

        // dd($record->iv_treatment_plan['treatment_generation_output']['options']);

        $html  = view('pdf.iv.iv-recommendation-report',
            [
                'age' => $record->age,
                'report_date' => $record->created_at,
                'patient' => $record->user,
                'options' => $record->iv_treatment_plan['treatment_generation_output']['options'],
            ]
        )->render();
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

        // If it's a single session option (not the multi-session roadmap 'plan_option')
        if (isset($selected_plan['option_type']) && $selected_plan['option_type'] !== 'plan_option') {
            $html = view('pdf.iv.iv-single-session-report', [
                'age' => $record->age,
                'report_date' => $record->created_at,
                'patient' => $record->user,
                'program' => $selected_plan,
            ])->render();

            $filename = 'iv-single-session-report.pdf';
        } else {
            $html = view('pdf.iv.iv-multi-session-report', [
                'age' => $record->age,
                'report_date' => $record->created_at,
                'patient' => $record->user,
                'program' => $selected_plan,
            ])->render();

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
}
