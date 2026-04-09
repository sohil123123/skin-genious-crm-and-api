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

    // ─────────────────────────────────────────────
    //  1. Skin Analysis Diagnostic Report
    // ─────────────────────────────────────────────
    public function skinAnalysis()
    {
        $data = [
            'patient' => [
                'name'         => 'Swati Mishra',
                'age'          => 46,
                'gender'       => 'Female',
                'report_date'  => '16/03/2026',
                'skin_profile' => 'Combination',
            ],
            'overview' => [
                'mild'        => 6,
                'moderate'    => 8,
                'significant' => 1,
            ],
            // Use collect() so ->chunk(2) works in Blade
            'parameters' => collect([
                [
                    'name'        => 'Skin Type Classification - Combination',
                    'description' => 'Your skin shows a mix of oilier areas in the T-zone with more balanced cheeks. This is typical of combination skin and affects both product choice and treatment sequencing.',
                ],
                [
                    'name'        => 'Barrier Health + Sensitivity - 3/5',
                    'description' => 'Your barrier is mostly stable, with mild sensitivity and localized areas of compromise. The focus is protection, hydration support, and avoiding unnecessary irritation.',
                ],
                [
                    'name'        => 'Visual Acne Grading - 2/5',
                    'description' => 'Breakout activity is very mild, with small congestion and a few inflamed points. Acne severity is low and appears well controlled at present.',
                ],
                [
                    'name'        => 'Skin Sebum Index - 3/5',
                    'description' => 'Oil production is moderate overall, with a clear concentration in the T-zone. The aim is oil balance without over-drying the healthier zones of the face.',
                ],
            ]),
        ];

        $html  = view('pdf.facial.skin_analysis', $data)->render();
        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('skin_analysis.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="skin_analysis.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  2. Personalized Treatment Protocol
    // ─────────────────────────────────────────────
    public function treatmentProtocol()
    {
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
                ]),
            ],
        ];

        $html = view('pdf.treatment_protocol', $data)->render();
        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('treatment_protocol.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="treatment_protocol.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  3. Re-Assessment & Progress Report
    // ─────────────────────────────────────────────
    public function reassessment()
    {
        $data = [
            'patient' => [
                'name'   => 'Madhavi Kumar',
                'age'    => 45,
                'gender' => 'Female',
            ],
            'reassessment' => [
                'treatment_period'    => '6 Weeks',
                'sessions_completed'  => 4,
                'total_sessions'      => 4,
                'params_improved'     => 5,
                'params_stable'       => 9,
                'params_monitored'    => 1,
                'improvements' => [
                    ['name' => 'Barrier Health + Sensitivity', 'before' => '3/5', 'after' => '2/5'],
                    ['name' => 'Skin Hydration Score',         'before' => '2/5', 'after' => '3/5'],
                    ['name' => 'Skin Luminosity / Glow Index', 'before' => '2/5', 'after' => '3/5'],
                    ['name' => 'Textural Radiance Index',      'before' => '3/5', 'after' => '2/5'],
                ],
                'maintenance' => [
                    'Continue maintenance treatments every 4-6 weeks to sustain and enhance the improvements achieved.',
                    'Maintain a consistent skincare routine with niacinamide / ceramide serums, hydrating moisturizers, and daily SPF 50+ protection.',
                    'Schedule a follow-up re-assessment in 3 months to evaluate longer-term response and refine the plan if needed.',
                ],
            ],
        ];

        $html = view('pdf.reassessment', $data)->render();
        $mpdf = $this->makeMpdf();
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('reassessment.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reassessment.pdf"',
        ]);
    }
}
