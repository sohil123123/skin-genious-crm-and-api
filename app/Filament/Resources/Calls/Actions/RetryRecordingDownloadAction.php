<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Actions;

use App\Enums\Call\RecordingDownloadStatus;
use App\Jobs\Call\DownloadCallRecordingJob;
use App\Models\Call;
use App\Models\CallRecording;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Fetch audio the provider published but this server never stored.
 *
 * The common case is a provider that announces a recording before the file
 * behind it exists: the first download 404s, the recording settles as failed,
 * and nothing revisits it. The other is a server that has never run the
 * download worker, where every recording is a row with no file.
 *
 * Offered on the list as well as the call page, because "Unavailable" in the
 * Recording column is exactly where somebody notices, and the fix should be
 * available from there.
 */
final class RetryRecordingDownloadAction
{
    public static function make(string $name = 'retryRecording'): Action
    {
        return Action::make($name)
            ->label('Retry download')
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (?Call $record): bool => $record !== null
                && (auth()->user()?->can('update', $record) ?? false))
            // No recordings means nothing to fetch. Offering it anyway would
            // queue nothing and report success.
            ->visible(fn (?Call $record): bool => $record?->recordings()->exists() ?? false)
            ->action(function (Call $record): void {
                $queued = 0;

                $record->recordings()
                    ->get()
                    // Only what is actually absent. Re-downloading a stored
                    // file costs bandwidth and gains nothing.
                    ->reject(fn (CallRecording $recording): bool => $recording->fileExists())
                    ->each(function (CallRecording $recording) use (&$queued): void {
                        // Reset from a settled state so the job does not
                        // immediately return: a manual retry is an explicit
                        // instruction to try a recording that was given up on.
                        $recording->forceFill([
                            'download_status' => RecordingDownloadStatus::Pending,
                            'error_message' => null,
                        ])->save();

                        DownloadCallRecordingJob::dispatch($recording->getKey());
                        $queued++;
                    });

                $queued > 0
                    ? Notification::make()
                        ->success()
                        ->title(sprintf('Queued %d download(s)', $queued))
                        ->body('A worker on the call-recordings queue has to be running for these to arrive.')
                        ->send()
                    : Notification::make()->info()->title('Every recording is already stored')->send();
            });
    }
}
