<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\TreatmentSession;
use Mpdf\Mpdf;
use Carbon\Carbon;

class ReassessmentReportService
{
    /**
     * Generate the comparison report PDF for a single treatment session with baseline.
     *
     * @param Assessment $record
     * @param TreatmentSession $session
     * @param string $compareTo
     * @return string Raw PDF bytes
     */
    public function getReassessmentPdfContent(Assessment $record, TreatmentSession $session, string $compareTo = 'baseline')
    {
        $patient = $record->user;
        $pdfData = [];
        $pdfData['patient'] = $patient ? $patient->toArray() : [];
        if ($patient) {
            $pdfData['patient']['name'] = $patient->name;
            $pdfData['patient']['age'] = $patient->date_of_birth ? Carbon::parse($patient->date_of_birth)->age : 'N/A';
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
            $prevSession = TreatmentSession::where('assessment_id', $record->id)
                ->where('session_number', $session->session_number - 1)
                ->first();
            $assessmentImages = $prevSession ? $prevSession->post_images : $record->images;
        }

        $pdfData['assessmentImages'] = $assessmentImages;
        $pdfData['postAssessmentImages'] = $postAssessmentImages;
        $pdfData['compare_to'] = $compareTo;

        $html = view('pdf.facial.reassessment', $pdfData)->render();
        
        $mpdf = new Mpdf(config('project.mpdf_config'));
        
        $fontPath = public_path('fonts/Montserrat');
        if (file_exists($fontPath)) {
            $mpdf->AddFontDirectory($fontPath);
        } else {
            $mpdf->AddFontDirectory(app_path('Http/Controllers/Api') . config('project.mpdf_font_dir'));
        }
        
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Generate the overall reassessment report comparing latest post assessment with baseline.
     *
     * @param Assessment $record
     * @return string Raw PDF bytes
     */
    public function getVisualComparisonPdfContent(Assessment $record)
    {
        $data['patient'] = $record->user->toArray();
        $data['patient']['name'] = $record->user->name;
        $data['patient']['age'] = $record->user->date_of_birth ? Carbon::parse($record->user->date_of_birth)->age : 'N/A';

        // Fetch latest completed treatment session if available
        $latestSession = TreatmentSession::where('assessment_id', $record->id)
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
        
        $fontPath = public_path('fonts/Montserrat');
        if (file_exists($fontPath)) {
            $mpdf->AddFontDirectory($fontPath);
        } else {
            $mpdf->AddFontDirectory(app_path('Http/Controllers/Api') . config('project.mpdf_font_dir'));
        }
        
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;
        $html = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Generate a ZIP archive containing all available reassessment report PDFs compared with baseline.
     *
     * @param Assessment $record
     * @return string|null Path to the generated ZIP file, or null if no sessions found.
     */
    public function generateZipOfReports(Assessment $record)
    {
        $completedSessions = TreatmentSession::where('assessment_id', $record->id)
            ->where('status', 'completed')
            ->whereNotNull('post_diagnosis')
            ->orderBy('session_number', 'asc')
            ->get();

        if ($completedSessions->isEmpty()) {
            return null;
        }

        $zip = new \ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'reassessment_zip');
        
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create zip archive");
        }

        $patientName = $record->user ? str_replace(' ', '_', strtolower($record->user->name)) : 'patient';

        // 1. Add overall "Facial Re-Assessment & Progress Report" (latest comparison) if available
        $hasSessionReassessment = $completedSessions->isNotEmpty();
        if ($record->assessment_type === 'normal' && ($record->post_diagnosis || $hasSessionReassessment) && $record->images) {
            $overallPdf = $this->getVisualComparisonPdfContent($record);
            $zip->addFromString($patientName . '_overall_facial_reassessment_progress_report.pdf', $overallPdf);
        }

        // 2. Add each session reassessment report compared to baseline
        foreach ($completedSessions as $session) {
            $baselinePdf = $this->getReassessmentPdfContent($record, $session, 'baseline');
            $zip->addFromString($patientName . '_facial_reassessment_session_' . $session->session_number . '_baseline_comparison.pdf', $baselinePdf);
        }

        $zip->close();

        return $tempFile;
    }

    /**
     * Generate a ZIP archive containing reassessment reports for multiple assessments.
     *
     * @param \Illuminate\Support\Collection|array $assessments
     * @return string|null Path to the generated ZIP file, or null if no files added.
     */
    public function generateZipOfMultipleAssessments($assessments)
    {
        if (empty($assessments)) {
            return null;
        }

        // Increase limits to handle potential load of generating multiple PDFs
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $zip = new \ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'reassessment_all_zip');
        
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create zip archive");
        }

        $hasFiles = false;

        foreach ($assessments as $record) {
            $completedSessions = TreatmentSession::where('assessment_id', $record->id)
                ->where('status', 'completed')
                ->whereNotNull('post_diagnosis')
                ->orderBy('session_number', 'asc')
                ->get();

            if ($completedSessions->isEmpty()) {
                continue;
            }

            $patientName = $record->user ? str_replace(' ', '_', strtolower($record->user->name)) : 'patient';
            $folderName = $patientName . '_assessment_' . $record->id;

            // 1. Add overall "Facial Re-Assessment & Progress Report" (latest comparison) if available
            $hasSessionReassessment = $completedSessions->isNotEmpty();
            if ($record->assessment_type === 'normal' && ($record->post_diagnosis || $hasSessionReassessment) && $record->images) {
                try {
                    $overallPdf = $this->getVisualComparisonPdfContent($record);
                    $zip->addFromString($folderName . '/' . $patientName . '_overall_facial_reassessment_progress_report.pdf', $overallPdf);
                    $hasFiles = true;
                } catch (\Exception $e) {
                    \Log::error("Failed to generate overall reassessment PDF for assessment #{$record->id}: " . $e->getMessage());
                }
            }

            // 2. Add each session reassessment report compared to baseline
            foreach ($completedSessions as $session) {
                try {
                    $baselinePdf = $this->getReassessmentPdfContent($record, $session, 'baseline');
                    $zip->addFromString($folderName . '/' . $patientName . '_facial_reassessment_session_' . $session->session_number . '_baseline_comparison.pdf', $baselinePdf);
                    $hasFiles = true;
                } catch (\Exception $e) {
                    \Log::error("Failed to generate session reassessment PDF for session #{$session->id}: " . $e->getMessage());
                }
            }
        }

        $zip->close();

        if (!$hasFiles) {
            @unlink($tempFile);
            return null;
        }

        return $tempFile;
    }
}
