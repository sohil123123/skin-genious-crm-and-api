<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Assessment;
use App\Models\User;
use App\Services\ReassessmentReportService;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateBulkReassessmentReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // Allow up to 30 minutes for large batch generation

    protected array $assessmentIds;
    protected int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $assessmentIds, int $userId)
    {
        $this->assessmentIds = $assessmentIds;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);
        if (!$user) {
            Log::error("User not found for GenerateBulkReassessmentReportsJob: ID {$this->userId}");
            return;
        }

        // Clean up old ZIP files (older than 24 hours) in bulk-reports
        $disk = Storage::disk('public');
        try {
            if ($disk->exists('bulk-reports')) {
                $files = $disk->files('bulk-reports');
                foreach ($files as $file) {
                    if ($disk->lastModified($file) < now()->subDay()->getTimestamp()) {
                        $disk->delete($file);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to clean up old ZIP files: " . $e->getMessage());
        }

        $assessments = Assessment::whereIn('id', $this->assessmentIds)->get();
        if ($assessments->isEmpty()) {
            Notification::make()
                ->title('Bulk Reassessment Download Failed')
                ->body('No matching assessments found.')
                ->danger()
                ->sendToDatabase($user);
            return;
        }

        $service = new ReassessmentReportService();
        $zipFilePath = null;

        try {
            $zipFilePath = $service->generateZipOfMultipleAssessments($assessments);
        } catch (\Throwable $e) {
            Log::error("Failed to generate ZIP of multiple assessments: " . $e->getMessage(), [
                'exception' => $e
            ]);
        }

        if (!$zipFilePath || !file_exists($zipFilePath)) {
            Notification::make()
                ->title('Bulk Reassessment Download Failed')
                ->body('Could not generate any of the reassessment reports.')
                ->danger()
                ->sendToDatabase($user);
            return;
        }

        // Save generated ZIP to public disk
        $filename = 'facial_reassessment_reports_' . now()->format('Ymd_His') . '_' . uniqid() . '.zip';
        $storagePath = 'bulk-reports/' . $filename;

        try {
            $disk->put($storagePath, fopen($zipFilePath, 'r+'));
            @unlink($zipFilePath);
        } catch (\Throwable $e) {
            @unlink($zipFilePath);
            Log::error("Failed to save ZIP to public storage: " . $e->getMessage());
            Notification::make()
                ->title('Bulk Reassessment Download Failed')
                ->body('Could not save reports ZIP archive to storage.')
                ->danger()
                ->sendToDatabase($user);
            return;
        }

        // Send download notification
        Notification::make()
            ->title('Bulk Reassessment ZIP Ready')
            ->success()
            ->body('The ZIP file containing ' . $assessments->count() . ' clients\' reports is ready.')
            ->actions([
                Action::make('download')
                    ->button()
                    ->url($disk->url($storagePath))
                    ->openUrlInNewTab(),
            ])
            ->sendToDatabase($user);
    }
}
