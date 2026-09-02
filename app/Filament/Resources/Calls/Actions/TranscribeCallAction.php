<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Actions;

use App\Enums\Call\TranscriptionStatus;
use App\Jobs\Call\TranscribeCallRecordingJob;
use App\Models\Call;
use App\Models\CallRecording;
use App\Services\Call\Contracts\CallTranscriptionServiceInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * "Turn this call's audio into text", wherever someone is standing.
 *
 * The sibling of AnalyseCallAction, and defined once for the same reason: the
 * guards are the interesting part. Only stored audio can be transcribed - a
 * recording the provider published but the CRM has not downloaded yet has
 * nothing on disk to send - so the button is offered only when a file actually
 * exists, rather than queueing work that fails on pickup.
 */
final class TranscribeCallAction
{
    public static function make(string $name = 'transcribe'): Action
    {
        return Action::make($name)
            ->label(fn (?Call $record): string => $record?->hasTranscript() === true
                ? 'Transcribe again'
                : 'Transcribe with AI')
            ->icon('heroicon-o-document-text')
            ->authorize(fn (?Call $record): bool => $record !== null
                && (auth()->user()?->can('update', $record) ?? false))
            ->visible(fn (?Call $record): bool => $record?->recordings()->exists() ?? false)
            ->requiresConfirmation()
            ->modalHeading('Transcribe this call')
            ->modalDescription(fn (?Call $record): string => $record?->hasTranscript() === true
                ? 'Runs transcription again. The existing transcript is kept as history and the new one becomes current.'
                : 'Sends the stored audio for transcription. The text appears here once the worker finishes.')
            ->modalSubmitActionLabel('Transcribe')
            ->action(function (Call $record): void {
                if (! app(CallTranscriptionServiceInterface::class)->isEnabled()) {
                    Notification::make()
                        ->warning()
                        ->title('Transcription is switched off')
                        ->body('Set a transcription provider under Calls → Call Settings first.')
                        ->send();

                    return;
                }

                $queued = 0;

                $record->recordings()
                    ->get()
                    // Stored on disk, not merely known about. Without this the
                    // job is dispatched for a file that was never downloaded.
                    ->filter(fn (CallRecording $recording): bool => $recording->fileExists())
                    ->each(function (CallRecording $recording) use (&$queued): void {
                        $recording->forceFill([
                            'transcription_status' => TranscriptionStatus::Pending,
                            'error_message' => null,
                        ])->save();

                        TranscribeCallRecordingJob::dispatch($recording->getKey());
                        $queued++;
                    });

                $queued > 0
                    ? Notification::make()
                        ->success()
                        ->title(sprintf('Queued %d transcription(s)', $queued))
                        ->body('The text appears in the Transcript section once the worker finishes.')
                        ->send()
                    : Notification::make()
                        ->warning()
                        ->title('No stored audio to transcribe')
                        ->body('The recording has not been downloaded yet. Use Actions → Retry recording download first.')
                        ->send();
            });
    }
}
