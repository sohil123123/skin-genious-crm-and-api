<?php

declare(strict_types=1);

namespace App\Services\Call\Transcription;

use App\DTOs\Call\TranscriptionResult;
use App\DTOs\Call\TranscriptSegment;
use App\Models\CallRecording;
use App\Models\Setting;
use App\Services\Call\Contracts\CallTranscriptionServiceInterface;
use App\Services\Call\Exceptions\CallTranscriptionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Transcribes call recordings with OpenAI's audio transcription endpoint.
 *
 * Chosen as the first real driver because clinic calls here are a mix of
 * Gujarati, Hindi and English — often inside one sentence — and a multilingual
 * model handles that far better than a per-language service would. The language
 * is therefore left unset by default: pinning it to one language would make the
 * model mis-transcribe the other two.
 *
 * The audio is read from the private disk rather than passed as a URL, which is
 * what keeps recordings from needing a publicly reachable address.
 */
class OpenAiTranscriptionService implements CallTranscriptionServiceInterface
{
    /**
     * HTTP statuses that will fail identically however many times they are
     * retried: a rejected key, a file the model will not accept, a request that
     * is malformed. Retrying these burns the job's attempts and delays every
     * other recording in the queue behind them.
     */
    protected const PERMANENT_STATUSES = [400, 401, 403, 404, 413, 415, 422];

    public function isEnabled(): bool
    {
        return (bool) Setting::getValue('call_transcription_enabled', config('calls.transcription.enabled', false))
            && filled($this->apiKey());
    }

    public function name(): string
    {
        return 'openai';
    }

    public function transcribe(CallRecording $recording, ?string $language = null): ?TranscriptionResult
    {
        if (! $recording->fileExists()) {
            // Nothing to send. Permanent rather than transient: the audio is
            // not going to reappear on its own.
            return null;
        }

        $minimum = (int) config('calls.transcription.min_duration_seconds', 5);

        if ($recording->duration_seconds !== null && $recording->duration_seconds < $minimum) {
            // A three-second recording is a ring-out or a wrong number. Sending
            // it costs money and returns nothing worth reading.
            return null;
        }

        $disk = Storage::disk($recording->diskName());
        $model = (string) Setting::getConfigured('call_transcription_model', config('calls.transcription.model', 'whisper-1'));
        $language ??= Setting::getConfigured('call_transcription_language', config('calls.transcription.language'));

        // A short piece of sample text in the expected style. The single most
        // effective accuracy control this endpoint offers, and the fix for the
        // problem that prompted it: Hindi and Urdu are the same spoken language
        // written in different scripts, so Whisper flips between them freely
        // and can render a Hindi call in Nastaliq. A prompt written in the
        // script you want anchors the output to it, and doubles as a place to
        // teach it treatment names it would otherwise mangle.
        $prompt = Setting::getConfigured('call_transcription_prompt', config('calls.transcription.prompt'));

        $stream = $disk->readStream($recording->storage_path);

        if ($stream === false || $stream === null) {
            return null;
        }

        try {
            $request = Http::withToken((string) $this->apiKey())
                ->timeout((int) config('calls.transcription.timeout', 600))
                ->attach(
                    'file',
                    $stream,
                    basename((string) $recording->storage_path),
                );

            $payload = array_filter([
                'model' => $model,
                'language' => $language,
                'prompt' => $prompt,
                // verbose_json is what carries the segment timings; without it
                // the transcript is one undifferentiated block and the segment
                // table stays empty. Only whisper-1 supports it — the gpt-4o
                // transcription models accept json or text only, and asking
                // them for verbose_json is a 400 — so the format follows the
                // model rather than being assumed.
                'response_format' => static::supportsSegments($model) ? 'verbose_json' : 'json',
                // Deliberately NOT pinned to 0. Greedy decoding is what makes
                // these models fall into a repetition loop — the same sentence
                // emitted a hundred times — and sending an explicit temperature
                // suppresses the provider's own fallback, which exists to
                // detect that degeneration and retry hotter until it breaks
                // out. Reproducibility is not worth a transcript that repeats
                // one line for three minutes.
                'temperature' => Setting::getConfigured('call_transcription_temperature', null),
            ], static fn (mixed $value): bool => filled($value));

            $response = $request->post(
                rtrim((string) config('calls.transcription.drivers.openai.base_url'), '/') . '/audio/transcriptions',
                $payload,
            );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($response->failed()) {
            $message = sprintf(
                'Transcription failed with HTTP %d: %s',
                $response->status(),
                mb_substr($response->body(), 0, 500),
            );

            if (in_array($response->status(), self::PERMANENT_STATUSES, true)) {
                Log::channel('calls')->error('Transcription rejected permanently.', [
                    'recording_id' => $recording->getKey(),
                    'status' => $response->status(),
                ]);

                return null;
            }

            // Transient — a 429 or a 5xx. Thrown so the job's backoff applies
            // rather than being swallowed as "cannot be transcribed".
            throw new CallTranscriptionException($message);
        }

        $body = (array) $response->json();
        $text = trim((string) ($body['text'] ?? ''));

        if ($text === '') {
            return null;
        }

        [$text, $warnings] = static::collapseRepetition($text);

        if ($warnings !== []) {
            Log::channel('calls')->warning('Transcription came back with a repetition loop.', [
                'recording_id' => $recording->getKey(),
                'model' => $model,
                'detail' => $warnings,
            ]);
        }

        return new TranscriptionResult(
            transcript: $text,
            language: $body['language'] ?? null,
            languageCode: $this->languageCode($body['language'] ?? null),
            confidence: null,
            durationSeconds: isset($body['duration']) ? (int) round((float) $body['duration']) : null,
            model: $model,
            segments: $this->mapSegments((array) ($body['segments'] ?? [])),
            raw: $body,
            warnings: $warnings,
        );
    }

    /**
     * Turn the response segments into the CRM's shape.
     *
     * No speaker is assigned. This endpoint does not diarise, so every segment
     * is Unknown — which is honest, and better than alternating agent and
     * customer on the assumption that people take turns politely.
     *
     * @param  array<int, array<string, mixed>>  $segments
     * @return array<int, TranscriptSegment>
     */
    protected function mapSegments(array $segments): array
    {
        $mapped = [];

        foreach ($segments as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $mapped[] = new TranscriptSegment(
                text: $text,
                startSeconds: isset($segment['start']) ? (float) $segment['start'] : null,
                endSeconds: isset($segment['end']) ? (float) $segment['end'] : null,
                speaker: null,
                confidence: isset($segment['avg_logprob'])
                    // avg_logprob is a log probability, not a 0-1 confidence.
                    // Converting it makes the column comparable across models
                    // and clamps the result so a very negative value cannot
                    // produce a nonsensical figure.
                    ? max(0.0, min(1.0, exp((float) $segment['avg_logprob'])))
                    : null,
                metadata: array_filter([
                    'no_speech_prob' => $segment['no_speech_prob'] ?? null,
                    'temperature' => $segment['temperature'] ?? null,
                ], static fn (mixed $value): bool => $value !== null),
            );
        }

        return $mapped;
    }

    /**
     * Collapse a repetition loop down to one instance of the repeated line.
     *
     * Speech models occasionally get stuck emitting the same sentence over and
     * over — a known failure of autoregressive decoding, most likely on long or
     * quiet audio. The result is a transcript that looks complete, carries a
     * plausible word count, and is unreadable.
     *
     * Only runs of three or more *consecutive* identical sentences are touched.
     * A person really does repeat themselves on a phone call ("hello? hello?"),
     * and twice is normal speech; twenty times is not something a human said.
     *
     * The untouched provider response is still stored in transcript_json, so
     * nothing is destroyed by this — the original is always recoverable.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function collapseRepetition(string $text): array
    {
        // Danda for Devanagari and Gujarati, plus Latin sentence enders.
        $sentences = preg_split('/(?<=[।\.\?\!])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($sentences === false || count($sentences) < 3) {
            return [$text, []];
        }

        $kept = [];
        $removed = 0;
        $longestRun = 0;
        $run = 1;

        foreach ($sentences as $index => $sentence) {
            $current = static::normaliseForComparison($sentence);
            $previous = $index > 0 ? static::normaliseForComparison($sentences[$index - 1]) : null;

            if ($current !== '' && $current === $previous) {
                $run++;
                $longestRun = max($longestRun, $run);

                // Keep the first two, drop the rest of the run.
                if ($run > 2) {
                    $removed++;

                    continue;
                }
            } else {
                $run = 1;
            }

            $kept[] = $sentence;
        }

        if ($removed === 0) {
            return [$text, []];
        }

        return [
            implode(' ', $kept),
            [sprintf(
                'The model repeated one sentence %d times in a row; %d duplicates were removed. '
                . 'This usually means part of the audio was silent or unclear — treat the transcript as incomplete.',
                $longestRun,
                $removed,
            )],
        ];
    }

    /**
     * Sentences differing only in spacing or case are the same sentence.
     */
    protected static function normaliseForComparison(string $sentence): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $sentence) ?? ''));
    }

    /**
     * Whether this model returns timestamped segments.
     *
     * Only whisper-1 does. The gpt-4o transcription models are more accurate on
     * code-switched speech — which is most calls here — but return a single
     * block of text, so choosing one trades the clickable timeline for a better
     * transcript. Both are offered; neither is obviously right for everyone.
     */
    public static function supportsSegments(string $model): bool
    {
        return str_starts_with($model, 'whisper');
    }

    /**
     * The service names the language in full ("gujarati"); the column wants a
     * code. Unrecognised languages keep their name rather than being dropped.
     */
    protected function languageCode(?string $language): ?string
    {
        if (blank($language)) {
            return null;
        }

        return match (strtolower($language)) {
            'english' => 'en',
            'hindi' => 'hi',
            'gujarati' => 'gu',
            'marathi' => 'mr',
            'punjabi' => 'pa',
            'bengali' => 'bn',
            'tamil' => 'ta',
            'telugu' => 'te',
            'kannada' => 'kn',
            'malayalam' => 'ml',
            'urdu' => 'ur',
            default => mb_substr($language, 0, 12),
        };
    }

    protected function apiKey(): ?string
    {
        $key = Setting::getConfigured(
            'call_transcription_api_key',
            config('calls.transcription.drivers.openai.api_key'),
        );

        return filled($key) ? (string) $key : null;
    }
}
