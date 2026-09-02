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
        return \App\Services\ReportAssetHelper::getReassessmentPdfContent($record, $session);
    }

    /**
     * Generate the overall reassessment report comparing latest post assessment with baseline.
     *
     * @param Assessment $record
     * @return string Raw PDF bytes
     */
    public function getVisualComparisonPdfContent(Assessment $record)
    {
        $latestSession = TreatmentSession::where('assessment_id', $record->id)
            ->where('status', 'completed')
            ->whereNotNull('post_diagnosis')
            ->orderBy('session_number', 'desc')
            ->first();

        return \App\Services\ReportAssetHelper::getReassessmentPdfContent($record, $latestSession);
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
            $hasSessionReassessment = TreatmentSession::where('assessment_id', $record->id)
                ->where('status', 'completed')
                ->whereNotNull('post_diagnosis')
                ->exists();

            if (!$hasSessionReassessment && !$record->post_diagnosis) {
                continue;
            }

            $patientName = $record->user ? str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', strtolower($record->user->name)) : 'patient';
            $filename = $patientName . '_assessment_' . $record->id . '_reassessment_report.pdf';

            try {
                $overallPdf = $this->getVisualComparisonPdfContent($record);
                $zip->addFromString($filename, $overallPdf);
                $hasFiles = true;
            } catch (\Exception $e) {
                \Log::error("Failed to generate overall reassessment PDF for assessment #{$record->id}: " . $e->getMessage());
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
