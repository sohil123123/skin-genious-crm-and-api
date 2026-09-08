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

    /**
     * Whether this signal leaves the clinic owing somebody something.
     *
     * The set both Next Best Action engines fire on, kept here rather than
     * hand-listed in each of them. Two hand-written lists is how the first
     * version of this shipped, and they disagreed immediately: the patient
     * engine listened for "unresolved issue" and the lead engine did not, the
     * lead engine listened for "high intent" and the patient engine did not,
     * and neither listened for "staff followup required" — the one signal in
     * the whole vocabulary that says, in as many words, that a member of staff
     * has to do something. A call that produced exactly that signal generated
     * no action at all, which is the bug this method exists to make
     * impossible: a key added to the vocabulary is now either actionable or
     * deliberately not, decided once.
     *
     * Intent counts as well as an explicit request. "I want to book" and
     * "please book me in" are the same conversation, and a model choosing
     * between AppointmentIntent and AppointmentRequested for that sentence is
     * making a distinction the clinic does not have to act on.
     *
     * Objections and sentiment are excluded on purpose. "Sounded hesitant" is
     * not a promise, and chasing it as one is how a queue fills with actions
     * nobody can complete. They still reach the engines through the score
     * modifier and the reason text.
     */
    public function demandsFollowUp(): bool
    {
        return match ($this) {
            // Asked for something outright.
            self::CallbackRequested, self::InformationRequested,
            self::AppointmentRequested, self::StaffFollowUpRequired,
            self::PatientCommitment => true,

            // Wanted something, in words a shade softer.
            self::AppointmentIntent, self::PurchaseIntent,
            self::HighIntent, self::BuyingSignal => true,

            // Left the call still needing an answer.
            self::UnresolvedIssue => true,

            default => false,
        };
    }

    /**
     * How to say this in a sentence somebody can open a call with.
     *
     * Written to follow "On the call yesterday they ..." because the reason
     * field is read aloud in effect — a staff member is about to ring this
     * person, and "appointment_intent, staff_followup_required" tells them
     * nothing they can say.
     *
     * The match is exhaustive with no default arm, deliberately. The phrases
     * used to live in the reader behind a default that fell back to the machine
     * name, and the two keys the model actually returned for Rohit's call had
     * no arm — so the reason read "they appointment intent, staff followup
     * required". A missing arm is now a fatal error the first time a developer
     * runs the code, which is the only way this stays honest as the vocabulary
     * grows.
     */
    public function phrase(?string $value = null): string
    {
        return match ($this) {
            self::RecentCall => 'spoke to us recently',
            self::MissedCall => 'missed a call from us',
            self::RepeatedCalls => 'has been rung several times',
            self::NoResponse => 'has not been answering',
            self::HighEngagement => 'was engaged throughout',
            self::LowEngagement => 'was hard to draw out',

            self::HighIntent => 'was clearly interested',
            self::MediumIntent => 'was fairly interested',
            self::LowIntent => 'showed little interest',
            self::NotInterested => 'said they are not interested',
            self::AppointmentIntent => 'wanted to arrange an appointment',
            self::PurchaseIntent => 'wanted to go ahead',

            self::PriceObjection => 'raised the cost',
            self::TrustObjection => 'was unsure about trusting the clinic',
            self::TimingObjection => 'said the timing did not suit',
            self::EffectivenessObjection => 'questioned whether it works',
            self::FearObjection => 'was nervous about the treatment',
            self::DistanceObjection => 'mentioned the travel',
            self::FamilyApprovalObjection => 'wants to discuss it at home',

            self::PositiveSentiment => 'sounded positive',
            self::NeutralSentiment => 'sounded neutral',
            self::NegativeSentiment => 'sounded unhappy',
            self::Frustrated => 'sounded frustrated',

            self::CallbackRequested => 'asked to be called back',
            self::InformationRequested => 'asked for more information',
            self::AppointmentRequested => 'asked for an appointment',
            self::StaffFollowUpRequired => 'were promised a follow-up',
            self::PatientCommitment => 'said they would come back to us',

            self::Complaint => 'made a complaint',
            self::Dissatisfaction => 'was unhappy with something',
            self::UnresolvedIssue => 'left with a question unanswered',
            self::RepeatedFailedFollowUp => 'has not been reached despite several attempts',

            self::BuyingSignal => 'sounded ready to go ahead',
            self::AppointmentBooked => 'booked on the call',
            self::TreatmentInterest => filled($value) ? 'asked about ' . $value : 'asked about a treatment',
            self::ProductInterest => filled($value) ? 'asked about ' . $value : 'asked about a product',
        };
    }

    public function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    /**
     * The keys that put somebody in a work queue.
     *
     * @return array<int, string>
     */
    public static function followUpValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->demandsFollowUp()),
        ));
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
