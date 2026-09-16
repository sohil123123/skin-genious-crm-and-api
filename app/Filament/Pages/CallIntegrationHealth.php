<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Call\CallEventProcessingStatus;
use App\Enums\Call\CallProvider;
use App\Enums\Call\RecordingDownloadStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use App\Models\Call;
use App\Models\CallRecording;
use App\Models\CallSyncRun;
use App\Models\CallWebhookEvent;
use App\Services\Call\CallProviderManager;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Is the call plumbing actually working?
 *
 * This page exists because of one specific failure mode: an integration that
 * silently stops is indistinguishable from a quiet week. No errors, no alerts,
 * just calls that never arrive — and it is typically noticed a month later by
 * someone wondering why a patient's history has a gap in it.
 *
 * So the headline figure per provider is when a call was last seen, not how
 * many there were. A "last webhook: 6 days ago" line is the one thing that
 * makes a dead integration obvious at a glance.
 */
class CallIntegrationHealth extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-heart';

    protected static string|\UnitEnum|null $navigationGroup = 'Calls';

    protected static ?string $title = 'Call Integration Health';

    protected static ?string $navigationLabel = 'Integration Health';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.call-integration-health';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Refreshed on a timer because this is a page people leave open while
     * waiting for a sync they just started to finish.
     */
    protected static ?string $pollingInterval = '30s';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Call::class) ?? false;
    }

    /**
     * Per-provider status.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getProviderHealth(): array
    {
        $manager = app(CallProviderManager::class);

        return collect([CallProvider::Exotel, CallProvider::Callyzer])
            ->map(function (CallProvider $provider) use ($manager): array {
                $adapter = $manager->get($provider);

                $lastEvent = CallWebhookEvent::query()
                    ->ofProvider($provider)
                    ->latest('received_at')
                    ->first();

                $lastProcessed = CallWebhookEvent::query()
                    ->ofProvider($provider)
                    ->where('processing_status', CallEventProcessingStatus::Processed->value)
                    ->latest('processed_at')
                    ->first();

                $lastSuccessfulSync = CallSyncRun::lastSuccessful($provider);
                $lastFailedSync = CallSyncRun::lastFailed($provider);

                $failedEvents = CallWebhookEvent::query()
                    ->ofProvider($provider)
                    ->failed()
                    ->where('received_at', '>=', now()->subDays(7))
                    ->count();

                return [
                    'provider' => $provider,
                    'label' => $provider->getLabel(),
                    'enabled' => $adapter->isEnabled(),
                    'calls_total' => Call::query()->ofProvider($provider)->count(),
                    'calls_7d' => Call::query()
                        ->ofProvider($provider)
                        ->where('started_at', '>=', now()->subDays(7))
                        ->count(),
                    'last_event_at' => $lastEvent?->received_at,
                    'last_processed_at' => $lastProcessed?->processed_at,
                    'failed_events_7d' => $failedEvents,
                    'duplicates_7d' => (int) CallWebhookEvent::query()
                        ->ofProvider($provider)
                        ->where('received_at', '>=', now()->subDays(7))
                        ->sum('duplicate_count'),
                    'last_error' => $lastEvent?->error_message ?? $lastFailedSync?->last_error,
                    'last_successful_sync' => $lastSuccessfulSync,
                    'last_failed_sync' => $lastFailedSync,
                    'sync_running' => CallSyncRun::isRunning($provider),
                    // The judgement the page exists to make, in one place.
                    'status' => $this->judge($adapter->isEnabled(), $lastEvent?->received_at, $failedEvents),
                ];
            })
            ->all();
    }

    /**
     * Reduce a provider's signals to one word.
     *
     * "Quiet" is deliberately distinct from "healthy": an enabled integration
     * that has heard nothing for two days is not obviously broken, but it is
     * the thing worth looking at, and calling it healthy would defeat the point
     * of the page.
     */
    protected function judge(bool $enabled, ?Carbon $lastEvent, int $failedEvents): string
    {
        if (! $enabled) {
            return 'disabled';
        }

        if ($lastEvent === null) {
            return 'waiting';
        }

        if ($failedEvents > 0) {
            return 'errors';
        }

        return $lastEvent->lt(now()->subDays(2)) ? 'quiet' : 'healthy';
    }

    /**
     * Pipeline counts for recordings and transcripts.
     *
     * @return array<string, array<string, int>>
     */
    public function getPipelineHealth(): array
    {
        $recordings = CallRecording::query()
            ->selectRaw('download_status, COUNT(*) as total')
            ->groupBy('download_status')
            ->pluck('total', 'download_status');

        $storage = CallRecording::query()
            ->selectRaw('storage_status, COUNT(*) as total')
            ->groupBy('storage_status')
            ->pluck('total', 'storage_status');

        $transcription = CallRecording::query()
            ->selectRaw('transcription_status, COUNT(*) as total')
            ->groupBy('transcription_status')
            ->pluck('total', 'transcription_status');

        return [
            'download' => [
                'pending' => (int) ($recordings[RecordingDownloadStatus::Pending->value] ?? 0),
                'downloading' => (int) ($recordings[RecordingDownloadStatus::Downloading->value] ?? 0),
                'downloaded' => (int) ($recordings[RecordingDownloadStatus::Downloaded->value] ?? 0),
                'failed' => (int) ($recordings[RecordingDownloadStatus::Failed->value] ?? 0),
                'skipped' => (int) ($recordings[RecordingDownloadStatus::Skipped->value] ?? 0),
            ],
            'storage' => [
                // The number that matters: audio the clinic does not own yet,
                // sitting on a provider's server with an expiry date.
                'remote_only' => (int) ($storage[RecordingStorageStatus::RemoteOnly->value] ?? 0),
                'stored' => (int) ($storage[RecordingStorageStatus::Stored->value] ?? 0),
                'failed' => (int) ($storage[RecordingStorageStatus::Failed->value] ?? 0),
                'purged' => (int) ($storage[RecordingStorageStatus::Purged->value] ?? 0),
            ],
            'transcription' => [
                'pending' => (int) ($transcription[TranscriptionStatus::Pending->value] ?? 0),
                'processing' => (int) ($transcription[TranscriptionStatus::Processing->value] ?? 0),
                'completed' => (int) ($transcription[TranscriptionStatus::Completed->value] ?? 0),
                'failed' => (int) ($transcription[TranscriptionStatus::Failed->value] ?? 0),
                'not_available' => (int) ($transcription[TranscriptionStatus::NotAvailable->value] ?? 0),
            ],
        ];
    }

    /**
     * The last few sync runs, whatever their outcome.
     *
     * @return Collection<int, CallSyncRun>
     */
    public function getRecentSyncRuns(): Collection
    {
        return CallSyncRun::query()
            ->with('triggeredBy:id,first_name,last_name')
            ->latest('started_at')
            ->limit(10)
            ->get();
    }

    /**
     * Events that failed and were never reprocessed.
     *
     * Listed rather than merely counted, because each one is a call the CRM
     * does not have and the payload is still on disk to replay.
     *
     * @return Collection<int, CallWebhookEvent>
     */
    public function getFailedEvents(): Collection
    {
        return CallWebhookEvent::query()
            ->failed()
            ->latest('received_at')
            ->limit(10)
            ->get();
    }
}
