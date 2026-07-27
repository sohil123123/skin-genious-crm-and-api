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
        $record = Assessment::findOrFail($assessment_id);
        $pdfContent = \App\Services\ReportAssetHelper::getSkinAnalysisPdfContent($record);

        return response($pdfContent, 200, [
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
        $record = Assessment::findOrFail($assessment_id);
        $session = $session_id ? \App\Models\TreatmentSession::findOrFail($session_id) : null;

        $pdfContent = \App\Services\ReportAssetHelper::getReassessmentPdfContent($record, $session);

        return response($pdfContent, 200, [
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
        $record = Assessment::findOrFail($id);

        $html = view('pdf.pigmentation.diagnosis', ['record' => $record])->render();

        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $mpdf->showImageErrors = true;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('diagnosis-report.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="diagnosis-report.pdf"',
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

        $data['patient'] = $record->user;
        $data['post_diagnosis'] = $record->post_diagnosis;
        $data['record'] = $record;
        $data['assessmentImages'] = $record->images;
        $data['postAssessmentImages'] = $record->post_images;
        $data['compareRecord'] = null;
        $data['compare_type'] = 'baseline';

        $html = view('pdf.pigmentation.post-treatment', $data)->render();
        
        $mpdf = new \Mpdf\Mpdf(config('project.mpdf_config'));
        $mpdf->AddFontDirectory( __DIR__ . config('project.mpdf_font_dir'));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('post-treatment-comparison.pdf', 'S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="post-treatment-comparison.pdf"',
        ]);
    }

    // ─────────────────────────────────────────────
    //  13. Download All Reassessment Reports as ZIP
    // ─────────────────────────────────────────────
    public function downloadAllReassessmentZip($assessment_id)
    {
        $record = Assessment::findOrFail($assessment_id);
        $service = new \App\Services\ReassessmentReportService();
        $zipFilePath = $service->generateZipOfReports($record);

        if (!$zipFilePath) {
            return response()->json(['message' => 'No completed reassessment sessions found for this client.'], 404);
        }

        $patientName = $record->user ? str_replace(' ', '_', strtolower($record->user->name)) : 'patient';
        $zipName = $patientName . '_facial_reassessment_reports.zip';

        return response()->download($zipFilePath, $zipName)->deleteFileAfterSend(true);
    }
}
