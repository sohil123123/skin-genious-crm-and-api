<?php

declare(strict_types=1);

namespace App\DTOs\Call;

use App\Enums\Call\CallSentiment;

/**
 * What an AI analyser concluded about one call.
 *
 * Every field is nullable because a model asked for twenty judgements about a
 * forty-second call will honestly not have twenty answers. A null here means
 * "the transcript did not say", which is a different and more useful claim than
 * a confident guess — and it is what stops the CRM reporting purchase intent
 * for a wrong number.
 *
 * The full model response is kept in $raw so a prompt change can be replayed
 * against what an earlier version actually returned.
 */
final readonly class CallAnalysisResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $summary = null,
        public ?string $customerIntent = null,
        public ?string $callReason = null,
        public ?string $outcome = null,
        public CallSentiment $sentiment = CallSentiment::Unknown,
        public ?float $sentimentScore = null,
        public ?string $urgency = null,
        public ?string $leadTemperature = null,
        public ?int $purchaseIntent = null,
        public ?string $objection = null,
        public ?string $productInterest = null,
        public ?string $treatmentInterest = null,
        // Nullable: a model that was not told about a booking has not told us
        // there was no booking. Defaulting these to false put a green tick
        // beside calls where nothing was booked.
        public ?bool $priceDiscussed = null,
        public ?bool $appointmentDiscussed = null,
        public ?bool $appointmentBooked = null,
        public ?bool $followUpRequired = null,
        public ?string $followUpReason = null,
        public ?string $nextBestAction = null,
        public ?int $nextBestActionPriority = null,
        public ?float $confidence = null,
        public ?string $model = null,
        public ?int $inputTokens = null,
        public ?int $outputTokens = null,
        /**
         * Validated signals, ready to become rows.
         *
         * Separate from $raw because $raw is whatever the model said and this
         * is what survived checking: unknown keys dropped, confidences clamped.
         * The action engines read only this.
         *
         * @var array<int, array{key: string, type: string, confidence: ?float, value: ?string}>
         */
        public array $signals = [],
        public array $raw = [],
    ) {}

    /**
     * Whether this is worth storing.
     *
     * A response with no summary and no intent is a model that had nothing to
     * say — usually a transcript of hold music. Writing that as a completed
     * analysis would put an empty AI panel in front of staff and teach them the
     * feature does not work.
     */
    public function isUseful(): bool
    {
        return filled($this->summary) || filled($this->customerIntent);
    }

    /**
     * The columns of call_analyses this maps onto.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'summary' => $this->summary,
            'customer_intent' => $this->customerIntent,
            'call_reason' => $this->callReason,
            'outcome' => $this->outcome,
            'sentiment' => $this->sentiment,
            'sentiment_score' => $this->sentimentScore,
            'urgency' => $this->urgency,
            'lead_temperature' => $this->leadTemperature,
            'purchase_intent' => $this->purchaseIntent,
            'objection' => $this->objection,
            'product_interest' => $this->productInterest,
            'treatment_interest' => $this->treatmentInterest,
            'price_discussed' => $this->priceDiscussed,
            'appointment_discussed' => $this->appointmentDiscussed,
            'appointment_booked' => $this->appointmentBooked,
            'follow_up_required' => $this->followUpRequired,
            'follow_up_reason' => $this->followUpReason,
            'next_best_action' => $this->nextBestAction,
            'next_best_action_priority' => $this->nextBestActionPriority,
            'ai_confidence' => $this->confidence,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'structured_result' => $this->raw ?: null,
        ];
    }
}
