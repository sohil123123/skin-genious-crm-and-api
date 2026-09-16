<?php

namespace App\Filament\Resources\Assessments\Pages;

use App\Filament\Resources\Assessments\AssessmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAssessments extends ListRecords
{
    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make()->disabled(),

            // Action::make('download_all_clients_reassessment_zip')
            //     ->label('Download Reassessment Reports (Facial)')
            //     ->icon('heroicon-o-archive-box')
            //     ->color('success')
            //     ->tooltip('Download all available facial reassessment reports for filtered clients as a ZIP file')
            //     ->action(function ($livewire) {
            //         $query = $livewire->getFilteredTableQuery();

            //         // We only want normal and instant-normal assessments with completed reassessments (either direct post_diagnosis or completed treatment sessions with post_diagnosis)
            //         $assessments = $query->whereIn('assessment_type', ['normal', 'instant-normal'])
            //             ->where(function ($q) {
            //                 $q->whereNotNull('post_diagnosis')
            //                   ->orWhereHas('treatmentSessions', function ($sq) {
            //                       $sq->where('status', 'completed')
            //                         ->whereNotNull('post_diagnosis');
            //                   });
            //             })
            //             ->get();

            //         if ($assessments->isEmpty()) {
            //             \Filament\Notifications\Notification::make()
            //                 ->title('No completed reassessment sessions found for matching clients.')
            //                 ->warning()
            //                 ->send();
            //             return;
            //         }

            //         $assessmentIds = $assessments->pluck('id')->toArray();

            //         \App\Jobs\GenerateBulkReassessmentReportsJob::dispatch($assessmentIds, auth()->id());

            //         \Filament\Notifications\Notification::make()
            //             ->title('ZIP Generation Started')
            //             ->body('Generating reports for ' . count($assessmentIds) . ' clients in the background. You will receive a notification with a download link when ready.')
            //             ->success()
            //             ->send();
            //     }),

            // Moved up from the table's own header. It exports whatever the
            // filters currently select rather than the rows on screen, so it
            // belongs with the page's controls, not in the toolbar where every
            // neighbour acts on rows.
            Action::make('download_all_clients_treatment_plans_json_zip')
                ->label('Download Facial Treatment Plans (JSON)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->tooltip('Download all available facial treatment plans for filtered clients as a ZIP of JSON files')
                ->action(function ($livewire) {
                    $query = $livewire->getFilteredTableQuery();

                    // Filter for normal and instant-normal assessments
                    $assessments = $query->whereIn('assessment_type', ['normal', 'instant-normal'])->get();

                    if ($assessments->isEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('No facial assessments found for matching clients.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $assessmentIds = $assessments->pluck('id')->toArray();

                    \App\Jobs\GenerateBulkTreatmentPlansJsonJob::dispatch($assessmentIds, auth()->id());

                    \Filament\Notifications\Notification::make()
                        ->title('ZIP Generation Started')
                        ->body('Generating ZIP of treatment plans for ' . count($assessmentIds) . ' clients in the background. You will receive a notification with a download link when ready.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
