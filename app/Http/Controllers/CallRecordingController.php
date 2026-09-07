<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CallRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves call audio to authorised staff, and to nobody else.
 *
 * Recordings are the most sensitive thing this application stores — a patient
 * discussing a medical concern — so they live on a private disk and are never
 * given a permanent URL. Every request is authenticated, authorised against the
 * call's own policy, and streamed rather than redirected, so the provider URL
 * and the storage path never reach a browser at all.
 *
 * Streamed rather than read into memory because a long consultation is tens of
 * megabytes, and a handful of concurrent listeners reading whole files would
 * exhaust PHP's memory limit.
 */
class CallRecordingController extends Controller
{
    /**
     * Play one recording.
     *
     * Range requests are honoured so the browser's audio element can seek —
     * without them a player can only start from the beginning, which makes a
     * twenty-minute call effectively unreviewable.
     */
    public function stream(Request $request, CallRecording $recording): StreamedResponse
    {
        $call = $recording->call;

        abort_if($call === null, 404);

        // Authorised against the call, not the recording — access to a
        // conversation is decided by whose conversation it was — and against
        // playRecording rather than view, because hearing what a patient said
        // is a different grant from knowing that they rang.
        //
        // Checked explicitly rather than with authorize() so the refusal can be
        // written down. A bare 403 tells whoever is looking at it nothing: the
        // policy is a permission AND a clinic match, the browser cannot say
        // which failed, and the same page can work for one account and not
        // another. Every refusal now names the user, their roles, the clinic on
        // both sides, and which half said no — which is the difference between
        // reading one log line and bisecting a deployment.
        if (! Gate::allows('playRecording', $call)) {
            $user = $request->user();

            Log::channel('calls')->warning('Recording playback refused.', [
                'recording_id' => $recording->getKey(),
                'call_id' => $call->getKey(),
                'call_clinic_id' => $call->clinic_id,
                'user_id' => $user?->getKey(),
                'user_clinic_id' => $user?->clinic_id,
                'roles' => $user?->getRoleNames()->all() ?? [],
                'has_play_permission' => $user?->can('PlayRecording:Call') ?? false,
                'guard' => config('auth.defaults.guard'),
            ]);

            abort(403, 'You are not allowed to play this recording.');
        }

        abort_unless($recording->fileExists(), 404, 'The audio for this recording is not stored.');

        // diskName(), not storage_disk: a disk renamed in filesystems.php
        // after the audio was written would otherwise make Storage::disk()
        // throw, turning a config rename into a 500 on every player.
        $disk = Storage::disk($recording->diskName());
        $size = (int) ($recording->file_size ?: $disk->size($recording->storage_path));
        $mime = $recording->mime_type ?: 'audio/mpeg';

        [$start, $end] = $this->resolveRange($request, $size);
        $length = $end - $start + 1;
        $isPartial = $start > 0 || $end < $size - 1;

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) $length,
            'Accept-Ranges' => 'bytes',
            // Inline so it plays rather than downloads, but named so a
            // deliberate save produces a file that can be identified later.
            'Content-Disposition' => sprintf(
                'inline; filename="call-%s.%s"',
                $call->uuid,
                $recording->extension ?: 'mp3',
            ),
            // Recordings must not sit in a shared cache or a proxy.
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($isPartial) {
            $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $size);
        }

        return response()->stream(
            function () use ($disk, $recording, $start, $length): void {
                $stream = $disk->readStream($recording->storage_path);

                if ($stream === false || $stream === null) {
                    return;
                }

                if ($start > 0) {
                    fseek($stream, $start);
                }

                $remaining = $length;
                $chunkSize = 262144; // 256 KB

                while ($remaining > 0 && ! feof($stream)) {
                    $read = fread($stream, (int) min($chunkSize, $remaining));

                    if ($read === false) {
                        break;
                    }

                    echo $read;
                    flush();

                    $remaining -= strlen($read);
                }

                fclose($stream);
            },
            $isPartial ? 206 : 200,
            $headers,
        );
    }

    /**
     * Work out which bytes the client asked for.
     *
     * A malformed or unsatisfiable Range header falls back to the whole file
     * rather than erroring: browsers vary in what they send, and refusing a
     * header we did not understand would break playback for no benefit.
     *
     * @return array{0: int, 1: int}
     */
    protected function resolveRange(Request $request, int $size): array
    {
        $header = $request->header('Range');

        if (blank($header) || $size <= 0 || ! preg_match('/bytes=(\d*)-(\d*)/', (string) $header, $matches)) {
            return [0, max(0, $size - 1)];
        }

        $start = $matches[1] !== '' ? (int) $matches[1] : 0;
        $end = $matches[2] !== '' ? (int) $matches[2] : $size - 1;

        $start = max(0, min($start, $size - 1));
        $end = max($start, min($end, $size - 1));

        return [$start, $end];
    }
}
