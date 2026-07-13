---
phase: quick-260713-lve
plan: "01"
subsystem: coordinator/members
tags: [email, coordinator, members, phase5-prep]
dependency_graph:
  requires: [users.email column (added in Phase 5 Plan 01)]
  provides: [/coordinator/members/{id}/edit-email route, email visible in member cards]
  affects: [src/coordinator/members_handler.php, src/templates/coordinator/members.php]
tech_stack:
  added: []
  patterns: [triple-constraint ownership check (id + team_id + role='member'), POST-redirect-GET, coordinator layout render_coach_page]
key_files:
  created:
    - src/coordinator/member_edit_email_handler.php
    - src/templates/coordinator/member_edit_email.php
  modified:
    - public/index.php
    - src/coordinator/members_handler.php
    - src/templates/coordinator/members.php
decisions:
  - "Triple-constraint WHERE clause (id + role='member' + team_id) on both SELECT and UPDATE to prevent cross-team access without relying solely on RLS"
  - "Route placed before deactivate|reactivate|reset-password pattern in index.php to avoid regex match interference"
  - "Email shown in card body only when set (non-empty), to avoid clutter for members without email"
  - "flex-wrap added to card-footer d-flex to prevent button overflow on small screens"
metrics:
  duration: "~8 minutes"
  completed: "2026-07-13"
  tasks_completed: 2
  files_created: 2
  files_modified: 3
---

# Phase quick-260713-lve Plan 01: Coordinator Edit Member Email Summary

**One-liner:** Coordinator can set or clear a member's email address via /coordinator/members/{id}/edit-email, with triple-constraint ownership, German validation, and success feedback on the members list.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create member_edit_email handler, template, and route | 5bd818b | src/coordinator/member_edit_email_handler.php, src/templates/coordinator/member_edit_email.php, public/index.php |
| 2 | Add email link to members list and surface success feedback | ef606d1 | src/coordinator/members_handler.php, src/templates/coordinator/members.php |

## What Was Built

### New Route: /coordinator/members/{id}/edit-email

A GET+POST handler that lets a coordinator set or clear the email address of any member on their team:

- **GET:** Renders a form pre-filled with the current email (or empty if none set)
- **POST:** Validates format and length, stores email or NULL, redirects to /coordinator/members with a German success message
- **Security:** Triple-constraint ownership check `WHERE id = ? AND role = 'member' AND team_id = ?` on both the fetch and the UPDATE — a coordinator cannot access members from other teams, and attempting to do so silently redirects to /coordinator/members

### Updated Members List (/coordinator/members)

- Each active member card body now shows the email address when set
- Active and inactive card footers both have an "E-Mail" button linking to the edit page
- `?success=` query param is surfaced as a green alert banner at the top of the page
- Card footers changed to `d-flex gap-2 flex-wrap` to handle button overflow on small screens

## Decisions Made

| Decision | Rationale |
|----------|-----------|
| Triple-constraint on SELECT and UPDATE | Defense-in-depth: even if RLS is bypassed, cross-team access is blocked at the query level |
| Route before deactivate/reactivate/reset-password | Regex `edit-email` must match before the `(deactivate|reactivate|reset-password)` alternation group |
| Email shown only when non-empty | Avoids visual noise for members with no email yet |
| flex-wrap on card footers | Three buttons (reset-password, deactivate, E-Mail) can overflow on narrow screens |

## Deviations from Plan

None - plan executed exactly as written.

## Known Stubs

None — the email field is wired directly to the `users.email` column. The edit-email page reads the current value and the UPDATE persists it immediately.

## Self-Check: PASSED

- `src/coordinator/member_edit_email_handler.php` — created
- `src/templates/coordinator/member_edit_email.php` — created
- `public/index.php` — route added before deactivate/reactivate/reset-password
- `src/coordinator/members_handler.php` — email in SELECT, $success variable, passed to closure
- `src/templates/coordinator/members.php` — success alert, email in card body, E-Mail button (active + inactive)
- Commit 5bd818b — exists
- Commit ef606d1 — exists
