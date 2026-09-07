# Build Unified Exotel + Callyzer Call Management System for Skincare CRM

I am building a skincare CRM using:

- Laravel
- Filament 4
- MySQL
- Existing CRM with leads/customers/users
- Callyzer for OUTGOING calls
- Exotel for INCOMING calls

I want to build a production-ready **Unified Call Management System**.

The goal is NOT simply to import call logs.

The goal is to create a complete call intelligence foundation that will later be used for:

- Customer engagement tracking
- Customer timeline
- Lead/customer activity
- Next Best Action
- Call follow-up
- Agent performance
- Call analytics
- Customer engagement scoring
- AI call analysis
- Call transcription
- Call summaries
- Sentiment analysis
- Intent detection
- Lead conversion analysis
- Follow-up recommendations
- English/Gujarati WhatsApp engagement
- CRM automation

---

# IMPORTANT ARCHITECTURE REQUIREMENT

Do NOT design the main database around only Exotel or only Callyzer.

Create a provider-independent unified call architecture.

Both providers must be normalized into one common `calls` table.

Example:

Callyzer outgoing call
        ↓
Callyzer integration
        ↓
Unified Call Service
        ↓
calls table
        ↓
customer/lead
        ↓
recording
        ↓
transcription
        ↓
AI analysis
        ↓
engagement / Next Best Action

Exotel incoming call
        ↓
Exotel webhook/API
        ↓
Unified Call Service
        ↓
calls table
        ↓
customer/lead
        ↓
recording
        ↓
transcription
        ↓
AI analysis
        ↓
engagement / Next Best Action

The CRM should not need to care whether a call came from Exotel or Callyzer when displaying customer activity.

---

# FIRST STEP — ANALYZE EXISTING CRM

Before writing code:

1. Inspect the complete existing Laravel project.
2. Identify:
   - Lead model
   - Customer model
   - User/Agent model
   - Existing activity tables
   - Existing engagement tables
   - Existing notes
   - Existing follow-up/reminder system
   - Existing Next Best Action system
   - Existing WhatsApp integration
   - Existing notification system
   - Existing storage configuration
   - Existing queue/jobs
   - Existing API/webhook architecture
3. Reuse existing models and relationships wherever possible.
4. Do NOT create duplicate customer/lead systems.
5. Do NOT break existing functionality.
6. Follow existing project coding conventions.
7. Follow existing Filament 4 patterns.
8. Follow existing authorization/policy architecture.

Before implementation, explain:

- Which existing tables/models will be reused
- Which new tables are required
- Which existing tables need modification
- How calls will connect to leads/customers/users
- How recordings/transcriptions will connect to calls

Then implement.

---

# PROVIDERS

## Provider 1: Callyzer

Callyzer is currently used for OUTGOING calls.

Use the current Callyzer API documentation as the source of truth.

Important current Callyzer fields include:

- id
- emp_name
- emp_code
- emp_country_code
- emp_number
- emp_tags
- client_name
- client_country_code
- client_number
- duration
- call_type
- call_date
- call_time
- note
- call_recording_url
- crm_status
- reminder_date
- reminder_time
- synced_at
- modified_at
- lead_id
- call_method
- call_mode

Callyzer supports:

- Call History API
- Fetch Call History By IDs
- Webhooks
- Recording URLs
- API authentication
- Pagination
- Date filtering
- Employee filtering
- Client filtering

The current Callyzer API has a rate limit of approximately one request every two seconds, so synchronization must use queues/rate limiting rather than uncontrolled API requests.

Do NOT hardcode assumptions about the Callyzer response.

Create a provider adapter/service so Callyzer-specific logic stays isolated.

Example architecture:

CallyzerClient
CallyzerWebhookController
CallyzerCallMapper
CallyzerSyncService

---

# PROVIDER 2: EXOTEL

Exotel is currently used for INCOMING calls.

Use the current official Exotel API/webhook documentation as the source of truth.

Before implementing Exotel integration:

1. Identify the exact webhook events available for incoming calls.
2. Identify all available call fields.
3. Identify recording URL fields.
4. Identify call status fields.
5. Identify caller/customer number.
6. Identify called/Exotel number.
7. Identify agent/employee information.
8. Identify call SID / unique provider ID.
9. Identify start/end timestamps.
10. Identify duration.
11. Identify hangup/disposition information.
12. Identify IVR/flow information if available.
13. Identify any recording metadata.
14. Identify retry/webhook behavior.

Do NOT invent Exotel fields.

Store the original Exotel webhook/API payload so future provider fields can be recovered without changing the schema.

Create:

ExotelClient
ExotelWebhookController
ExotelCallMapper
ExotelCallService

---

# UNIFIED CALL MODEL

Create one main table:

calls

The `calls` table must be provider-independent.

Recommended structure:

## Identity

- id
- uuid
- provider
- provider_call_id
- provider_reference_id
- provider_parent_call_id
- provider_event_id
- source

Provider examples:

- exotel
- callyzer

Source examples:

- webhook
- api_sync
- manual
- import

Add unique/indexed constraints appropriately.

Do NOT assume provider_call_id alone is globally unique.

Use a safe provider + provider_call_id uniqueness strategy.

---

# CALL DIRECTION

Store:

- direction

Values:

- incoming
- outgoing
- missed
- rejected
- unknown

Also store:

- call_status
- connection_status
- disposition

Do not mix direction and status.

Example:

direction = incoming
status = answered

or:

direction = incoming
status = missed

or:

direction = outgoing
status = no_answer

---

# PARTICIPANTS

Store complete normalized participant information.

Customer side:

- customer_id
- lead_id
- client_name
- client_country_code
- client_phone
- normalized_client_phone

Agent side:

- user_id
- employee_name
- employee_code
- employee_country_code
- employee_phone
- normalized_employee_phone

CRM side:

- assigned_user_id
- team_id if existing CRM supports teams

Also store provider-specific participant IDs where available.

---

# PHONE NUMBER NORMALIZATION

Create a centralized phone normalization service.

It must:

- Handle +91 numbers
- Handle 91XXXXXXXXXX
- Handle 10-digit Indian numbers
- Remove spaces
- Remove hyphens
- Remove brackets
- Handle country code
- Store original number
- Store normalized number

Never destroy the original provider number.

Example:

original_phone
normalized_phone
country_code

The normalized number should be used for matching leads/customers.

---

# LEAD/CUSTOMER MATCHING

When a call arrives:

1. Try exact normalized phone match.
2. Search existing lead.
3. Search existing customer.
4. Search alternate phone fields if the CRM has them.
5. If exactly one match exists, attach automatically.
6. If multiple matches exist, mark as ambiguous.
7. If no match exists, do NOT silently create duplicate customer records.

Store:

- matching_status
- matching_method
- matched_at

Example:

matching_status:

- matched
- unmatched
- ambiguous
- manually_matched

matching_method:

- phone
- alternate_phone
- provider_lead_id
- manual
- unknown

Create a manual matching mechanism in Filament.

---

# CALL TIMING

Store all timing information.

Fields:

- started_at
- answered_at
- ended_at
- duration_seconds
- ring_duration_seconds
- talk_duration_seconds
- hold_duration_seconds
- wait_duration_seconds
- timezone

If the provider only supplies some values:

- store what is available
- calculate derived values only when reliable
- never fabricate provider values

Also store:

- provider_started_at
- provider_answered_at
- provider_ended_at

This allows future debugging.

---

# RECORDING SYSTEM

This is VERY IMPORTANT.

Do not only store:

call_recording_url

Create a separate table:

call_recordings

A call can potentially have multiple recordings.

Fields should include:

- id
- call_id
- provider
- provider_recording_id
- source_url
- storage_disk
- storage_path
- original_filename
- mime_type
- extension
- file_size
- duration_seconds
- checksum
- download_status
- download_attempts
- downloaded_at
- storage_status
- transcription_status
- transcription_attempts
- transcription_started_at
- transcription_completed_at
- error_message
- metadata JSON
- created_at
- updated_at

Statuses:

download_status:

- pending
- downloading
- downloaded
- failed

storage_status:

- remote_only
- downloading
- stored
- failed

transcription_status:

- pending
- processing
- completed
- failed
- not_available

---

# RECORDING DOWNLOAD

When a provider supplies a recording URL:

1. Save original URL.
2. Do NOT immediately download inside webhook request.
3. Dispatch queue job.
4. Download recording asynchronously.
5. Validate HTTP response.
6. Validate content type.
7. Validate file.
8. Calculate checksum.
9. Store using Laravel filesystem.
10. Save storage path.
11. Mark download status.
12. Dispatch transcription job.

Important:

Webhook/API requests must remain fast.

Never perform long recording downloads synchronously inside webhook controllers.

---

# RECORDING STORAGE

Use Laravel filesystem.

Make storage configurable.

Example:

CALL_RECORDING_DISK

Possible:

- local
- public
- s3

Do not hardcode storage paths.

Recommended:

call-recordings/{YYYY}/{MM}/{call_uuid}/recording.ext

Never expose private recordings publicly unless explicitly authorized.

Use signed/private access where appropriate.

---

# TRANSCRIPTION

After recording download:

Dispatch:

TranscribeCallRecordingJob

The transcription architecture must be provider-independent.

Create a service interface such as:

CallTranscriptionServiceInterface

The system should support:

- audio file
- audio URL
- language
- provider
- transcription result
- confidence if available
- timestamps if available

Store transcription in a separate table:

call_transcriptions

Fields:

- id
- call_id
- call_recording_id
- provider
- language
- language_code
- transcript
- transcript_json
- confidence
- duration_seconds
- model
- version
- status
- error_message
- started_at
- completed_at
- created_at
- updated_at

Do NOT put a huge transcript directly into the calls table.

---

# TRANSCRIPT SEGMENTS

If the transcription provider supports timestamps/speakers, create:

call_transcript_segments

Fields:

- id
- call_transcription_id
- speaker
- speaker_type
- start_seconds
- end_seconds
- text
- confidence
- metadata JSON

Speaker types:

- agent
- customer
- unknown

This will be important for AI analysis later.

---

# AI CALL ANALYSIS

Prepare the architecture now even if AI analysis is implemented later.

Create:

call_analyses

Possible fields:

- id
- call_id
- call_transcription_id
- analysis_version
- summary
- customer_intent
- call_reason
- outcome
- sentiment
- sentiment_score
- urgency
- lead_temperature
- purchase_intent
- objection
- product_interest
- treatment_interest
- price_discussed
- appointment_discussed
- appointment_booked
- follow_up_required
- follow_up_reason
- next_best_action
- next_best_action_priority
- ai_confidence
- structured_result JSON
- created_at
- updated_at

Keep AI-specific analysis separate from raw call data.

---

# CUSTOMER ENGAGEMENT

The unified call system must integrate with the existing CRM engagement/activity architecture.

Every call should create an activity/event in the customer's timeline.

Example:

Customer Timeline

09:20 AM
Incoming call
Duration: 4m 32s
Agent: Priya
Outcome: Interested
Recording
Transcript
AI Summary
Next Best Action

10:30 AM
WhatsApp message

12:00 PM
Follow-up call

Do NOT create duplicate timeline records if the CRM already has an activity/event system.

Reuse it.

---

# CALL NOTES

Store provider notes separately from CRM notes.

Provider note:

provider_note

CRM note:

crm_note

AI generated note:

ai_summary

Agent notes should be editable from CRM.

Never overwrite original provider data.

---

# FOLLOW-UP

Callyzer may provide:

- crm_status
- reminder_date
- reminder_time
- lead_id

Store these as provider fields.

But also integrate with the CRM's existing follow-up/reminder system.

Do not create a second independent reminder system if the CRM already has one.

---

# RAW PROVIDER DATA

This is mandatory.

Create:

call_provider_payloads

Fields:

- id
- call_id
- provider
- event_type
- request_id
- payload JSON
- headers JSON
- received_at
- processed_at
- processing_status
- processing_error

Store the ORIGINAL payload exactly as received.

Reasons:

- Debugging
- Provider changes
- Missing fields
- Future migrations
- Audit trail
- Reprocessing
- AI/data analysis

Never depend only on normalized fields.

---

# WEBHOOK IDEMPOTENCY

This is critical.

Both providers may retry requests.

Implement idempotency.

Create:

call_webhook_events

Fields:

- id
- provider
- event_id
- provider_call_id
- event_type
- payload_hash
- received_at
- processed_at
- processing_status
- error_message

Use unique constraints where appropriate.

The same webhook must never create duplicate calls.

---

# CALLYZER SYNC

Implement both:

## Webhook synchronization

When Callyzer sends webhook:

CallyzerWebhookController

→ validate secret

→ store raw payload

→ dispatch processing job

→ normalize call

→ find customer

→ create/update call

→ queue recording

## API synchronization

Create scheduled/manual synchronization.

Example:

CallyzerCallSyncService

Parameters:

- from
- to
- employee
- client
- call types

Use pagination.

Respect API rate limits.

Use queues.

Use incremental sync based on:

- synced_at
- modified_at

Also support manual:

"Sync Now"

from Filament.

---

# EXOTEL WEBHOOK

Create:

POST /api/webhooks/exotel/calls

Requirements:

- validate webhook request
- authenticate/verify according to Exotel documentation
- store raw payload
- generate idempotency key
- create/update unified call
- resolve customer
- resolve agent
- process call status changes
- capture recording URL
- queue recording download
- queue transcription
- create CRM activity

The endpoint must return quickly.

Never run transcription directly inside the webhook.

---

# CALL UPDATE STRATEGY

Calls can arrive in multiple stages.

Example:

Incoming call starts

→ webhook

Call answered

→ webhook

Call ends

→ webhook

Recording available

→ later webhook/API update

Therefore the implementation must support UPSERT/update behavior.

Never assume the first event contains all call information.

Example:

Call initially:

status = ringing

Later:

status = answered

Later:

status = completed
duration = 240

Later:

recording_url = ...

The same `calls` record must be updated.

---

# PROVIDER ADAPTER ARCHITECTURE

Use a clean interface.

Example:

CallProviderInterface

Methods conceptually:

- fetchCall()
- normalizeCall()
- validateWebhook()
- getCallId()
- getRecording()
- syncCalls()
- processWebhook()

Implement:

CallyzerProvider
ExotelProvider

The unified call service should work with either provider.

---

# DATABASE DESIGN

Create migrations for the required tables.

At minimum consider:

1. calls
2. call_recordings
3. call_transcriptions
4. call_transcript_segments
5. call_analyses
6. call_provider_payloads
7. call_webhook_events

Only create additional tables when justified by the existing CRM architecture.

Add:

- foreign keys
- indexes
- composite indexes
- unique constraints
- nullable fields where providers may not supply data
- JSON fields for provider-specific metadata

Do not over-normalize provider payload fields that are unlikely to be queried.

---

# IMPORTANT INDEXES

Optimize for:

- customer_id
- lead_id
- user_id
- provider
- provider_call_id
- normalized_client_phone
- direction
- call_status
- started_at
- ended_at
- created_at

Composite indexes should support:

customer + started_at

lead + started_at

provider + provider_call_id

normalized_client_phone + started_at

direction + started_at

---

# FILAMENT 4 ADMIN UI

Create a Call Management section.

## Call List

Columns:

- Date/time
- Customer
- Lead
- Phone
- Direction
- Provider
- Agent
- Duration
- Status
- Outcome
- Recording
- Transcription
- AI analysis
- Follow-up
- Created at

Filters:

- Incoming
- Outgoing
- Missed
- Rejected
- Answered
- Provider
- Agent
- Customer
- Lead
- Date range
- Duration
- Recording available
- Transcription available
- AI analyzed
- Follow-up required

---

# CALL DETAIL PAGE

Create a complete call detail view.

Sections:

## Call Information

- Provider
- Provider call ID
- Direction
- Status
- Date/time
- Duration
- Agent
- Customer
- Lead

## Participants

Customer information

Agent information

## Recording

- Audio player
- Recording source
- Download status
- Storage status
- File size
- Duration

## Transcript

Display full transcript.

If speaker segments exist:

Agent:
...

Customer:
...

## AI Analysis

Show:

- Summary
- Intent
- Sentiment
- Outcome
- Purchase intent
- Objection
- Product interest
- Appointment status
- Follow-up required
- Next Best Action

## Raw Provider Data

Show raw JSON in a collapsible section.

## Sync Information

Show:

- Provider
- Source
- Received at
- Synced at
- Modified at
- Last processing status
- Errors

---

# CUSTOMER PROFILE

Add a Calls tab to the existing customer/lead profile.

Show:

- Total calls
- Incoming calls
- Outgoing calls
- Missed calls
- Connected calls
- Total talk duration
- Average duration
- Last call
- Last incoming call
- Last outgoing call
- Follow-up required

Then show complete chronological call timeline.

---

# DASHBOARD / ANALYTICS

Prepare reusable query/service classes for analytics.

Metrics:

- Total calls
- Incoming calls
- Outgoing calls
- Missed calls
- Rejected calls
- Connected calls
- Connection rate
- Total duration
- Average duration
- Unique customers contacted
- Calls per agent
- Calls per customer
- Calls by day
- Calls by hour
- Calls by source
- Calls by outcome
- Calls requiring follow-up
- Calls with recordings
- Calls transcribed
- Calls AI analyzed

Agent metrics:

- Total calls
- Incoming
- Outgoing
- Answered
- Missed
- Average duration
- Total talk time
- Unique customers
- Follow-up completion
- Conversion if CRM data supports it

---

# NEXT BEST ACTION FOUNDATION

Do NOT hardcode the NBA rules into the call ingestion system.

Call ingestion should produce reliable structured data.

The NBA system can later consume:

- last_call_at
- last_incoming_call_at
- last_outgoing_call_at
- days_since_last_call
- total_calls
- connected_calls
- missed_calls
- total_talk_duration
- average_talk_duration
- call_outcomes
- sentiment
- intent
- purchase_intent
- objection
- appointment status
- follow-up required
- transcript
- AI summary

This makes call data reusable.

---

# ERROR HANDLING

Every integration must be fault tolerant.

Handle:

- API timeout
- API 401
- API 403
- API 429
- invalid payload
- missing provider call ID
- invalid phone number
- recording download failure
- recording unavailable
- transcription failure
- duplicate webhook
- customer not found
- ambiguous customer
- malformed JSON

Never lose the original provider payload.

Use:

- Laravel logs
- database processing status
- queue retries
- exponential/backoff retry where appropriate

---

# QUEUES

Use Laravel queues for:

- Callyzer synchronization
- Exotel processing
- Recording downloads
- Transcription
- AI analysis
- Customer matching if expensive
- Analytics aggregation if required

Webhook controllers must remain lightweight.

---

# SECURITY

Protect:

- API credentials
- Callyzer API token
- Exotel credentials
- webhook secrets
- recordings
- transcripts
- customer phone numbers

Never expose secrets in:

- database payloads unnecessarily
- frontend
- logs
- Filament pages

Sensitive raw payloads should have appropriate access control.

Recordings should not be publicly accessible by default.

---

# PRIVACY / AUDIT

Because calls and recordings contain customer communication:

- maintain access control
- log important administrative actions
- do not expose recordings to unauthorized users
- do not expose raw provider payloads to normal CRM users unless authorized
- retain provider metadata needed for auditing
- make retention configurable

---

# DATA RETENTION

Make recording/transcription retention configurable.

Example config:

CALL_RECORDING_RETENTION_DAYS

CALL_TRANSCRIPT_RETENTION_DAYS

Do not automatically delete data unless the CRM explicitly enables the policy.

---

# TESTING

Create tests for:

## Callyzer

- webhook accepted
- invalid webhook rejected
- duplicate webhook
- incoming/outgoing normalization
- phone normalization
- recording URL
- modified call
- pagination
- API rate limit handling

## Exotel

- incoming call webhook
- duplicate event
- call status update
- recording URL update
- customer matching
- missing fields
- invalid webhook

## Unified calls

- same customer incoming/outgoing calls
- unmatched phone
- ambiguous phone
- duplicate provider ID
- call update
- recording processing
- transcription processing

## Security

- unauthorized call access
- unauthorized recording access
- webhook authentication

---

# OBSERVABILITY

Create a simple integration health section.

Show:

Callyzer:

- Last successful sync
- Last failed sync
- Number of calls imported
- Number of failed calls
- Last API error
- Webhook status

Exotel:

- Last webhook received
- Last successful processing
- Failed events
- Last error

Recording:

- Pending
- Downloaded
- Failed

Transcription:

- Pending
- Processing
- Completed
- Failed

---

# ADMIN SETTINGS

Create CRM settings for:

Callyzer:

- enabled
- API token
- webhook secret
- sync enabled
- sync frequency
- employee mapping

Exotel:

- enabled
- credentials
- webhook secret
- virtual number mapping
- agent mapping

Recording:

- storage disk
- storage path
- download enabled
- transcription enabled

AI:

- transcription provider
- analysis enabled
- analysis model
- language settings

Use secure configuration/storage for credentials.

---

# IMPORTANT: PROVIDER MAPPING

Create mapping support because provider employee numbers may not exactly equal CRM users.

Example table if required:

call_provider_agents

Fields:

- id
- provider
- provider_employee_id
- provider_employee_code
- provider_employee_number
- user_id
- metadata
- active

This allows:

Callyzer employee
→ CRM user

Exotel agent
→ CRM user

---

# DO NOT DUPLICATE CALLS

The most important rule:

One real-world call must result in ONE unified call record.

If:

Callyzer webhook

and

Callyzer API sync

both contain the same call,

they must update the same record.

If Exotel sends multiple status events,

they must update the same call.

Design the idempotency logic carefully.

---

# DATA MODEL PRINCIPLE

Separate data into four layers:

## Layer 1 — Provider Raw Data

What Exotel/Callyzer actually sent.

Never modify.

## Layer 2 — Unified Call Data

Normalized CRM call.

Used by CRM features.

## Layer 3 — Derived Communication Intelligence

Recording, transcript, AI analysis.

## Layer 4 — CRM Engagement

Customer timeline, follow-up, Next Best Action, conversion.

Do not mix these responsibilities.

---

# IMPORTANT: DO NOT OVERWRITE SOURCE DATA

Example:

Callyzer says:

crm_status = "Interested"

Later CRM user changes outcome to:

"Appointment Booked"

Do not overwrite the Callyzer value.

Keep:

provider_crm_status = Interested

crm_outcome = Appointment Booked

Likewise:

provider_note
crm_note
ai_summary

must remain separate.

---

# IMPLEMENTATION ORDER

Implement in this exact order:

### Phase 1

Analyze existing CRM architecture.

### Phase 2

Create unified database schema.

### Phase 3

Create models and relationships.

### Phase 4

Create provider abstraction.

### Phase 5

Implement Callyzer integration.

### Phase 6

Implement Exotel integration.

### Phase 7

Implement webhook/idempotency.

### Phase 8

Implement customer/lead matching.

### Phase 9

Implement recording download/storage.

### Phase 10

Implement transcription architecture.

### Phase 11

Implement CRM customer timeline.

### Phase 12

Implement Filament Call Management.

### Phase 13

Implement analytics.

### Phase 14

Prepare Next Best Action data integration.

### Phase 15

Create tests.

---

# CODE QUALITY RULES

Use:

- Laravel services
- Jobs
- Events/listeners where appropriate
- Form requests
- Policies
- Resources
- Actions
- Enums
- DTOs/value objects where useful
- Database transactions
- Logging
- Queue retry strategy

Avoid:

- huge controllers
- provider-specific logic inside models
- provider-specific logic inside Filament resources
- duplicate call tables
- hardcoded credentials
- synchronous recording downloads
- synchronous transcription
- synchronous AI analysis
- duplicate customer creation

---

# ENUMS

Use Laravel enums where appropriate.

Examples:

CallProvider:

- EXOTEL
- CALLYZER

CallDirection:

- INCOMING
- OUTGOING
- MISSED
- REJECTED
- UNKNOWN

CallStatus:

- RINGING
- ANSWERED
- COMPLETED
- MISSED
- REJECTED
- BUSY
- NO_ANSWER
- FAILED
- UNKNOWN

CallMatchingStatus:

- MATCHED
- UNMATCHED
- AMBIGUOUS
- MANUALLY_MATCHED

---

# IMPORTANT USER REQUIREMENT

I want to store "every small detail".

Therefore:

1. Normalize all important searchable fields.
2. Store provider-specific fields in a `provider_data` JSON column where appropriate.
3. Store the complete original webhook/API payload separately.
4. Never throw away unknown provider fields.
5. Preserve timestamps.
6. Preserve provider IDs.
7. Preserve recording URLs.
8. Preserve provider status.
9. Preserve employee information.
10. Preserve customer information.
11. Preserve notes.
12. Preserve sync metadata.
13. Preserve CRM-related provider information.
14. Preserve call method.
15. Preserve call mode.
16. Preserve future provider fields without requiring immediate migrations.

But do NOT blindly create hundreds of database columns.

Use a balance of:

- normalized columns for frequently queried data
- JSON for provider-specific data
- raw payload table for complete source preservation

---

# DELIVERABLE

Before coding, provide:

1. Existing CRM architecture findings.
2. Proposed database ERD.
3. List of migrations.
4. List of models.
5. List of services.
6. List of jobs.
7. List of controllers.
8. List of Filament resources/pages/widgets.
9. Integration flow.
10. Recording flow.
11. Transcription flow.
12. Customer matching flow.
13. Idempotency strategy.
14. Queue strategy.

Then implement the functionality.

Do not make assumptions about existing CRM tables.

Inspect the existing project first.

Do not create duplicate functionality.

Do not run destructive database operations.

Do not run migrations, composer commands, npm commands, queue commands, or deployment commands automatically. I will run commands myself.

Provide me with the exact commands I need to run separately at the end.

---

# FINAL ACCEPTANCE CRITERIA

The implementation is considered complete only when:

- Callyzer outgoing calls are imported.
- Exotel incoming calls are imported.
- Both appear in one unified Calls section.
- Calls connect to existing leads/customers.
- Duplicate calls are prevented.
- Multiple provider events update the same call.
- Original provider payloads are preserved.
- Provider-specific data is preserved.
- Recordings are downloaded asynchronously.
- Recordings are stored securely.
- Recording URLs are preserved.
- Transcription is processed asynchronously.
- Transcript is stored separately.
- Speaker/timestamp segments can be stored.
- AI analysis can be added without redesigning calls.
- Customer timeline shows incoming/outgoing calls.
- Filament provides useful call filtering/search.
- Call analytics can be generated.
- Next Best Action can consume call engagement data.
- Failed integrations can be retried.
- API rate limits are respected.
- Webhooks are idempotent.
- Sensitive data is protected.
- Existing CRM functionality remains intact.

Most importantly:

**Design the system as a unified communication/engagement data layer, not simply as a Callyzer importer or Exotel importer.**