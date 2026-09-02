# Laravel + Filament CRM — Unified Lead Architecture + Meta Lead Ads Integration

I have an existing Laravel + Filament CRM.

I want to integrate Meta/Facebook/Instagram Lead Ads so that whenever a customer submits a Meta Lead Form, the lead is automatically received by my Laravel CRM and displayed in the existing Filament Lead module.

## MOST IMPORTANT ARCHITECTURE DECISION

Do NOT build Meta leads as a completely separate CRM lead system.

The CRM must have ONE unified Lead system.

Meta is only one Lead Source.

The architecture should be designed so that future sources can be added without changing the core Lead system.

Target architecture:

```text
                           CRM LEAD
                              |
             +----------------+----------------+
             |                |                |
            META            WEBSITE         WHATSAPP
             |                |                |
      Facebook/Instagram   Website Forms    WhatsApp
        Lead Ads
```

Future sources may include:

```text
Google Ads
Website
WhatsApp
Manual
Import
API
Referral
Other integrations
```

All of them must create/use the same CRM Lead model.

---

# CORE LEAD MODEL

First inspect the existing Lead model and database.

Reuse the existing Lead model if possible.

Do NOT create a separate `MetaLead` CRM entity if the existing Lead model can represent it.

The Lead should contain common CRM fields such as:

```text
id
name
phone
email
source
source_id
status
assigned_to
notes
custom_fields
created_at
updated_at
```

Use the existing project's actual fields and naming conventions.

Do not blindly add these columns if equivalent fields already exist.

---

# SOURCE ARCHITECTURE

The source-specific integration should be separated from the core Lead system.

Conceptually:

```text
Lead
 |
 +-- source = meta
 +-- source = website
 +-- source = whatsapp
 +-- source = google
 +-- source = manual
```

For external integrations, use a clean service/adapter architecture.

For example:

```text
LeadSource
    |
    +-- MetaLeadSource
    +-- WebsiteLeadSource
    +-- WhatsAppLeadSource
```

Or use interfaces/contracts if that fits the existing project architecture.

Do NOT over-engineer this.

The goal is:

```text
Meta Integration
      ↓
Normalize Meta Lead
      ↓
Existing CRM Lead
```

not:

```text
Meta Integration
      ↓
Separate Meta CRM
```

---

# META-SPECIFIC DATA

Meta-specific information should NOT pollute the core Lead table with unnecessary columns.

If appropriate, create a dedicated source/integration table such as:

```text
meta_leads
```

or use an existing integration/source metadata architecture.

Possible fields:

```text
id
lead_id
meta_lead_id
meta_page_id
meta_form_id
meta_campaign_id
meta_adset_id
meta_ad_id
meta_created_time
form_answers JSON
raw_data JSON
created_at
updated_at
```

Use the actual project naming conventions.

IMPORTANT:

`meta_lead_id` must be unique.

The same Meta webhook can be delivered more than once.

The integration must therefore be idempotent.

Example:

```text
Webhook #1
meta_lead_id = 123
→ Create CRM Lead

Webhook #2
meta_lead_id = 123
→ Do NOT create duplicate Lead
```

Use database-level uniqueness where appropriate, not only application-level checks.

---

# DYNAMIC META FORM QUESTIONS

This is extremely important.

Different Meta Lead Forms can contain completely different questions.

Example Form A:

```text
name
phone
skin_type
skin_problem
age
```

Example Form B:

```text
name
phone
hair_problem
preferred_time
budget
```

Do NOT create a database column for every possible Meta question.

Store dynamic answers using the existing custom-field/JSON architecture if available.

Example:

```json
{
    "skin_type": "Oily",
    "skin_problem": "Acne",
    "age": "28"
}
```

Another lead can contain:

```json
{
    "hair_problem": "Hair Fall",
    "preferred_time": "Evening",
    "budget": "5000"
}
```

The same CRM Lead architecture must support both.

In Filament, render these answers dynamically.

---

# META DATA RELATIONSHIP

Where possible, preserve the Meta attribution information:

```text
Page
Form
Campaign
Ad Set
Ad
Lead
```

The CRM should be able to answer:

```text
Which campaign generated this lead?
Which ad generated this lead?
Which form generated this lead?
Which Meta Page generated this lead?
```

Do not assume every webhook payload contains every attribution field.

Use the Meta Graph API where required.

Store IDs even when the human-readable names are unavailable.

---

# META WEBHOOK FLOW

Implement:

```text
Meta
 ↓
POST Laravel Webhook
 ↓
Validate webhook
 ↓
Extract leadgen_id
 ↓
Dispatch ProcessMetaLead Job
 ↓
Return HTTP 200 quickly
```

Do NOT perform the full Meta Graph API processing inside the webhook request.

Use a Laravel queued Job.

Example:

```text
MetaLeadWebhookController
        ↓
ProcessMetaLead Job
        ↓
MetaLeadService
        ↓
Meta Graph API
        ↓
Normalize Meta Lead
        ↓
Create/Update CRM Lead
```

---

# WEBHOOK

Create the appropriate Laravel webhook endpoint using the project's existing route conventions.

Conceptually:

```text
GET  /api/webhooks/meta
POST /api/webhooks/meta
```

GET:

- Meta webhook verification

POST:

- Meta `leadgen` events

Validate webhook requests according to Meta's current requirements.

Do not hard-code assumptions from old Meta API tutorials.

Use the current Meta documentation/API version.

---

# META GRAPH API

Create/reuse a dedicated Meta API service.

For example:

```text
MetaGraphApiService
```

Responsibilities:

- authentication
- API requests
- API version
- timeout handling
- error handling
- retry handling
- response parsing

Do not spread Graph API calls throughout controllers/models.

The Meta Lead service should use the API service.

---

# ACCESS TOKEN

Do not hard-code access tokens.

Use configuration/environment variables or the existing secure settings architecture.

Potential configuration:

```env
META_APP_ID=
META_APP_SECRET=
META_VERIFY_TOKEN=
META_ACCESS_TOKEN=
META_API_VERSION=
```

If the existing CRM has a secure settings/credentials system, inspect it first and use that architecture instead of unnecessarily creating another one.

Never display:

- App Secret
- Access Token

in Filament tables.

Never log them.

---

# FUTURE MULTI-PAGE / MULTI-BUSINESS SUPPORT

Design the Meta integration so it can support multiple Meta Pages later.

Do not assume only one Page if doing so would make future expansion difficult.

A possible architecture:

```text
Meta Integration
    |
    +-- Page A
    +-- Page B
    +-- Page C
```

Each Page may have:

```text
page_id
page_name
access_token
status
```

If the existing CRM is intended for only one business/Page, keep the first implementation simple but avoid architecture that makes multi-page support impossible.

Do NOT build a complex multi-tenant system unless the existing CRM requires it.

---

# LEAD SOURCE

The Lead module should clearly identify where the lead came from.

For example:

```text
Source:
Meta
```

or:

```text
Source:
Website
```

or:

```text
Source:
WhatsApp
```

If the existing CRM already has a source field/relationship, reuse it.

Do not create duplicate source systems.

---

# FILAMENT LEAD LIST

Inspect the existing Filament Lead Resource.

Do not replace the existing Lead Resource unless necessary.

Add Meta information to the existing Lead UI.

The Lead table should ideally show:

```text
Name
Phone
Email
Source
Campaign
Ad Set
Ad
Form
Status
Assigned User
Created At
```

Only show columns that make sense for the existing CRM.

Use filters such as:

```text
Source = Meta
Campaign
Form
Status
Assigned User
Date
```

---

# FILAMENT LEAD VIEW

The Lead view should contain logical sections.

Example:

```text
Lead Information

Name
Phone
Email
Status
Assigned User
```

```text
Source Information

Source
Source ID
```

For Meta leads:

```text
Meta Information

Meta Lead ID
Page
Form
Campaign
Ad Set
Ad
Created Time
```

```text
Meta Form Answers

Skin Type: Oily
Skin Problem: Acne
Age: 28
```

The form answers must be dynamic.

Do not hard-code:

```text
Skin Type
Skin Problem
Age
```

because another Meta Form may have completely different questions.

---

# FILAMENT PERMISSIONS

Inspect the existing permission system.

If the CRM uses:

- Filament Shield
- spatie/laravel-permission
- custom permissions

reuse it.

Meta integration actions such as:

```text
Sync Leads
Retry Lead
Test Connection
Connect Page
```

must respect the existing permission system.

Do not introduce another authorization system.

---

# META SYNC LOG

Inspect the existing logging architecture first.

If no suitable integration log exists, create a lightweight Meta synchronization log.

Possible fields:

```text
id
meta_lead_id
status
attempts
error_message
payload
processed_at
created_at
updated_at
```

Statuses:

```text
pending
processing
success
failed
```

This is for integration troubleshooting.

Do not duplicate the application's existing system log functionality.

---

# IMPORTANT: SYSTEM LOG

If the project already uses a system log package such as:

```text
opcodesio/log-viewer
```

continue using it for application/system errors.

Do not create another generic application log system.

Meta-specific synchronization status can have its own lightweight records if required.

---

# DUPLICATE HANDLING

Duplicate protection must happen at multiple levels.

1. Meta Lead ID unique database constraint
2. Application-level existing-record check
3. Queue/job idempotency
4. Safe retry

Example:

```text
Meta Lead
    ↓
Webhook
    ↓
Job
    ↓
Check meta_lead_id
    ↓
Existing?
 ┌──┴──┐
Yes   No
 ↓     ↓
Skip  Create
```

A retry must never create a duplicate CRM Lead.

---

# PHONE DUPLICATES

Do NOT assume phone number is the Meta duplicate identifier.

The same person may submit multiple Meta forms.

Example:

```text
Lead 1
Meta Lead ID = 100
Phone = 9999999999

Lead 2
Meta Lead ID = 200
Phone = 9999999999
```

These are two different Meta submissions.

Therefore:

```text
meta_lead_id
```

must be the integration-level unique identifier.

If the CRM has its own duplicate-lead/business rules based on phone number, preserve those rules separately.

Do not incorrectly reject Meta leads solely because the phone already exists.

---

# HISTORICAL LEADS

Support historical Meta lead import if the current Meta API/account permissions allow it.

Architecture:

```text
Meta Historical Leads
       ↓
Same normalization layer
       ↓
Same ProcessMetaLead Job
       ↓
Same CRM Lead creation logic
```

Do NOT create a separate import path that behaves differently from real-time leads.

Historical and real-time leads should eventually produce the same CRM Lead structure.

---

# FUTURE LEAD SOURCES

The architecture should make it easy to add:

```text
Meta
Website
WhatsApp
Google Ads
Manual
CSV Import
API
```

Adding a future source should ideally require:

```text
New source adapter/service
+
Source-specific metadata
```

and should NOT require rewriting the Lead module.

Example future architecture:

```text
Lead
 |
 +-- MetaLeadSource
 |
 +-- WebsiteLeadSource
 |
 +-- WhatsAppLeadSource
 |
 +-- GoogleLeadSource
 |
 +-- ManualLeadSource
```

Keep this extensible but simple.

Do not create unnecessary interfaces, repositories, factories, events, DTOs, or abstractions unless they provide real value in this project.

---

# IMPORTANT: INSPECT BEFORE CODING

Before making any changes, inspect:

1. Laravel version
2. Filament version
3. Existing Lead model
4. Lead migration
5. Lead Filament Resource
6. Lead relationships
7. Lead status system
8. Existing source/origin fields
9. Custom fields/JSON architecture
10. Existing Jobs
11. Existing Services
12. Existing API routes
13. Existing webhook routes
14. Existing settings system
15. Existing permissions
16. Existing logging
17. Existing queue configuration
18. Existing database conventions
19. Existing tests

Do not assume the project structure.

---

# IMPLEMENTATION RULES

- Do not upgrade Laravel.
- Do not upgrade Filament.
- Do not modify unrelated functionality.
- Do not create duplicate Lead systems.
- Do not create unnecessary packages.
- Do not hard-code Meta API credentials.
- Do not expose secrets.
- Do not put all Meta logic inside the Controller.
- Do not create database columns for every Meta form question.
- Do not use phone number as the Meta duplicate identifier.
- Do not block the webhook while performing long Graph API requests.
- Use queued processing.
- Make the integration idempotent.
- Use database transactions where appropriate.
- Follow existing project conventions.

---

# IMPLEMENTATION PHASES

Implement in phases.

## Phase 1 — Architecture Review

Inspect the project and report:

```text
Existing Lead architecture
Existing source architecture
Existing custom fields
Existing queue architecture
Existing settings
Existing permissions
Existing logging
```

Then propose the minimum required changes.

Do not code yet.

## Phase 2 — Database

Implement only the required database changes.

## Phase 3 — Meta Configuration

Implement:

- Meta config
- credentials
- API version configuration

## Phase 4 — Webhook

Implement:

- GET verification
- POST leadgen
- validation
- security
- queue dispatch

## Phase 5 — Graph API

Implement:

- Meta Graph API service
- lead retrieval
- error handling
- retries

## Phase 6 — Lead Normalization

Convert Meta data into the existing CRM Lead structure.

Example:

```text
Meta
 ↓
Normalized Lead Data
 ↓
CRM Lead
```

Keep normalization separate from database persistence where practical.

## Phase 7 — Duplicate Protection

Implement:

- unique meta_lead_id
- idempotent job
- safe retry

## Phase 8 — Filament

Update existing Lead Resource.

Add:

- Meta source
- Meta filters
- Meta information
- dynamic form answers
- sync status if appropriate

## Phase 9 — Historical Sync

Implement only if supported and useful after real-time integration works.

## Phase 10 — Testing

Test:

1. Webhook verification
2. Valid leadgen event
3. Invalid webhook
4. Duplicate webhook
5. Graph API success
6. Graph API failure
7. Queue processing
8. Failed job
9. Retry
10. Dynamic form questions
11. CRM lead creation
12. Existing CRM lead behavior
13. Historical import
14. Filament display
15. Permissions

---

# META API DOCUMENTATION

When implementing Meta-specific functionality, use current official Meta documentation.

Do not rely on old Stack Overflow answers or outdated tutorials for:

- permissions
- access tokens
- Graph API versions
- webhook requirements
- Lead Ads API
- Page subscriptions
- App Review

If current documentation differs from older examples, follow the current Meta requirements.

---

# OUTPUT FORMAT

Before changing code, give me:

### 1. Current architecture

Short summary of what you found.

### 2. Recommended architecture

Show:

```text
Meta
 ↓
Webhook
 ↓
Job
 ↓
Meta Service
 ↓
Normalize
 ↓
Unified CRM Lead
```

### 3. Database changes

Only list actual required changes.

### 4. Files to create/change

Give exact paths.

### 5. Implementation order

Give a simple numbered sequence.

Then wait for my approval before making large changes.

When implementing:

- Show exact file path.
- Explain why the file changes.
- Give production-ready code.
- Follow existing project conventions.
- Do not modify unrelated files.

The final result must feel like a native part of my existing Laravel + Filament CRM, not a separate Meta demo application.