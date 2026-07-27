<?php

namespace App\Filament\Resources\Assessments\Pages;

use App\Filament\Resources\Assessments\AssessmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use App\Models\Assessment;
use App\Services\ReportAssetHelper;
use ZipArchive;
use Filament\Notifications\Notification;

class ListAssessments extends ListRecords
{
    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_all_reassessments')
                ->label('Download All Reassessments (ZIP)')
                ->icon('heroicon-o-archive-box')
                ->color('success')
                ->action(function () {
                    $records = Assessment::where('assessment_type', 'normal')
                        ->where(function ($query) {
                            $query->whereNotNull('post_diagnosis')
                                ->orWhereHas('treatmentSessions', function ($q) {
                                    $q->where('status', 'completed')
                                      ->whereNotNull('post_diagnosis');
                                });
                        })
                        ->get();

                    if ($records->isEmpty()) {
                        Notification::make()
                            ->title('No reassessment reports available to download')
                            ->warning()
                            ->send();
                        return;
                    }

                    $zip = new ZipArchive();
                    $zipPath = tempnam(sys_get_temp_dir(), 'reassessments') . '.zip';

                    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                        Notification::make()
                            ->title('Could not create ZIP file')
                            ->danger()
                            ->send();
                        return;
                    }

                    $addedFiles = 0;
                    foreach ($records as $record) {
                        $latestSession = \App\Models\TreatmentSession::where('assessment_id', $record->id)
                            ->where('status', 'completed')
                            ->whereNotNull('post_diagnosis')
                            ->orderBy('session_number', 'desc')
                            ->first();

                        try {
                            $pdfContent = ReportAssetHelper::getReassessmentPdfContent($record, $latestSession);
                            
                            $patientName = $record->user ? str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $record->user->name) : 'Patient_' . $record->id;
                            $filename = $patientName . '_facial_reassessment_report.pdf';
                            
                            // Prevent filename collisions inside the zip
                            $zipFilename = $filename;
                            $i = 1;
                            while ($zip->locateName($zipFilename) !== false) {
                                $zipFilename = $patientName . '_facial_reassessment_report_' . $i . '.pdf';
                                $i++;
                            }
                            
                            $zip->addFromString($zipFilename, $pdfContent);
                            $addedFiles++;
                        } catch (\Throwable $e) {
                            \Log::error("Failed to generate PDF for assessment #{$record->id}: " . $e->getMessage());
                        }
                    }

                    $zip->close();

                    if ($addedFiles === 0) {
                        @unlink($zipPath);
                        Notification::make()
                            ->title('Failed to generate any reassessment reports')
                            ->danger()
                            ->send();
                        return;
                    }

                    return response()->download($zipPath, 'all_clients_reassessment_reports.zip')->deleteFileAfterSend(true);
                })
        ];
    }
}
