---
phase: 05-email-notifications
plan: "03"
subsystem: admin
tags: [php, admin, coordinator, email, crud, form]

# Dependency graph
requires:
  - "email column in users table (05-01)"
provides:
  - "Optional email field on coordinator create form with validation"
  - "GET+POST /admin/coordinators/{id}/edit-email handler and template"
  - "coordinators list shows email status and edit link per active coordinator"
affects:
  - "src/templates/admin/coach_form.php"
  - "src/admin/coordinator_create_handler.php"
  - "src/admin/coordinator_edit_email_handler.php (new)"
  - "src/templates/admin/coordinator_edit_email.php (new)"
  - "src/admin/coordinators_handler.php"

# Tech stack
added: []
patterns:
  - "PRG redirect with ?success= banner after email save"
  - "Ownership check: role='coordinator' on both SELECT and UPDATE"
  - "Nullable email: empty string → NULL in DB"

# Key files
created:
  - src/admin/coordinator_edit_email_handler.php
  - src/templates/admin/coordinator_edit_email.php
modified:
  - src/templates/admin/coach_form.php
  - src/admin/coordinator_create_handler.php
  - src/admin/coordinators_handler.php

# Decisions
key-decisions:
  - "Email field is optional on create form; empty string saved as NULL in DB"
  - "Edit-email route /admin/coordinators/{id}/edit-email added to router in plan 05-05"
  - "PRG redirect to /admin/coordinators?success=... after successful email update"
  - "Coordinators list shows Keine-E-Mail warning (bi-envelope-x) for coordinators without email"

# Metrics
duration_seconds: 151
completed_date: "2026-07-13"
tasks_completed: 2
files_created: 2
files_modified: 3
---

# Phase 05 Plan 03: Admin Coordinator Email Management Summary

**One-liner:** Admin create form + dedicated edit-email page for coordinator email management, with PRG redirect and email/no-email indicator in coordinators list.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Add email field to coordinator create form + handler | 4d5e358 | coach_form.php, coordinator_create_handler.php |
| 2 | Coordinator edit-email page + update coordinators list | a479dad | coordinator_edit_email_handler.php (new), coordinator_edit_email.php (new), coordinators_handler.php |

## What Was Built

### Task 1 — Email field on coordinator create form

**`src/templates/admin/coach_form.php`:**
- Fixed pre-existing route bug: `action="/admin/coaches/create"` → `action="/admin/coordinators/create"`
- Fixed pre-existing cancel link: `href="/admin/coaches"` → `href="/admin/coordinators"`
- Added optional email input (`id="coordinator_email"`, `name="email"`) after the Team select, before button row
- Label: "E-Mail-Adresse (optional)", form-text: "Wird für die Koordinatoren-Benachrichtigung genutzt. Nur für den Admin sichtbar."

**`src/admin/coordinator_create_handler.php`:**
- Extracts `$email_raw = trim($_POST['email'] ?? '');`
- Validates with `FILTER_VALIDATE_EMAIL` when non-empty
- INSERT statement now includes `email` column; saves `$email_raw !== '' ? $email_raw : null`

### Task 2 — Edit-email page + coordinators list update

**`src/admin/coordinator_edit_email_handler.php` (new):**
- `require_admin()` guard, `require_csrf()` on POST
- Ownership check: `WHERE id = ? AND role = 'coordinator'` on both SELECT and UPDATE
- Email validation (FILTER_VALIDATE_EMAIL, max 255 chars)
- PRG redirect to `/admin/coordinators?success=...` on save
- Renders `coordinator_edit_email.php` template via `render_admin_page()`

**`src/templates/admin/coordinator_edit_email.php` (new):**
- Back link to /admin/coordinators
- Error/success alert banners
- Email form with `csrf_field()`, int-cast coordinator ID in action
- Copywriting matches UI spec (Screen 5)

**`src/admin/coordinators_handler.php`:**
- SELECT now includes `u.email`
- `$success` variable extracted from `?success=` query param
- `render_admin_page()` closure now uses `$success`; renders success banner
- Active coordinator list items show email address (bi-envelope icon) or "Keine E-Mail" warning (bi-envelope-x, text-warning)
- "E-Mail" edit button (`btn-outline-primary`) with link to `/admin/coordinators/{id}/edit-email`

## Route Note

The route `/admin/coordinators/{id}/edit-email` dispatching to `coordinator_edit_email_handler.php` is added in plan **05-05** (router update). The handler and template are ready; they will be unreachable until the router is updated.

## Email Validation Rules

- Empty string → allowed (email is optional); saved as NULL in DB
- Non-empty → must pass `filter_var($value, FILTER_VALIDATE_EMAIL)`
- Non-empty → max 255 characters (mb_strlen check)
- Invalid → re-renders form with error message (coordinator create uses redirect with error; edit-email re-renders inline)

## Pre-Existing Bugs Fixed

| Bug | File | Fix |
|-----|------|-----|
| `action="/admin/coaches/create"` wrong route | coach_form.php | Changed to `/admin/coordinators/create` |
| `href="/admin/coaches"` wrong cancel link | coach_form.php | Changed to `/admin/coordinators` |

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — all email fields are wired to real DB columns (users.email). No placeholder data.

## Self-Check: PASSED
- `/Users/sebastianwiller/Documents/github/team-manager/src/admin/coordinator_edit_email_handler.php` — EXISTS
- `/Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/coordinator_edit_email.php` — EXISTS
- Commit 4d5e358 — EXISTS (Task 1)
- Commit a479dad — EXISTS (Task 2)
