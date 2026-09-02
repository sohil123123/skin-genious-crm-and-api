# MODIFY EXISTING META LEAD INTEGRATION — SIMPLE AUTOMATED FLOW

I already have a Laravel + Filament CRM and I already implemented part of the Meta Lead Ads integration.

I want to MODIFY the existing Meta integration.

Do NOT rebuild the whole integration from scratch.

First inspect the existing implementation and modify only what is necessary.

---

# MAIN GOAL

I want the Meta Lead integration to be completely automated.

I do NOT want to manually create/configure every Facebook Page in my CRM.

I do NOT want a complicated multi-business/multi-tenant architecture.

I want a simple system that automatically handles leads from any Meta Page/Form that is connected to the Meta App and sends them into my CRM.

The desired flow is:

```text
Any Facebook/Instagram Page
        ↓
Any Meta Lead Form
        ↓
User submits Lead Form
        ↓
Meta sends leadgen webhook
        ↓
Laravel receives webhook
        ↓
Laravel gets leadgen_id + page_id
        ↓
Laravel retrieves lead from Meta Graph API
        ↓
Laravel automatically gets Page information
        ↓
Laravel automatically gets Form information
        ↓
Laravel automatically gets available Campaign / Ad Set / Ad information
        ↓
Create/update CRM Lead
        ↓
Save Meta Page/Form information automatically
```

---

# VERY IMPORTANT

Keep the implementation SIMPLE.

Do NOT create:

- complex multi-tenant architecture
- complicated Meta account management
- separate CRM Lead modules for each Page
- manual Page setup workflow
- unnecessary repositories
- unnecessary interfaces
- unnecessary factories
- unnecessary packages
- unnecessary configuration screens

I only need automated Meta Lead ingestion.

---

# REQUIRED USER EXPERIENCE

The administrator should NOT have to do this:

```text
Create Meta Page
Enter Page ID
Enter Page Name
Enter Page Access Token
Select Clinic
Save
```

for every Page.

Instead:

```text
Meta App configured once
        ↓
Webhook configured once
        ↓
Meta Page submits lead
        ↓
Webhook receives page_id
        ↓
System automatically identifies Page
        ↓
System automatically stores Page information
        ↓
Lead is created
```

---

# SIMPLE DATA FLOW

When a Meta Lead Form is submitted:

### Step 1

Meta sends a webhook event containing the lead information, especially:

```text
leadgen_id
page_id
```

### Step 2

Laravel receives the webhook.

### Step 3

Laravel uses the appropriate Meta Graph API credentials to retrieve the complete lead.

### Step 4

Laravel automatically retrieves/stores Page information.

For example:

```text
page_id
page_name
```

### Step 5

Laravel retrieves/stores available Meta attribution information:

```text
form_id
form_name
campaign_id
campaign_name
adset_id
adset_name
ad_id
ad_name
```

Only save information that Meta actually provides or that can safely be retrieved.

Do NOT assume all fields are always available.

### Step 6

Laravel creates the normal CRM Lead.

Example:

```text
Lead
------------------------
Name
Phone
Email
Source = Meta
Status = New
```

### Step 7

Store Meta-specific information with the lead.

Example:

```text
Meta Lead ID
Page ID
Page Name
Form ID
Form Name
Campaign
Ad Set
Ad
Form Answers
Raw Meta Data
```

---

# MULTIPLE PAGES

The system should automatically support:

```text
Page A
Page B
Page C
```

but keep this implementation simple.

The only requirement is:

```text
Webhook receives page_id
        ↓
Find existing Meta Page record
        ↓
If not found:
    automatically create it
        ↓
Continue processing lead
```

Example:

```text
Webhook:

page_id = 111
leadgen_id = 999

↓

Meta Page 111 does not exist

↓

Automatically create:

Meta Page
page_id = 111
page_name = "My Clinic"
status = active

↓

Create Lead
```

If Page 111 already exists:

```text
Find Page 111
        ↓
Use existing record
        ↓
Create Lead
```

Do NOT require an administrator to manually create the Page first.

---

# ACCESS TOKEN

Keep access-token management as simple as possible.

There should be ONE primary Meta access token/configuration for the integration if that is sufficient for the current Meta setup.

Do not build a complicated token management system.

Use the existing Meta access-token configuration if already implemented.

Store the token securely/encrypted.

Never expose the token in Filament tables.

Never write the token to logs.

If Meta requires a Page-specific token for retrieving a lead, automatically obtain/use it through the existing Meta authorization mechanism where possible.

Do not make the administrator manually enter a Page token for every Page.

---

# EXISTING META PAGE RESOURCE

I currently have a Filament "Meta Pages" section.

Modify it to support the automated architecture.

It can remain useful for viewing automatically discovered Pages.

For example:

```text
Meta Pages

Page
Page ID
Status
Last Lead
Created At
```

But the administrator should NOT need to manually create a Page before receiving leads.

If the current "Create Meta Page" form is no longer necessary for normal operation, simplify/remove/disable that workflow.

Do not break existing records.

---

# WEBHOOK

Keep ONE webhook endpoint.

Example:

```text
POST /api/webhooks/meta
```

All connected Meta Pages/Form submissions should come through this webhook.

The webhook should:

1. Validate Meta request
2. Extract `page_id`
3. Extract `leadgen_id`
4. Dispatch lead processing
5. Return success quickly

Do not perform long-running Graph API processing directly in the webhook request if the existing implementation already uses queues.

---

# QUEUE

Use the existing Laravel queue architecture.

Flow:

```text
Meta webhook
    ↓
ProcessMetaLead job
    ↓
Meta Graph API
    ↓
Get Lead
    ↓
Get Page
    ↓
Get Form/Attribution
    ↓
Create CRM Lead
```

The job must be safe to retry.

---

# DUPLICATE LEADS

Meta can send the same webhook more than once.

Use:

```text
meta_lead_id
```

as the integration-level unique identifier.

Example:

```text
Webhook 1
leadgen_id = 123
→ Create Lead

Webhook 2
leadgen_id = 123
→ Do NOT create another Lead
```

Do NOT use phone number as the Meta duplicate identifier.

---

# DIFFERENT META FORMS

This is important.

I have multiple Meta Lead Forms.

Each form may contain different questions.

Example:

Form A:

```text
Name
Phone
Skin Type
Skin Problem
Age
```

Form B:

```text
Name
Phone
Hair Problem
Budget
Preferred Time
```

The CRM must handle both automatically.

Do NOT create database columns for every possible Meta question.

Store dynamic answers in the existing JSON/custom-fields structure.

Example:

```json
{
    "skin_type": "Oily",
    "skin_problem": "Acne",
    "age": "28"
}
```

Another lead:

```json
{
    "hair_problem": "Hair Fall",
    "budget": "5000",
    "preferred_time": "Evening"
}
```

---

# CRM LEAD

Use the EXISTING CRM Lead model.

Do NOT create a separate CRM Lead module for Meta.

Every Meta submission becomes a normal CRM Lead.

Example:

```text
Leads
    ↓
Lead #1001
Source = Meta
```

The existing Lead module should continue to handle:

- status
- assignment
- follow-up
- notes
- activities
- conversion
- etc.

Meta is simply the source.

---

# META INFORMATION ON LEAD

The Lead detail page should show a simple Meta section:

```text
Meta Information

Page
Page ID

Form
Form ID

Campaign
Campaign ID

Ad Set
Ad Set ID

Ad
Ad ID

Meta Lead ID
```

And:

```text
Form Answers
```

displayed dynamically.

---

# AUTOMATIC PAGE CREATION

This is one of the most important modifications.

When processing:

```text
page_id = 123456
```

do:

```text
Find MetaPage where page_id = 123456

IF EXISTS:
    use it

IF NOT EXISTS:
    retrieve Page details from Meta
    create MetaPage automatically
```

Example:

```text
Meta webhook
    ↓
page_id = 123
    ↓
MetaPage::where(page_id, 123)
    ↓
Not found
    ↓
Graph API
    ↓
{id: 123, name: "AI Aesthetics"}
    ↓
Create MetaPage
    ↓
Continue Lead processing
```

This should happen automatically.

---

# CLINIC MAPPING

The current Meta Page form has a required:

```text
Clinic
```

field.

Do NOT create complicated automatic clinic matching.

First inspect the existing CRM architecture.

If the CRM can safely determine the clinic from the Meta Page, use that.

Otherwise:

- allow a simple default clinic setting for Meta integration
- or use the existing Meta Page → Clinic mapping when available

The important point is:

**The administrator should not need to manually create the Meta Page before the first lead arrives.**

If a clinic is absolutely required for creating a CRM Lead and cannot be determined automatically, use the existing/default clinic configuration rather than failing the Meta lead.

Do not lose the lead just because Page metadata is missing.

---

# ERROR HANDLING

If Page information cannot be retrieved:

DO NOT discard the lead.

The system should still try to create the CRM Lead using:

```text
leadgen_id
page_id
available lead data
```

Record the Meta synchronization error for later retry.

Similarly, if campaign/ad information is unavailable:

Create the Lead anyway.

Meta attribution fields should be treated as optional.

---

# FILAMENT UI

Keep the UI simple.

## Meta Pages

Show automatically discovered Pages:

```text
Page
Page ID
Status
Last Lead
Last Sync
```

## Meta Sync Logs

Keep the existing sync log functionality.

Show:

```text
Lead ID
Page
Status
Error
Created At
```

Allow retry for failed processing if already supported.

## Lead

Existing Lead list:

```text
Name
Phone
Email
Source
Status
Created At
```

Meta-specific filters can be added if already useful.

---

# SETTINGS

Keep only the necessary Meta settings.

For example:

```text
Meta App ID
Meta App Secret
Meta Access Token
Webhook Verify Token
Meta API Version
```

Do not add unnecessary Page configuration screens.

The Meta integration should be configured once.

After that:

```text
ANY PAGE
ANY FORM
    ↓
Webhook
    ↓
Automatic processing
```

---

# IMPORTANT: DO NOT BREAK EXISTING FUNCTIONALITY

Before modifying anything:

1. Inspect existing Meta Pages code.
2. Inspect Meta webhook code.
3. Inspect Meta Lead Settings.
4. Inspect Meta Sync Log.
5. Inspect Lead model.
6. Inspect Lead migration.
7. Inspect Lead Filament Resource.
8. Inspect Meta Graph API service.
9. Inspect queue/job implementation.
10. Inspect existing permissions.

Then modify the existing implementation instead of creating a duplicate system.

---

# FINAL DESIRED RESULT

I want the final workflow to be this simple:

```text
STEP 1
User submits ANY Meta Lead Form
        ↓

STEP 2
Meta calls ONE Laravel webhook
        ↓

STEP 3
Laravel receives:

leadgen_id
page_id
        ↓

STEP 4
Laravel automatically gets:

Lead
Page
Form
Campaign
Ad Set
Ad
        ↓

STEP 5
If Page does not exist in CRM:

Automatically create Page record
        ↓

STEP 6
Create normal CRM Lead
        ↓

STEP 7
Show Lead in Filament
```

The administrator should NOT manually create Meta Pages for normal operation.

---

# CODING APPROACH

First inspect the existing implementation.

Then give me:

1. What currently exists
2. What is causing the current manual Page setup
3. What files need modification
4. What database changes are actually required
5. The simplest modification plan

Then implement the changes.

Do not rebuild the integration.

Do not over-engineer.

Keep it simple, automated, reliable and production-ready.

The final result should be:

```text
Configure Meta once
        ↓
Forget about Page configuration
        ↓
Meta leads automatically arrive in CRM
```