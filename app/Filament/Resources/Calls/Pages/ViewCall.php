<?php

declare(strict_types=1);

namespace App\Filament\Resources\Calls\Pages;

use App\Enums\Call\CallProvider;
use App\Enums\Call\RecordingDownloadStatus;
use App\Filament\Resources\Calls\Actions\AnalyseCallAction;
use App\Filament\Resources\Calls\Actions\TranscribeCallAction;
use App\Filament\Resources\Calls\CallResource;
use App\Jobs\Call\DownloadCallRecordingJob;
use App\Models\Call;
use App\Models\CallRecording;
use App\Services\Call\CallIngestionService;
use App\Services\Call\CallProviderManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * One call in full.
 *
 * The header actions are the recovery paths for the three things that routinely
 * go wrong on their own — a recording that was not ready when the webhook
 * fired, a transcription that failed transiently, and a customer the matcher
 * could not identify. Each is a retry a staff member can trigger without
 * needing anyone to run a command on a server.
 */
class ViewCall extends ViewRecord
{
    protected static string $resource = CallResource::class;

    public function getTitle(): string
    {
        /** @var Call $record */
        $record = $this->getRecord();

        return sprintf(
            '%s call with %s',
            $record->direction?->getLabel() ?? 'Call',
            $record->customer_name,
        );
    }

    public function getSubheading(): ?string
    {
        /** @var Call $record */
        $record = $this->getRecord();

        return $record->started_at
            ?->timezone(app_timezone())
            ->format(app_datetime_format());
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                $this->refreshFromProviderAction(),
                $this->retryRecordingAction(),
                TranscribeCallAction::make(),
                AnalyseCallAction::make(),
                $this->rematchAction(),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }

    /**
     * Ask the provider for this call again.
     *
     * The usual reason: Exotel finalises a recording a short while after the
     * call ends, so a flow that hangs up promptly delivers its last webhook
     * with no RecordingUrl. Without a pull, that call permanently has no audio
     * despite one existing.
     */
    protected function refreshFromProviderAction(): Action
    {
        return Action::make('refreshFromProvider')
            ->label('Refresh from provider')
            ->icon('heroicon-o-arrow-path')
            ->authorize(fn (): bool => auth()->user()->can('update', $this->getRecord()))
            ->requiresConfirmation()
            ->modalDescription('Fetches this call again from the provider. Anything the CRM recorded — the outcome, the note, a manual match — is left alone.')
            ->action(function (): void {
                /** @var Call $record */
                $record = $this->getRecord();

                $manager = app(CallProviderManager::class);

                try {
                    $refreshed = match ($record->provider) {
                        CallProvider::Exotel => $manager->get(CallProvider::Exotel)->refreshCall($record),
                        CallProvider::Callyzer => $manager->get(CallProvider::Callyzer)
                            ->refreshCalls([$record->provider_call_id]) > 0,
                        default => false,
                    };
                } catch (\Throwable $exception) {
                    Notification::make()
                        ->danger()
                        ->title('Could not refresh the call')
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                $refreshed
                    ? Notification::make()->success()->title('Call refreshed')->send()
                    : Notification::make()->warning()->title('The provider returned nothing new')->send();

                $this->refreshFormData([]);
            });
    }

    protected function retryRecordingAction(): Action
    {
        return Action::make('retryRecording')
            ->label('Retry download')
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => auth()->user()->can('update', $this->getRecord()))
            ->visible(fn (): bool => $this->getRecord()->recordings()->exists())
            ->action(function (): void {
                /** @var Call $record */
                $record = $this->getRecord();

                $queued = 0;

                $record->recordings()
                    ->get()
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
                    ? Notification::make()->success()->title(sprintf('Queued %d download(s)', $queued))->send()
                    : Notification::make()->info()->title('Every recording is already stored')->send();
            });
    }

    /**
     * Run automatic matching over this call again.
     *
     * Useful because the CRM changes underneath its calls: a caller who was a
     * stranger last week is a patient today. Skipped for a manual match, which
     * the resolver refuses to touch by design.
     */
    protected function rematchAction(): Action
    {
        return Action::make('rematch')
            ->label('Re-run customer matching')
            ->icon('heroicon-o-user-plus')
            ->authorize(fn (): bool => auth()->user()->can('matchCustomer', $this->getRecord()))
            ->visible(fn (): bool => $this->getRecord()->matching_status?->isHumanDecided() !== true)
            ->action(function (): void {
                /** @var Call $record */
                $record = $this->getRecord();

                $matched = app(CallIngestionService::class)
                    ->rematchUnmatched($record->client_phone_key, limit: 50);

                $record->refresh();

                $record->isMatched()
                    ? Notification::make()
                        ->success()
                        ->title('Customer found')
                        ->body(sprintf('%d call(s) with this number were attached.', max(1, $matched)))
                        ->send()
                    : Notification::make()
                        ->info()
                        ->title('Still no match')
                        ->body('No patient or lead in the CRM has this number.')
                        ->send();
            });
    }
}
