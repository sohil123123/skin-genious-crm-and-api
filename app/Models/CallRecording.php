<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Call\CallProvider;
use App\Enums\Call\RecordingDownloadStatus;
use App\Enums\Call\RecordingStorageStatus;
use App\Enums\Call\TranscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The audio of a call, and the state of getting hold of it.
 *
 * Recordings are the most sensitive thing this system stores — a patient
 * discussing a medical concern — so nothing here ever produces a public URL.
 * Playback goes through a signed route that checks the call policy first, and
 * the disk defaults to the private one.
 */
class CallRecording extends Model
{
    protected $fillable = [
        'call_id',
        'provider',
        'provider_recording_id',
        'source_url',
        'source_url_hash',
        'storage_disk',
        'storage_path',
        'original_filename',
        'mime_type',
        'extension',
        'file_size',
        'duration_seconds',
        'checksum',
        'download_status',
        'download_attempts',
        'download_started_at',
        'downloaded_at',
        'storage_status',
        'transcription_status',
        'transcription_attempts',
        'transcription_started_at',
        'transcription_completed_at',
        'error_message',
        'metadata',
        'purge_after',
        'purged_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CallProvider::class,
            'download_status' => RecordingDownloadStatus::class,
            'storage_status' => RecordingStorageStatus::class,
            'transcription_status' => TranscriptionStatus::class,
            'metadata' => 'array',
            'download_started_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'transcription_started_at' => 'datetime',
            'transcription_completed_at' => 'datetime',
            'purge_after' => 'datetime',
            'purged_at' => 'datetime',
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
            'download_attempts' => 'integer',
            'transcription_attempts' => 'integer',
        ];
    }

    /**
     * The provider URL is excluded from serialisation.
     *
     * Some providers embed a signed token in it, so a recording URL in a JSON
     * response is a credential leak, not a convenience.
     */
    protected $hidden = [
        'source_url',
    ];

    // ──────────────── Relationships ────────────────

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function transcriptions(): HasMany
    {
        return $this->hasMany(CallTranscription::class);
    }

    // ──────────────── Scopes ────────────────

    public function scopeStored(Builder $query): Builder
    {
        return $query->where('storage_status', RecordingStorageStatus::Stored->value);
    }

    public function scopePendingDownload(Builder $query): Builder
    {
        return $query->whereIn('download_status', [
            RecordingDownloadStatus::Pending->value,
            RecordingDownloadStatus::Failed->value,
        ]);
    }

    public function scopeAwaitingTranscription(Builder $query): Builder
    {
        return $query->where('storage_status', RecordingStorageStatus::Stored->value)
            ->where('transcription_status', TranscriptionStatus::Pending->value);
    }

    /**
     * Recordings whose retention date has passed and whose audio still exists.
     */
    public function scopeDuePurge(Builder $query): Builder
    {
        return $query->whereNotNull('purge_after')
            ->where('purge_after', '<=', now())
            ->where('storage_status', RecordingStorageStatus::Stored->value);
    }

    // ──────────────── State ────────────────

    public function isStored(): bool
    {
        return $this->storage_status === RecordingStorageStatus::Stored
            && filled($this->storage_path);
    }

    /**
     * Disk names this process has already reported as missing.
     *
     * A stale disk on a table of fifty rows would otherwise write fifty
     * identical warnings for one configuration problem.
     *
     * @var array<string, true>
     */
    protected static array $reportedMissingDisks = [];

    /**
     * The disk to actually read this recording from.
     *
     * Normally the one recorded at download time. But a disk can be renamed in
     * filesystems.php long after the audio was written — which happened here,
     * `call_recording` becoming `call_recordings` — and Storage::disk() throws
     * on a name it does not know rather than returning nothing. That turned a
     * cosmetic config change into a 500 on every player.
     *
     * The files are almost always still there under the same path, so this
     * falls back to the configured disk rather than losing them, and warns once
     * so somebody notices rather than relying on the fallback forever. The
     * repair is a one-line update of storage_disk on the affected rows; there
     * was a command for it, removed as part of a console tidy-up.
     */
    public function diskName(): ?string
    {
        if (blank($this->storage_disk)) {
            return null;
        }

        $disks = (array) config('filesystems.disks', []);

        if (array_key_exists($this->storage_disk, $disks)) {
            return $this->storage_disk;
        }

        if (! isset(static::$reportedMissingDisks[$this->storage_disk])) {
            static::$reportedMissingDisks[$this->storage_disk] = true;

            Log::channel('calls')->warning('Call recordings point at a disk that is no longer configured.', [
                'stored_disk' => $this->storage_disk,
                'falling_back_to' => (string) config('calls.recording.disk'),
                'fix' => 'Point these rows at the configured disk, or restore the old disk name in filesystems.php.',
            ]);
        }

        $configured = (string) Setting::getConfigured(
            'call_recording_disk',
            config('calls.recording.disk', 'local'),
        );

        return array_key_exists($configured, $disks) ? $configured : null;
    }

    /**
     * Whether the audio is actually readable, not merely recorded as stored.
     *
     * A file removed from disk outside the application leaves the row saying
     * Stored, and the player must not offer a link to nothing. Never throws:
     * an unreadable recording is a missing recording, not a broken page.
     */
    public function fileExists(): bool
    {
        if (! $this->isStored()) {
            return false;
        }

        $disk = $this->diskName();

        if ($disk === null) {
            return false;
        }

        try {
            return Storage::disk($disk)->exists($this->storage_path);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * A short-lived URL for playing this recording.
     *
     * Deliberately not a permanent link. Returns null rather than falling back
     * to the provider URL: that URL may carry a token, and handing it to a
     * browser would leak it into history and referrer headers.
     */
    public function temporaryUrl(): ?string
    {
        if (! $this->fileExists()) {
            return null;
        }

        $disk = Storage::disk($this->diskName());
        $minutes = (int) config('calls.recording.signed_url_ttl', 10);

        try {
            return $disk->temporaryUrl($this->storage_path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            // The local driver has no temporary URLs unless "serve" is on.
            // The caller falls back to the streaming route in that case.
            return null;
        }
    }

    public function markDownloading(): void
    {
        $this->forceFill([
            'download_status' => RecordingDownloadStatus::Downloading,
            'storage_status' => RecordingStorageStatus::Downloading,
            'download_attempts' => $this->download_attempts + 1,
            'download_started_at' => now(),
        ])->save();
    }

    public function markDownloaded(array $attributes): void
    {
        $this->forceFill(array_merge($attributes, [
            'download_status' => RecordingDownloadStatus::Downloaded,
            'storage_status' => RecordingStorageStatus::Stored,
            'downloaded_at' => now(),
            'error_message' => null,
        ]))->save();
    }

    public function markDownloadFailed(string $message): void
    {
        $this->forceFill([
            'download_status' => RecordingDownloadStatus::Failed,
            'storage_status' => RecordingStorageStatus::RemoteOnly,
            // A provider error body can be far longer than anything readable.
            'error_message' => mb_substr($message, 0, 1000),
        ])->save();
    }

    /**
     * There will never be audio here, and nothing should keep trying.
     */
    public function markSkipped(string $reason): void
    {
        $this->forceFill([
            'download_status' => RecordingDownloadStatus::Skipped,
            'transcription_status' => TranscriptionStatus::NotAvailable,
            'error_message' => mb_substr($reason, 0, 1000),
        ])->save();
    }
}
