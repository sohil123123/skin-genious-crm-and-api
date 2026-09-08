<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Actions;

use App\Enums\Call\CallAnalysisStatus;
use App\Jobs\Call\AnalyzeCallJob;
use App\Models\Call;
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
use App\Services\Call\Transcription\TranscriptWordCounter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * "Analyse this one call now", wherever someone happens to be standing.
 *
 * It appears on three surfaces - the call's Actions menu, the row menu in the
 * list, and the button inside the empty AI analysis section - because each one
 * is where a different person gives up looking. Defined once here rather than
 * three times: the guards below are the interesting part, and three copies of a
 * guard is three chances for one of them to drift into dispatching work the
 * others refuse.
 *
 * Every surface passes the record through Filament's own injection, so the same
 * closure works as a page action, a table row action, and a schema action.
 */
final class AnalyseCallAction
{
    public static function make(string $name = 'analyse'): Action
    {
        return Action::make($name)
            ->label(fn (?Call $record): string => $record?->currentAnalysis !== null
                ? 'Analyse again'
                : 'Analyse with AI')
            ->icon('heroicon-o-sparkles')
            ->authorize(fn (?Call $record): bool => $record !== null
                && (auth()->user()?->can('update', $record) ?? false))
            // Nothing to read means nothing to analyse. Offering the button
            // anyway would produce a job that immediately gives up.
            ->visible(fn (?Call $record): bool => $record?->hasTranscript() ?? false)
            ->requiresConfirmation()
            ->modalHeading('Analyse this call')
            ->modalDescription(fn (?Call $record): string => $record?->currentAnalysis !== null
                ? 'Runs the analysis again. The current one is kept as history and the new one replaces it on this page.'
                : 'Reads the transcript and extracts intent, sentiment, objection and a suggested next step.')
            ->modalSubmitActionLabel('Analyse')
            ->action(function (Call $record): void {
                $analyser = app(CallAnalysisServiceInterface::class);

                if (! $analyser->isEnabled()) {
                    Notification::make()
                        ->warning()
                        ->title('Analysis is switched off')
                        ->body('Set an analysis provider under Calls → Call Settings first.')
                        ->send();

                    return;
                }

                // Refused here rather than in the worker. The driver already
                // declines a transcript this short, but it does so silently and
                // settles the call as "not available" — so pressing the button
                // appeared to work, and the panel then showed the same blank
                // section as before with no explanation anywhere.
                $words = TranscriptWordCounter::count($record->currentTranscription?->transcript);
                $minimum = $analyser->minimumWords();

                if ($words < $minimum) {
                    Notification::make()
                        ->warning()
                        ->title('Too short to analyse')
                        ->body(sprintf(
                            'This transcript is %d word%s and the minimum is %d. Lower it under Calls → Call Settings → Transcription and AI if these calls are worth reading.',
                            $words,
                            $words === 1 ? '' : 's',
                            $minimum,
                        ))
                        ->persistent()
                        ->send();

                    return;
                }

                // Reset from a settled status, or the job returns immediately -
                // a manual press is an explicit instruction to run it again.
                $record->forceFill([
                    'analysis_status' => CallAnalysisStatus::Pending,
                    'last_error' => null,
                ])->saveQuietly();

                AnalyzeCallJob::dispatch($record->getKey());

                Notification::make()
                    ->success()
                    ->title('Analysis queued')
                    ->body('It appears in the AI analysis section once the worker picks it up.')
                    ->send();
            });
    }
}
