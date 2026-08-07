You are a Senior Laravel 12, PHP 8.3+, MySQL, Filament v4 Architect with 15+ years of experience.

Your job is to build a production-ready **Facebook Meta Lead CSV Import System** for my CRM.

## Tech Stack

- Laravel 12
- PHP 8.3+
- Filament v4
- MySQL
- Queue Supported
- Storage Local
- Tailwind
- Livewire
- Spatie packages if required

---

# IMPORTANT RULES

- Never simplify anything.
- Write production-ready code.
- Follow SOLID principles.
- Follow Laravel Best Practices.
- Use Service classes.
- Use Action classes when needed.
- Use Form Requests.
- Use DTO if useful.
- Keep code modular.
- Make everything reusable.
- Proper validation.
- Proper error handling.
- Transaction support.
- Logging.
- Notifications.
- Progress tracking.
- Clean UI.

Do NOT skip any file.

Whenever you create any file, show the full file.

Never use placeholders.

---

# VERY IMPORTANT

Do NOT execute any artisan commands.

Whenever artisan commands are required,

ONLY provide a section called

## Artisan Commands

Example

php artisan make:model ...
php artisan make:migration ...
php artisan make:filament-resource ...

I will execute them myself.

---

# Project Goal

I receive Facebook Meta Lead Ads CSV exports.

Every CSV comes from different Lead Forms.

Some columns are common.

Some columns are different.

Example

CSV A

Name
Phone Number
Email
City

CSV B

Name
Phone Number
Email
Skin Concern
Current Routine

CSV C

Name
Phone
Email
Age
Budget
Consultation Time

Every Facebook Form has different questions.

I need to import ALL of them into ONE CRM.

---

# Requirements

Create a complete Lead Import module.

---

## Module Features

Create

- Lead Import Page
- Upload CSV
- Import History
- Import Logs
- Failed Rows
- Duplicate Detection
- Mapping Screen
- Preview Screen
- Progress Screen

---

# CSV Upload

Support

CSV

UTF-8

Large Files

10k+

50k+

100k+

Queue imports.

No timeout.

Memory efficient.

---

# CSV Preview

Before importing

Show

First 20 rows

Detected Columns

Detected Data Types

Total Rows

Blank Rows

Duplicate Rows

Missing Required Fields

---

# Dynamic Column Mapping

This is the most important feature.

Every CSV has different columns.

Create a Mapping UI.

Example

CSV Column

Customer Name

↓

CRM Field

full_name

---

CSV

Mobile Number

↓

CRM

phone

---

CSV

Email Address

↓

CRM

email

---

CSV

Skin Concern

↓

Dynamic Question

---

CSV

Current Products

↓

Dynamic Question

The user should be able to map every CSV column.

Save mappings.

Reuse mappings later.

---

# Auto Mapping

Automatically match columns like

Name

Full Name

Customer Name

Your Name

→ full_name

Phone

Mobile

Phone Number

WhatsApp

Mobile Number

→ phone

Email

Email Address

Mail

→ email

City

Location

Town

→ city

Use fuzzy matching.

---

# Database Design

Design proper tables.

Example

leads

lead_imports

lead_import_logs

lead_custom_fields

lead_field_values

lead_mapping_templates

lead_import_failures

Do NOT store Facebook custom questions as fixed columns.

Store them dynamically.

---

# Dynamic Custom Fields

Every unknown column should automatically become

Custom Field

Example

Skin Concern

↓

lead_custom_fields

Question

Skin Concern

Answer

Acne

Another CSV

Budget

↓

lead_custom_fields

Question

Budget

Answer

5000

This makes the CRM future-proof.

---

# Lead Duplicate Detection

Detect duplicates using configurable rules.

Priority

Phone

Email

Facebook Lead ID

Combination Rules

Allow

Skip

Update Existing

Merge

Create Duplicate

User selects behavior before import.

---

# Import Settings

Allow

Skip Empty Rows

Trim Spaces

Normalize Phone Numbers

Normalize Emails

Convert Date Formats

Ignore Blank Columns

Ignore Hidden Columns

Ignore Duplicate Headers

---

# Phone Normalization

Convert

+91XXXXXXXXXX

91XXXXXXXXXX

0XXXXXXXXXX

XXXXXXXXXX

Into

Standard format

---

# Import Progress

Live progress bar.

Processed

Imported

Updated

Skipped

Failed

ETA

Percentage

---

# Queue Support

Imports must run in queue.

Large CSV should never block UI.

---

# Failed Rows

Store every failed row.

Reason

Validation Error

Duplicate

Database Error

Missing Required Field

Allow re-import of failed rows.

---

# Import History

Show

File Name

Uploaded By

Imported Date

Total Rows

Imported

Updated

Skipped

Failed

Duration

Status

Download Original CSV

Download Failed CSV

---

# Filament UI

Beautiful Filament v4 pages.

Use

Wizard

Table

Stats

Widgets

Progress

Badges

Notifications

Bulk Actions

Filters

Global Search

Dark Mode compatible

---

# Search

Search by

Phone

Name

Email

Custom Field

Facebook Form Name

Campaign

Ad Set

Ad Name

---

# Filters

Campaign

Form

Date

Status

Assigned User

Import Batch

---

# Facebook Information

Support optional fields

Campaign

Campaign ID

Ad Set

Ad Set ID

Ad

Ad ID

Lead ID

Created Time

Platform

Page Name

Form Name

---

# Performance

Handle

100,000+ rows

Chunk Reading

Batch Inserts

Queues

Lazy Collections

Indexes

Optimized Queries

---

# Security

Authorization

Policies

Validation

Rate Limiting

Mass Assignment Protection

SQL Injection Protection

CSV Formula Injection Protection

---

# Logging

Log every import.

Log errors.

Log duplicate actions.

Log user actions.

---

# Documentation

At the end generate

Flow Diagram

Import Workflow

---

# Code Generation Rules

Generate everything step-by-step.

Do NOT jump.

For every step provide

1. Explanation

2. Artisan Commands (only commands, never execute)

3. Files to Create

4. Full Code

5. Why this approach

Wait for my confirmation before moving to the next step.

Never skip any production-level implementation detail.
