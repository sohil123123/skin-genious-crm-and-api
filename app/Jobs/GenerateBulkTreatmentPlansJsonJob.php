<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Assessment;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateBulkTreatmentPlansJsonJob implements ShouldQueue
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
            Log::error("User not found for GenerateBulkTreatmentPlansJsonJob: ID {$this->userId}");
            return;
        }

        // Clean up old ZIP files (older than 24 hours) in bulk-reports
        $publicDisk = Storage::disk('public');
        try {
            if ($publicDisk->exists('bulk-reports')) {
                $files = $publicDisk->files('bulk-reports');
                foreach ($files as $file) {
                    if ($publicDisk->lastModified($file) < now()->subDay()->getTimestamp()) {
                        $publicDisk->delete($file);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to clean up old ZIP files: " . $e->getMessage());
        }

        $assessments = Assessment::whereIn('id', $this->assessmentIds)->get();
        if ($assessments->isEmpty()) {
            Notification::make()
                ->title('Bulk Treatment Plans Download Failed')
                ->body('No matching assessments found.')
                ->danger()
                ->sendToDatabase($user);
            return;
        }

        $filesDisk = Storage::disk('files');
        $zip = new \ZipArchive();
        $tempFile = tempnam(sys_get_temp_dir(), 'treatment_plan_json_zip');

        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            Log::error("Failed to create temp zip file for treatment plans bulk download");
            Notification::make()
                ->title('Bulk Treatment Plans Download Failed')
                ->body('Could not create ZIP archive.')
                ->danger()
                ->sendToDatabase($user);
            return;
        }

        $hasFiles = false;

        foreach ($assessments as $record) {
            $filePath = "treatment-plans/treatment_plans_#{$record->id}.json";
            $patientName = $record->user ? str_replace([' ', '/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', strtolower($record->user->name)) : 'patient';
            $filename = $patientName . '_assessment_' . $record->id . '_treatment_plan.json';

            if ($filesDisk->exists($filePath)) {
                try {
                    $jsonContent = $filesDisk->get($filePath);
                    $zip->addFromString($filename, $jsonContent);
                    $hasFiles = true;
                } catch (\Throwable $e) {
                    Log::error("Failed to read/add treatment plan JSON for assessment #{$record->id}: " . $e->getMessage());
                }
            } else {
                try {
                    $treatments = [];
                    if (!empty($record->treatment_sessions['treatments'])) {
                        $treatments = $record->treatment_sessions['treatments'];
                    }

                    $data = [
                        'treatment_plans' => [
                            'total_time' => $record->total_time,
                        ],
                        'treatment_plan' => [
                            'treatments' => $treatments,
                        ],
                        'recommended_full_plan' => $record->recommended_full_plan,
                    ];

                    $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    $zip->addFromString($filename, $jsonContent);
                    $hasFiles = true;
                } catch (\Throwable $e) {
                    Log::error("Failed to dynamically generate treatment plan JSON for assessment #{$record->id}: " . $e->getMessage());
                }
            }
        }

        $zip->close();

        if (!$hasFiles) {
            @unlink($tempFile);
            Notification::make()
                ->title('Bulk Treatment Plans Download Failed')
                ->body('No treatment plan JSON files found in storage for matching assessments.')
                ->danger()
                ->sendToDatabase($user);
            return;
        }

        // Save generated ZIP to public disk
        $zipFilename = 'facial_treatment_plans_' . now()->format('Ymd_His') . '_' . uniqid() . '.zip';
        $storagePath = 'bulk-reports/' . $zipFilename;

        try {
            $publicDisk->put($storagePath, fopen($tempFile, 'r+'));
            @unlink($tempFile);
        } catch (\Throwable $e) {
            @unlink($tempFile);
            Log::error("Failed to save treatment plans ZIP to public storage: " . $e->getMessage());
            Notification::make()
                ->title('Bulk Treatment Plans Download Failed')
                ->body('Could not save ZIP archive to public storage.')
                ->danger()
                ->sendToDatabase($user);
            return;
        }

        // Send download notification
        Notification::make()
            ->title('Bulk Treatment Plans JSON Ready')
            ->success()
            ->body('The ZIP file containing ' . $assessments->count() . ' clients\' treatment plans is ready.')
            ->actions([
                Action::make('download')
                    ->button()
                    ->url($publicDisk->url($storagePath))
                    ->openUrlInNewTab(),
            ])
            ->sendToDatabase($user);
    }
}
