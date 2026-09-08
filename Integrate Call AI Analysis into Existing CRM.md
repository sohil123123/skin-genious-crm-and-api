# Integrate Call AI Analysis into Existing CRM + Next Best Action

You are working on my existing skincare CRM project.

## Project

CRM path:

`E:\www\skin-genious-crm-and-api`

Technology:
- Laravel
- Filament v4
- Existing CRM database
- Existing Next Best Action system

IMPORTANT:

I have ALREADY implemented **Next Best Action (NBA)** in my CRM for:

1. Current patients
2. Meta/Facebook leads

DO NOT rebuild or replace the existing NBA system.

Your job is to inspect the existing implementation and EXTEND it so that **Call AI Analysis becomes another intelligence/data source for NBA**.

---

# 1. First Understand My Existing CRM

Before changing anything, inspect the project and understand:

- Existing patient model/table
- Existing Meta lead model/table
- Existing call-related models/tables
- Existing Exotel incoming call integration
- Existing Callyzer outgoing call integration
- Existing call records
- Existing Next Best Action implementation
- NBA rules/scoring
- NBA services/classes
- NBA database structure
- Filament resources/pages/widgets
- Existing patient/lead relationship structure
- Existing activity/timeline system
- Existing webhook/API architecture
- Existing queue/jobs/events/listeners
- Existing authentication/authorization

Search the codebase instead of assuming file names.

DO NOT create duplicate functionality if something already exists.

Before coding, give me a short architecture summary:

### Existing:
- Patient data:
- Lead data:
- Call data:
- Incoming calls:
- Outgoing calls:
- NBA:
- Activities/timeline:
- AI-related functionality:
- Relevant services:
- Relevant Filament pages:

Then explain exactly where Call AI Analysis should be integrated.

---

# 2. Goal

I want my CRM to understand conversations between:

- Staff ↔ Patients
- Staff ↔ Leads

using AI call analysis.

The system should extract useful information from calls and store it in structured form.

The extracted information should then improve my existing:

**Next Best Action recommendations.**

The flow should become:

Call
→ Call Recording / Transcript
→ AI Analysis
→ Structured Call Insights
→ Patient/Lead Profile
→ Engagement History
→ NBA Engine
→ Better Next Best Action

---

# 3. Important Existing Call Architecture

My CRM uses:

### Incoming calls
Exotel

### Outgoing calls
Callyzer

I already want to store detailed call information from both systems.

The Call AI Analysis system should work on top of these existing call records.

DO NOT create a separate independent call system.

Use the existing call records as the source.

Every analyzed call must be linked to the correct:

- patient OR
- lead

and to the original call record.

---

# 4. Call AI Analysis Data

Design the system so that an analyzed call can contain structured information such as:

## Basic AI information

- analysis_status
- analyzed_at
- AI provider/model
- processing time
- confidence score
- transcript availability
- recording availability

## Call classification

Determine:

- call_type
- call_purpose
- call_outcome
- conversation_category

Examples:

- inquiry
- follow-up
- consultation
- appointment discussion
- treatment discussion
- product discussion
- complaint
- support
- payment discussion
- reschedule
- cancellation
- general inquiry
- other

---

# 5. Patient/Lead Intent

Extract intent from the conversation.

Examples:

- interested
- highly interested
- needs more information
- price sensitive
- wants appointment
- wants callback
- not interested
- postponed decision
- comparing options
- existing treatment concern
- complaint
- urgent request

Store intent in a structured way.

Do not rely only on free-text AI output.

Use normalized values/enums wherever appropriate.

---

# 6. Sentiment & Conversation Quality

Extract:

- overall sentiment
- sentiment score
- positive/neutral/negative
- frustration
- satisfaction
- trust level
- urgency
- objection level

Also identify whether the patient/lead appears:

- engaged
- confused
- dissatisfied
- hesitant
- ready to proceed

These values should be usable by the NBA engine.

---

# 7. Objections

AI should identify objections mentioned during calls.

Examples:

- price
- treatment cost
- timing
- distance
- fear
- side effects
- treatment effectiveness
- trust
- family approval
- availability
- previous bad experience
- not ready
- comparison with competitor
- other

Store objections in structured form.

A call can contain multiple objections.

---

# 8. Requirements / Needs

Extract what the patient or lead actually needs.

Examples:

- wants pricing
- wants consultation
- wants dermatologist consultation
- wants treatment details
- wants product information
- wants before/after examples
- wants appointment
- wants callback
- wants EMI/payment information
- wants availability
- wants treatment duration
- wants expected results

These insights should become usable inputs for NBA.

---

# 9. Commitments & Promises

Extract commitments made during the call.

Examples:

Patient/Lead:

- will call tomorrow
- will visit clinic
- will book appointment
- will send photos
- will make payment
- will discuss with family
- will decide later

Staff:

- will call back
- will send information
- will send quotation
- will arrange consultation
- will share details
- will follow up

Store these separately so they can later create follow-up actions.

---

# 10. Follow-Up Intelligence

AI should identify:

- follow-up required
- recommended follow-up date/time if explicitly mentioned
- follow-up reason
- who should follow up
- urgency
- promised action
- unresolved issue

Examples:

"Call me tomorrow"

"Send me the price on WhatsApp"

"I will visit next Monday"

"I need to discuss with my husband"

These should become structured signals.

IMPORTANT:

Do not automatically create appointments/tasks based only on uncertain AI interpretation.

Use confidence thresholds and/or a review mechanism where appropriate.

---

# 11. Treatment / Product Interest

If the conversation identifies a treatment, service, product, concern, or category, extract it.

Examples:

- acne
- pigmentation
- hair treatment
- skin treatment
- laser
- facial
- skincare product
- consultation
- specific clinic service

Use existing CRM entities/tables where they already exist.

Do not create duplicate treatment/product tables if equivalent entities already exist.

---

# 12. Important Conversation Insights

AI should also extract:

- key topics
- important statements
- unresolved questions
- buying signals
- risk signals
- churn/dissatisfaction signals
- conversion signals
- missed opportunities
- staff performance signals

Examples:

### Buying signal
"How soon can I start?"

### Price objection
"Your treatment is expensive."

### Follow-up signal
"Call me after 7 PM."

### Dissatisfaction
"I already called twice but nobody responded."

### High intent
"I want to book the appointment."

---

# 13. AI Summary

Every analyzed call should have:

### Short summary

A concise human-readable summary.

### Detailed summary

More detailed structured explanation.

### Key points

Bullet/structured points.

### Customer intent

What the patient/lead wants.

### Next action

What should happen next.

But remember:

The AI's "next action" is an INPUT to my existing NBA system.

It should NOT directly replace NBA.

---

# 14. Transcript

If transcript is available, store it safely.

Support:

- full transcript
- speaker identification
- timestamps if available
- language
- detected language
- transcript confidence

The system should support:

- English
- Gujarati
- Hindi
- mixed-language conversations

Do not assume calls are English only.

---

# 15. AI Analysis Architecture

Create a clean architecture such as:

Call Record
↓
Call AI Analysis
↓
Structured Insights
↓
Patient/Lead Intelligence
↓
NBA Input

Prefer service-oriented architecture.

For example, if appropriate after inspecting the project:

- CallAiAnalysisService
- CallAiProviderInterface
- CallInsightService
- PatientLeadInsightService
- NextBestAction integration

But DO NOT blindly create these exact classes.

Follow the existing project's architecture and naming conventions.

---

# 16. AI Provider Must Be Replaceable

Do not tightly couple the CRM to one AI provider.

Create an abstraction so that later I can change AI providers.

For example:

Call AI Provider
→ Provider Interface
→ OpenAI / Claude / other provider

The database should store:

- provider
- model
- prompt/version if useful
- analysis version
- raw response if appropriate
- normalized structured result

Store enough information for debugging and re-analysis.

---

# 17. Raw AI Response vs Structured Data

I want both where useful.

### Raw AI result

For debugging/auditing.

### Structured result

For CRM functionality.

NBA must use structured data.

Do NOT make NBA depend on parsing raw AI text.

---

# 18. NBA Integration

This is the MOST IMPORTANT part.

My existing NBA system already generates recommendations.

Do not replace it.

Instead add Call AI signals as additional inputs.

Example:

Existing NBA:

Lead has not contacted us for 5 days
→ Follow-up call

After Call AI:

Lead said:

"I am interested but price is too high."

AI insight:

intent = interested
objection = price
sentiment = neutral
purchase_intent = medium/high

NBA can now generate:

**Next Best Action:**
"Send a WhatsApp message addressing pricing/payment options."

---

# 19. NBA Signals

Design reusable signals such as:

### Engagement

- recent_call
- missed_call
- repeated_calls
- no_response
- high_engagement
- low_engagement

### Intent

- high_intent
- medium_intent
- low_intent
- appointment_intent
- purchase_intent

### Objections

- price_objection
- trust_objection
- timing_objection
- effectiveness_objection
- fear_objection

### Sentiment

- positive
- neutral
- negative
- frustrated

### Follow-up

- callback_requested
- information_requested
- appointment_requested
- staff_followup_required

### Risk

- complaint
- dissatisfaction
- unresolved_issue
- repeated_failed_followup

### Opportunity

- buying_signal
- appointment_signal
- treatment_interest
- product_interest

These should become inputs to NBA scoring/rules.

---

# 20. NBA Priority

Call AI should be able to change NBA priority.

Example:

Patient says:

"I am very interested. Please call me tomorrow morning."

This should create a high-priority follow-up signal.

Another example:

Patient says:

"I am unhappy because nobody called me back."

This should create a high-priority recovery action.

Another example:

Lead says:

"I am not interested right now."

NBA should NOT aggressively recommend repeated calls.

The system should understand negative signals and reduce inappropriate follow-ups.

---

# 21. Avoid Duplicate Actions

This is critical.

If:

- call happened today
- patient already requested callback tomorrow
- WhatsApp was already sent
- appointment already booked

NBA should not recommend the same action again unnecessarily.

Use existing CRM activities/communications/actions where possible.

Call AI should enrich existing data, not create duplicate activities.

---

# 22. Recency / Time Decay

Call insights should have recency.

For example:

A price objection from 6 months ago should not have the same weight as a price objection from yesterday.

Design NBA integration so recent call insights have higher relevance.

If my current NBA already has a scoring/decay system, integrate with it instead of creating another one.

---

# 23. Confidence

AI analysis is not always correct.

Store confidence for important extracted fields.

Example:

intent_confidence
sentiment_confidence
objection_confidence
followup_confidence

NBA should avoid making strong decisions from low-confidence AI insights.

Use configurable thresholds where appropriate.

---

# 24. Call Timeline

Integrate AI analysis into the existing patient/lead timeline.

Example:

## Call — 8 Sep 2026

Incoming call — 06:32

AI Analysis:

- Intent: High
- Sentiment: Positive
- Interest: Acne treatment
- Objection: Price
- Follow-up: Send pricing information
- Confidence: 92%

NBA:

**Next Best Action**
Send pricing/payment information on WhatsApp.

---

# 25. Filament UI

Inspect my existing Filament UI first.

Add AI insights naturally into the existing call/patient/lead interface.

Possible sections:

### Call details

- recording
- transcript
- summary

### AI insights

- sentiment
- intent
- objections
- interests
- topics
- urgency
- outcome

### Follow-up

- recommended follow-up
- commitment
- unresolved issue

### NBA

Show how the call influenced the recommendation.

For example:

**Why this action?**

> Patient showed high treatment interest but raised a price objection during the latest call.

This explanation is extremely important.

---

# 26. Patient/Lead Intelligence Profile

If my CRM architecture supports it, create a consolidated intelligence view.

Example:

## Patient Intelligence

Current intent:
High

Primary interest:
Acne treatment

Current objection:
Price

Sentiment:
Positive

Last meaningful call:
Today

Follow-up required:
Yes

Buying signal:
Strong

Open issue:
Pricing information requested

This should be derived from recent structured call insights and existing CRM data.

Avoid permanently overwriting historical facts unless appropriate.

Historical call analysis must remain available.

---

# 27. Historical Intelligence

Do not only store the latest AI result.

Every call analysis should remain historically available.

I should be able to see:

Call 1 → analysis
Call 2 → analysis
Call 3 → analysis

Then derive current patient/lead intelligence from historical events.

This allows future analytics such as:

- sentiment over time
- objection trends
- conversion patterns
- number of follow-ups
- call-to-conversion correlation
- staff performance
- lead quality
- patient engagement

---

# 28. Analytics Preparation

Do not overbuild analytics right now.

But design the database so later I can analyze:

- calls per patient
- calls per lead
- successful calls
- missed calls
- call duration
- sentiment trends
- intent trends
- objections
- treatment interest
- follow-up success
- conversion after call
- conversion rate by call outcome
- conversion rate by AI intent
- common objections
- common questions
- staff performance

The data model should support these future analytics.

---

# 29. Security & Privacy

Call recordings and transcripts may contain sensitive patient information.

Follow the existing CRM authorization system.

Ensure:

- only authorized users can see recordings/transcripts
- AI results respect patient/lead permissions
- sensitive data is not unnecessarily duplicated
- logs do not expose full transcripts unnecessarily
- API keys remain in environment/config
- raw AI responses are protected

Do not introduce insecure public recording URLs.

---

# 30. Queue / Background Processing

AI analysis should NOT block the incoming/outgoing call workflow.

Prefer:

Call completed
→ Call record saved
→ AI analysis queued
→ AI processing
→ Structured insights saved
→ NBA recalculated

Use the existing queue/job architecture if present.

Handle:

- retry
- timeout
- failure
- duplicate processing
- partial processing

An AI failure must NOT break the original call record.

---

# 31. Idempotency

A call must not accidentally receive multiple duplicate AI analyses.

Use a safe strategy such as:

call_id + analysis_version/provider/model

or follow the existing project architecture.

Allow re-analysis intentionally when needed.

---

# 32. Webhook / Integration

If the call AI provider sends results through webhook:

Support:

- authentication/signature validation
- idempotency
- status updates
- failed analysis
- retry
- mapping provider call ID → CRM call ID

Do not assume provider-specific fields until you inspect/configure the actual integration.

---

# 33. Database Design

Before migration creation, inspect my existing database.

Reuse existing tables where appropriate.

Only add tables/columns that are actually necessary.

Prefer a normalized design.

Potential conceptual structure:

### call_ai_analyses

- id
- call_id
- patient_id nullable
- lead_id nullable
- provider
- model
- status
- language
- transcript
- summary
- detailed_summary
- sentiment
- sentiment_score
- intent
- intent_score
- call_purpose
- call_outcome
- urgency
- confidence
- analyzed_at
- analysis_version
- raw_response
- timestamps

### call_ai_insights

Potentially normalized structured insights such as:

- analysis_id
- insight_type
- insight_key
- value
- confidence
- metadata

But ONLY implement this structure if it fits the existing CRM.

If a JSON structure is better for some AI fields, explain why.

Do not blindly normalize every field.

---

# 34. Relationships

The final design should support:

Call
→ AI Analysis

AI Analysis
→ Patient OR Lead

Patient/Lead
→ Calls

Patient/Lead
→ AI Insights

AI Insights
→ NBA

Use proper Laravel relationships.

Avoid duplicated patient/lead intelligence data unless there is a clear reason.

---

# 35. AI Prompt Design

Create a strict structured-output prompt for the AI model.

The AI should return JSON matching a defined schema.

Example concept:

{
  "summary": "...",
  "language": "gu",
  "intent": {
    "value": "high_interest",
    "confidence": 0.92
  },
  "sentiment": {
    "value": "positive",
    "score": 0.78,
    "confidence": 0.90
  },
  "objections": [
    {
      "type": "price",
      "confidence": 0.91
    }
  ],
  "interests": [],
  "buying_signals": [],
  "follow_up": {
    "required": true,
    "reason": "...",
    "suggested_time": null,
    "confidence": 0.88
  },
  "unresolved_issues": [],
  "key_topics": [],
  "commitments": [],
  "risk_signals": []
}

Do not use this exact schema blindly.

Adapt it after inspecting my existing CRM and NBA architecture.

---

# 36. Language Detection

The AI must support:

- English
- Hindi
- Gujarati
- Hinglish
- mixed Gujarati/Hindi/English

Store detected language.

Do not translate the original transcript unless necessary.

If translation is useful, keep the original transcript intact and store translation separately.

---

# 37. Next Best Action Explainability

Every NBA recommendation influenced by AI should be explainable.

Example:

### Next Best Action

**Send WhatsApp pricing information**

### Why?

- Latest call shows high treatment interest
- Patient raised price objection
- Patient requested more pricing information
- Last call was today
- No pricing WhatsApp sent yet

This will make the system much more useful to CRM users.

---

# 38. Do Not Over-Automate

AI should provide intelligence.

NBA should decide the action.

Do not automatically:

- send WhatsApp
- call patient
- book appointment
- cancel lead
- mark patient as converted

unless my existing NBA/action system explicitly supports automatic execution.

Initially, recommendations should remain explainable and controllable by CRM users.

---

# 39. Error Handling

Implement clear statuses:

- pending
- processing
- completed
- failed
- retrying

Store failure reason safely.

If AI analysis fails:

Call should still work normally.

NBA should continue using existing data.

---

# 40. Testing

Add tests for:

### Call AI

- successful analysis
- failed analysis
- retry
- duplicate webhook
- duplicate analysis
- invalid AI response
- missing transcript
- missing recording
- multilingual call

### NBA

Test scenarios:

1. High-interest call
2. Price objection
3. Negative sentiment
4. Callback requested
5. Appointment requested
6. Complaint
7. No interest
8. Existing appointment
9. Recent WhatsApp already sent
10. Multiple calls with conflicting insights
11. Low AI confidence
12. Old AI insight vs recent AI insight

Ensure AI insights improve NBA without breaking existing NBA behavior.

---

# 41. Migration Safety

This is an EXISTING production CRM.

Be careful with migrations.

Do not:

- delete existing data
- rename existing columns unnecessarily
- modify existing NBA behavior without justification
- break existing relationships
- break Meta lead functionality
- break patient functionality
- break Exotel integration
- break Callyzer integration

Use backward-compatible changes wherever possible.

---

# 42. Implementation Process

Follow this exact workflow:

## Step 1 — Inspect

Understand the current CRM.

## Step 2 — Map architecture

Show:

Call
→ Patient/Lead
→ AI
→ Insights
→ NBA

## Step 3 — Identify reusable code

Tell me what existing services/models/tables should be reused.

## Step 4 — Propose database changes

Show migration/table changes BEFORE implementing.

## Step 5 — Propose AI schema

Show the structured AI output.

## Step 6 — Propose NBA integration

Explain exactly how existing NBA will consume call insights.

## Step 7 — Implement

Only after the architecture is clear.

## Step 8 — Test

Create/update tests.

## Step 9 — Filament UI

Add the UI without disturbing existing screens.

---

# 43. Important Coding Rules

Follow the existing project's:

- naming conventions
- architecture
- service patterns
- model patterns
- policies
- validation
- database conventions
- Filament conventions
- queue conventions

Do not introduce unnecessary packages.

Do not rewrite working code just for style.

Do not duplicate existing services.

Keep the implementation modular.

---

# 44. VERY IMPORTANT — Before Coding

First inspect the existing codebase.

Do NOT immediately start creating migrations/models.

I want you to first tell me:

1. Where current call data is stored
2. How Exotel calls are stored
3. How Callyzer calls are stored
4. How calls connect to patients
5. How calls connect to leads
6. Where current NBA logic exists
7. How NBA scoring/rules currently work
8. Where NBA recommendations are stored
9. Where patient/lead timeline is implemented
10. What exact files/classes should be modified
11. What new files/classes/tables are required
12. What database migrations are required
13. How AI analysis should trigger NBA recalculation

Then STOP and wait for my approval before making major changes.

---

# Final Objective

The final architecture should look conceptually like this:

                    ┌──────────────────┐
                    │  Exotel Incoming │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │                  │
                    │   CRM Call Data  │
                    │                  │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │                  │
                    │  Call AI Engine  │
                    │                  │
                    └────────┬─────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
          Intent        Sentiment       Objections
              │              │              │
              └──────────────┼──────────────┘
                             │
                    ┌────────▼─────────┐
                    │ Patient / Lead   │
                    │ Intelligence     │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │ Existing NBA     │
                    │ Engine            │
                    └────────┬─────────┘
                             │
                    ┌────────▼─────────┐
                    │ Next Best Action │
                    └──────────────────┘

Callyzer outgoing calls should enter the same architecture.

The key principle is:

**Call AI does not replace Next Best Action.**

It provides better, richer, structured intelligence to the existing Next Best Action engine.

Build this as an extension of my current CRM, not as a new standalone system.