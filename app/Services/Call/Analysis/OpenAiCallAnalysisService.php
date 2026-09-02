<?php

declare(strict_types=1);

namespace App\Services\Call\Analysis;

use App\DTOs\Call\CallAnalysisResult;
use App\Enums\Call\CallSentiment;
use App\Models\Call;
use App\Models\CallTranscription;
use App\Models\Setting;
use App\Services\Call\Contracts\CallAnalysisServiceInterface;
use App\Services\Call\Exceptions\CallTranscriptionException;
use App\Services\Call\Transcription\TranscriptWordCounter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a call transcript and extracts the things a clinic acts on.
 *
 * The prompt is written for this business rather than being generic: a skincare
 * clinic's calls turn on treatment interest, whether a price was quoted, what
 * the objection was, and whether an appointment got booked. A general-purpose
 * "summarise this call" returns prose nobody can filter or count.
 *
 * Two rules shape the whole design.
 *
 * The model is told to answer null when the transcript does not say. A model
 * asked for twenty judgements about a forty-second call will otherwise invent
 * them, and invented purchase intent is worse than none — it looks like data,
 * gets filtered on, and drives follow-up to the wrong people.
 *
 * And nothing here writes to the call's own CRM fields. The analysis has its
 * own follow_up_required and outcome; the call's belong to whoever typed them.
 * Letting a model overwrite a staff member's note would break the separation
 * the whole schema is built around.
 */
class OpenAiCallAnalysisService implements CallAnalysisServiceInterface
{
    /**
     * Statuses that will fail identically however many times they are retried.
     */
    protected const PERMANENT_STATUSES = [400, 401, 403, 404, 413, 422];

    /**
     * A transcript shorter than this cannot support twenty judgements. Sending
     * it costs money and returns confident nonsense.
     */
    protected const MIN_WORDS = 15;

    public function isEnabled(): bool
    {
        return (bool) Setting::getValue('call_analysis_enabled', config('calls.analysis.enabled', false))
            && filled($this->apiKey());
    }

    public function name(): string
    {
        return 'openai';
    }

    public function version(): string
    {
        return (string) Setting::getConfigured('call_analysis_version', config('calls.analysis.version', 'v1'));
    }

    public function analyse(Call $call, CallTranscription $transcription): ?CallAnalysisResult
    {
        $transcript = trim((string) $transcription->transcript);

        // Counted in a way that does not depend on the alphabet. This used to
        // be str_word_count() plus a space count, which returns 0 for
        // Devanagari and left the space tally doing all the work - so a Hindi
        // call needed roughly twice the words of an English one to clear the
        // same floor, and most of this clinic's calls are in Hindi.
        if ($transcript === '' || TranscriptWordCounter::count($transcript) < self::MIN_WORDS) {
            return null;
        }

        $model = (string) Setting::getConfigured('call_analysis_model', config('calls.analysis.model', 'gpt-4o-mini'));

        try {
            $response = Http::withToken((string) $this->apiKey())
                ->timeout((int) config('calls.queue.timeout', 180))
                ->post(rtrim((string) config('calls.transcription.drivers.openai.base_url'), '/') . '/chat/completions', [
                    'model' => $model,
                    // json_object rather than a strict schema: it is supported
                    // by every chat model, where strict schemas are not, and the
                    // mapping below already treats every field as untrusted.
                    'response_format' => ['type' => 'json_object'],
                    // Judgement, not creativity. Low but not zero — zero makes
                    // these models repeat themselves.
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($call, $transcript)],
                    ],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            throw new CallTranscriptionException('Could not reach the analysis API: ' . $exception->getMessage());
        }

        if ($response->failed()) {
            $message = sprintf('Analysis failed with HTTP %d: %s', $response->status(), mb_substr($response->body(), 0, 500));

            if (in_array($response->status(), self::PERMANENT_STATUSES, true)) {
                Log::channel('calls')->error('Call analysis rejected permanently.', [
                    'call_id' => $call->getKey(),
                    'status' => $response->status(),
                ]);

                return null;
            }

            throw new CallTranscriptionException($message);
        }

        $body = (array) $response->json();
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (blank($content)) {
            return null;
        }

        $data = json_decode((string) $content, true);

        if (! is_array($data)) {
            // A model that ignored the JSON instruction. Permanent for this
            // attempt rather than retried: the same prompt returns the same
            // shape, and a retry loop over a malformed response is just cost.
            Log::channel('calls')->warning('Call analysis returned something that was not JSON.', [
                'call_id' => $call->getKey(),
            ]);

            return null;
        }

        return $this->toResult($data, $model, (array) ($body['usage'] ?? []));
    }

    /**
     * The instruction. Kept in code rather than a setting because changing it
     * changes what every stored analysis means, and that should arrive through
     * a version bump and a deploy, not a text box.
     */
    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
        You analyse phone calls for a skincare and aesthetics clinic in India.
        Calls mix Hindi and English, often in the same sentence.

        Return a single JSON object with exactly these keys:

        summary                     2-3 sentences, plain English, what happened on the call
        customer_intent             a few words, e.g. "price enquiry", "book appointment", "complaint"
        call_reason                 a few words, why they rang
        outcome                     a few words, how it ended
        sentiment                   one of: positive, neutral, negative, mixed, unknown
        sentiment_score             number between -1 and 1
        urgency                     one of: high, medium, low
        lead_temperature            one of: hot, warm, cold
        purchase_intent             integer 0-100, how likely they are to buy
        objection                   what is stopping them, or null
        product_interest            products discussed, or null
        treatment_interest          treatments discussed, or null
        price_discussed             true, false, or null if not stated
        appointment_discussed       true, false, or null if not stated
        appointment_booked          true, false, or null if not stated
        follow_up_required          true, false, or null if not stated
        follow_up_reason            why a callback is needed, or null
        next_best_action            one sentence, the single most useful next step
        next_best_action_priority   integer 0-100
        confidence                  number 0-1, how sure you are overall

        Rules:
        - Use null when the transcript genuinely does not say. Do not guess.
        - appointment_booked is true ONLY if a specific appointment was actually
          agreed on this call - a date, a day, or a time both sides settled on.
          Interest in booking, being quoted a price, giving a name or number,
          being promised a WhatsApp message, or being invited to visit are NOT
          bookings. If the caller is still deciding, it is false.
        - appointment_discussed covers the weaker case: booking came up at all.
        - outcome must describe what actually happened by the end of the call,
          not what seemed likely to happen next.
        - A wrong number, a silent call or hold music is not a sales enquiry:
          return nulls and low confidence rather than inventing intent.
        - Write summary, objection and next_best_action in English even when the
          call was in Hindi, so one person can scan a day of calls.
        - Do not quote the caller's phone number or full name in the summary.
        PROMPT;
    }

    protected function userPrompt(Call $call, string $transcript): string
    {
        $context = implode("\n", array_filter([
            'Direction: ' . ($call->direction?->getLabel() ?? 'unknown'),
            'Outcome per the phone system: ' . ($call->call_status?->getLabel() ?? 'unknown'),
            $call->talk_duration_seconds !== null ? 'Talk time: ' . $call->talk_duration_seconds . ' seconds' : null,
        ]));

        return "Call details:\n{$context}\n\nTranscript:\n{$transcript}";
    }

    /**
     * Map an untrusted response onto the result.
     *
     * Every value is coerced and clamped. The model is instructed to return a
     * particular shape, not guaranteed to, and a string where an integer belongs
     * would otherwise reach the database as a silent 0.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $usage
     */
    protected function toResult(array $data, string $model, array $usage): CallAnalysisResult
    {
        $text = static fn (string $key): ?string => filled($data[$key] ?? null) && is_scalar($data[$key])
            ? mb_substr(trim((string) $data[$key]), 0, 500)
            : null;

        // Absent, null, or unrecognisable all mean "the model did not answer
        // this" - which is not the same as answering no. FILTER_NULL_ON_FAILURE
        // is what keeps those apart: without it "maybe" arrives as a definite
        // false and gets rendered as a fact.
        $bool = static function (string $key) use ($data): ?bool {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                return null;
            }

            return filter_var($data[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        };

        $percent = static function (string $key) use ($data): ?int {
            if (! isset($data[$key]) || ! is_numeric($data[$key])) {
                return null;
            }

            return (int) max(0, min(100, (int) round((float) $data[$key])));
        };

        $score = static function (string $key, float $min, float $max) use ($data): ?float {
            if (! isset($data[$key]) || ! is_numeric($data[$key])) {
                return null;
            }

            return max($min, min($max, (float) $data[$key]));
        };

        $oneOf = static function (string $key, array $allowed) use ($data): ?string {
            $value = is_scalar($data[$key] ?? null) ? strtolower(trim((string) $data[$key])) : null;

            return in_array($value, $allowed, true) ? $value : null;
        };

        return new CallAnalysisResult(
            summary: $text('summary'),
            customerIntent: $text('customer_intent'),
            callReason: $text('call_reason'),
            outcome: $text('outcome'),
            sentiment: CallSentiment::tryFrom((string) $oneOf('sentiment', ['positive', 'neutral', 'negative', 'mixed', 'unknown']))
                ?? CallSentiment::Unknown,
            sentimentScore: $score('sentiment_score', -1, 1),
            urgency: $oneOf('urgency', ['high', 'medium', 'low']),
            leadTemperature: $oneOf('lead_temperature', ['hot', 'warm', 'cold']),
            purchaseIntent: $percent('purchase_intent'),
            objection: $text('objection'),
            productInterest: $text('product_interest'),
            treatmentInterest: $text('treatment_interest'),
            priceDiscussed: $bool('price_discussed'),
            appointmentDiscussed: $bool('appointment_discussed'),
            appointmentBooked: $bool('appointment_booked'),
            followUpRequired: $bool('follow_up_required'),
            followUpReason: $text('follow_up_reason'),
            nextBestAction: $text('next_best_action'),
            nextBestActionPriority: $percent('next_best_action_priority'),
            confidence: $score('confidence', 0, 1),
            model: $model,
            inputTokens: isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null,
            outputTokens: isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null,
            raw: $data,
        );
    }

    /**
     * Its own key, falling back to the transcription one — the same OpenAI
     * account usually does both, and asking for it twice is friction for no
     * benefit.
     */
    protected function apiKey(): ?string
    {
        $key = Setting::getConfigured('call_analysis_api_key')
            ?: Setting::getConfigured('call_transcription_api_key', config('calls.transcription.drivers.openai.api_key'));

        return filled($key) ? (string) $key : null;
    }
}
