<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class ReportController extends BaseApiController
{
    // ─────────────────────────────────────────────
    //  Shared mPDF factory
    // ─────────────────────────────────────────────
    private function makeMpdf(): Mpdf
    {
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'margin_top'     => 38,   // leaves space for htmlpageheader
            'margin_bottom'  => 28,   // leaves space for htmlpagefooter
            'margin_left'    => 12,
            'margin_right'   => 12,
            'setAutoTopMargin'    => 'stretch',
            'setAutoBottomMargin' => 'stretch',
        ]);

        $mpdf->SetDisplayMode('fullpage');

        return $mpdf;
    }

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

        $record = \App\Models\Assessment::find($assessment_id);

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
        $record = \App\Models\Assessment::find($assessment_id);
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

        $record = \App\Models\Assessment::find($assessment_id);
        $data['patient'] = $record->user;
        $data['reassessment'] = $record->post_diagnosis['reassessment'];
        $data['counts'] = collect($data['reassessment'])
        ->pluck('status')
        ->countBy();

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
}
