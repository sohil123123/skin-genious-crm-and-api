<?php

declare(strict_types=1);

namespace App\Enums\Call;

use Filament\Support\Contracts\HasLabel;

/**
 * The fixed vocabulary of things a call can tell the CRM.
 *
 * A closed set, not free text, and that is the whole point of it. The Next Best
 * Action engines have to ask "every lead with an open price objection in the
 * last month" — a question that only has an answer if "price objection" is one
 * value rather than forty phrasings of one idea. A model asked for free text
 * returns "cost concern", "too expensive", "budget issue" and "price is high"
 * for the same sentence, and each one is invisible to a rule written for the
 * others.
 *
 * So the model is handed this list and its output is checked against it.
 * Anything outside is dropped rather than stored, the same treatment sentiment
 * and urgency already get in the analyser.
 *
 * Some keys here are not things a model can hear. "Repeated calls" and "no
 * response" are facts about the CRM's own records, not about a conversation,
 * and are marked accordingly: the prompt only ever offers the conversational
 * ones, so the model is never invited to guess at something it cannot know.
 */
enum CallSignalKey: string implements HasLabel
{
    // ─── Engagement ────────────────────────────────────────────────────────
    case RecentCall = 'recent_call';
    case MissedCall = 'missed_call';
    case RepeatedCalls = 'repeated_calls';
    case NoResponse = 'no_response';
    case HighEngagement = 'high_engagement';
    case LowEngagement = 'low_engagement';

    // ─── Intent ────────────────────────────────────────────────────────────
    case HighIntent = 'high_intent';
    case MediumIntent = 'medium_intent';
    case LowIntent = 'low_intent';
    case NotInterested = 'not_interested';
    case AppointmentIntent = 'appointment_intent';
    case PurchaseIntent = 'purchase_intent';

    // ─── Objections ────────────────────────────────────────────────────────
    case PriceObjection = 'price_objection';
    case TrustObjection = 'trust_objection';
    case TimingObjection = 'timing_objection';
    case EffectivenessObjection = 'effectiveness_objection';
    case FearObjection = 'fear_objection';
    case DistanceObjection = 'distance_objection';
    case FamilyApprovalObjection = 'family_approval_objection';

    // ─── Sentiment ─────────────────────────────────────────────────────────
    case PositiveSentiment = 'positive_sentiment';
    case NeutralSentiment = 'neutral_sentiment';
    case NegativeSentiment = 'negative_sentiment';
    case Frustrated = 'frustrated';

    // ─── Follow-up ─────────────────────────────────────────────────────────
    case CallbackRequested = 'callback_requested';
    case InformationRequested = 'information_requested';
    case AppointmentRequested = 'appointment_requested';
    case StaffFollowUpRequired = 'staff_followup_required';
    case PatientCommitment = 'patient_commitment';

    // ─── Risk ──────────────────────────────────────────────────────────────
    case Complaint = 'complaint';
    case Dissatisfaction = 'dissatisfaction';
    case UnresolvedIssue = 'unresolved_issue';
    case RepeatedFailedFollowUp = 'repeated_failed_followup';

    // ─── Opportunity ───────────────────────────────────────────────────────
    case BuyingSignal = 'buying_signal';
    case AppointmentBooked = 'appointment_booked';
    case TreatmentInterest = 'treatment_interest';
    case ProductInterest = 'product_interest';

    public function type(): CallSignalType
    {
        return match ($this) {
            self::RecentCall, self::MissedCall, self::RepeatedCalls,
            self::NoResponse, self::HighEngagement, self::LowEngagement => CallSignalType::Engagement,

            self::HighIntent, self::MediumIntent, self::LowIntent,
            self::NotInterested, self::AppointmentIntent, self::PurchaseIntent => CallSignalType::Intent,

            self::PriceObjection, self::TrustObjection, self::TimingObjection,
            self::EffectivenessObjection, self::FearObjection,
            self::DistanceObjection, self::FamilyApprovalObjection => CallSignalType::Objection,

            self::PositiveSentiment, self::NeutralSentiment,
            self::NegativeSentiment, self::Frustrated => CallSignalType::Sentiment,

            self::CallbackRequested, self::InformationRequested, self::AppointmentRequested,
            self::StaffFollowUpRequired, self::PatientCommitment => CallSignalType::FollowUp,

            self::Complaint, self::Dissatisfaction,
            self::UnresolvedIssue, self::RepeatedFailedFollowUp => CallSignalType::Risk,

            self::BuyingSignal, self::AppointmentBooked,
            self::TreatmentInterest, self::ProductInterest => CallSignalType::Opportunity,
        };
    }

    /**
     * Whether a model reading a transcript could know this.
     *
     * The rest are facts about the CRM's own history — how many times somebody
     * has been rung, whether they ever answered — and are computed from records
     * rather than heard in a conversation. Offering them in the prompt would be
     * inviting the model to invent them.
     */
    public function isConversational(): bool
    {
        return ! in_array($this, [
            self::RecentCall,
            self::MissedCall,
            self::RepeatedCalls,
            self::NoResponse,
            self::RepeatedFailedFollowUp,
        ], true);
    }

    /**
     * Whether this signal argues for contacting somebody sooner.
     *
     * Read by the score modifier. Kept on the enum so the two Next Best Action
     * engines cannot disagree about what a signal means — a lead engine that
     * treated "not interested" as encouragement would be a bug nobody notices
     * until the complaints arrive.
     */
    public function pressure(): float
    {
        return match ($this) {
            self::AppointmentRequested, self::BuyingSignal, self::HighIntent => 0.35,
            self::CallbackRequested, self::AppointmentIntent, self::PurchaseIntent => 0.30,
            self::Complaint, self::Dissatisfaction => 0.30,
            self::InformationRequested, self::PatientCommitment,
            self::StaffFollowUpRequired, self::UnresolvedIssue => 0.20,
            self::PriceObjection, self::TrustObjection, self::EffectivenessObjection,
            self::FearObjection, self::FamilyApprovalObjection => 0.10,
            self::TreatmentInterest, self::ProductInterest, self::MediumIntent => 0.10,

            // Negative pressure: these argue for leaving somebody alone. A
            // person who said no, or who has just booked, does not want the
            // call the queue would otherwise recommend.
            self::NotInterested => -0.60,
            self::AppointmentBooked => -0.45,
            self::TimingObjection, self::DistanceObjection => -0.20,
            self::LowIntent, self::LowEngagement, self::Frustrated => -0.15,

            default => 0.0,
        };
    }

    public function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * The keys a model is allowed to return, for the prompt and for validation.
     *
     * @return array<int, string>
     */
    public static function conversationalValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isConversational()),
        ));
    }
}
