---
phase: 05-email-notifications
plan: "02"
subsystem: member-profile
tags: [php, member, profile, email, form, pdo, csrf, prg]

# Dependency graph
requires:
  - "email column in users table (05-01)"
provides:
  - "GET /member/profile — loads member's current email from DB"
  - "POST /member/profile — validates, saves or clears email via PRG"
  - "profile.php template — email card with success/error banners"
affects:
  - "users table (email column reads/writes)"

# Tech stack
tech_stack:
  added: []
  patterns:
    - "PRG (Post/Redirect/Get) for form submission with ?success=1 / ?error=invalid_email"
    - "filter_var(FILTER_VALIDATE_EMAIL) for email validation"
    - "render_player_page() + closure for template rendering"
    - "NULL-able email: empty string maps to NULL on save"

# Key files
key_files:
  created:
    - path: "src/member/profile_handler.php"
      purpose: "GET reads email from DB; POST validates + saves (or clears) via prepared statement; PRG redirects"
    - path: "src/templates/member/profile.php"
      purpose: "Card layout with optional email input, form-text, success/error banners, CSRF field"
  modified: []

# Decisions
decisions:
  - "On validation error, user sees their previously-saved email (not the submitted invalid value) — avoids bookmarkable invalid values in URL; acceptable for optional field"
  - "Empty string maps to SQL NULL (cleared); non-empty must pass filter_var(FILTER_VALIDATE_EMAIL)"
  - "UPDATE restricted to role='member' row — defense-in-depth beyond session check"

# Metrics
metrics:
  duration_seconds: 80
  completed_date: "2026-07-13"
  tasks_completed: 2
  files_created: 2
  files_modified: 0
---

# Phase 5 Plan 02: Member Profile Page Summary

Member email profile page — card form for optional email save/clear with PRG validation pattern.

## What Was Built

Two new files implement the member profile page where members can view, update, or clear their optional email address. This is the member-facing counterpart to the email infrastructure added in 05-01.

### Handler variable contract

`$current_email` (string) — current email from DB, or empty string if NULL  
`$error` (string) — non-empty when `?error=invalid_email` is set  
`$success` (string) — non-empty when `?success=1` is set  

### Validation rules

1. Empty input — accepted; saves NULL to DB (clears email)
2. Non-empty input — must pass `filter_var($email_raw, FILTER_VALIDATE_EMAIL)` AND `mb_strlen($email_raw) <= 255`
3. Invalid input — redirects to `/member/profile?error=invalid_email` (PRG); user sees saved email in form

### Files created

| File | Purpose |
|------|---------|
| `src/member/profile_handler.php` | GET loads current email; POST validates + saves; PRG pattern |
| `src/templates/member/profile.php` | Email card UI per UI spec Screen 3; banners for success/error |

## Commits

| Task | Commit | Files |
|------|--------|-------|
| Task 1 — profile handler | c535051 | src/member/profile_handler.php |
| Task 2 — profile template | a50dedb | src/templates/member/profile.php |

## Note

Route `/member/profile` and nav item ("Profil" tab) are wired in plan 05-05. Until then, the handler and template exist but are unreachable via the router.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None. The form reads from and writes to the real `users.email` column. No hardcoded empty values flow to UI rendering.

## Self-Check: PASSED

- src/member/profile_handler.php — FOUND
- src/templates/member/profile.php — FOUND
- 05-02-SUMMARY.md — FOUND
- commit c535051 — FOUND
- commit a50dedb — FOUND
