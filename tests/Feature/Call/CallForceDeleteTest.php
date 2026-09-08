<?php

declare(strict_types=1);

use App\Models\{Call, CallRecording, Clinic};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deleting a call permanently has to mean the audio too.
 *
 * The foreign key removes the call_recordings rows, but a cascade is a database
 * operation and knows nothing about files — the mp3 would stay on disk with
 * nothing pointing at it. For a recorded medical conversation that is not
 * untidiness: it is a deletion somebody was told had happened and had not.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('call_recordings');

    $this->clinic = Clinic::create([
        'name' => 'Jaipur', 'address_line1' => '1', 'city' => 'J', 'pincode' => '302001', 'is_active' => true,
    ]);

    $this->call = Call::create([
        'uuid' => (string) Str::uuid(),
        'clinic_id' => $this->clinic->getKey(),
        'provider' => 'exotel',
        'provider_call_id' => 'purge-1',
        'source' => 'webhook',
        'direction' => 'incoming',
        'call_status' => 'completed',
        'started_at' => now(),
    ]);

    Storage::disk('call_recordings')->put('calls/purge-1.mp3', 'audio-bytes');

    $this->recording = CallRecording::create([
        'call_id' => $this->call->getKey(),
        'provider' => 'exotel',
        'storage_status' => 'stored',
        'storage_disk' => 'call_recordings',
        'storage_path' => 'calls/purge-1.mp3',
    ]);
});

it('takes the audio off disk with a permanent delete', function (): void {
    expect(Storage::disk('call_recordings')->exists('calls/purge-1.mp3'))->toBeTrue();

    $this->call->forceDelete();

    expect(Storage::disk('call_recordings')->exists('calls/purge-1.mp3'))->toBeFalse()
        ->and(Call::withTrashed()->whereKey($this->call->getKey())->exists())->toBeFalse()
        ->and(CallRecording::whereKey($this->recording->getKey())->exists())->toBeFalse();
});

/**
 * A soft delete is reversible, so the audio has to survive it — otherwise
 * Restore returns a call whose recording is gone.
 */
it('leaves the audio alone on an ordinary delete', function (): void {
    $this->call->delete();

    expect(Storage::disk('call_recordings')->exists('calls/purge-1.mp3'))->toBeTrue();

    $this->call->restore();

    expect($this->call->fresh()->recordings()->count())->toBe(1);
});

/**
 * A file already gone, or a disk since reconfigured, must not leave the record
 * itself undeletable.
 */
it('still deletes the call when the file cannot be removed', function (): void {
    Storage::disk('call_recordings')->delete('calls/purge-1.mp3');

    $this->call->forceDelete();

    expect(Call::withTrashed()->whereKey($this->call->getKey())->exists())->toBeFalse();
});
