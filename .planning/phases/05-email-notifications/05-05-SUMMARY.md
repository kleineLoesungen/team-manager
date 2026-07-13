---
phase: 05-email-notifications
plan: "05"
subsystem: notifications
tags: [php, routing, nav, coordinator, member, admin, notify]

# Dependency graph
requires:
  - "list_notify_handler.php from 05-04"
  - "file_notify_handler.php from 05-04"
  - "notify_coordinators_handler.php from 05-04"
  - "profile_handler.php from 05-02"
  - "coordinator_edit_email_handler.php from 05-03"
provides:
  - "member nav: Profil tab (bi-person-circle, 'profile' active key)"
  - "admin nav: Benachrichtigung tab (bi-envelope, 'notify' active key)"
  - "list detail: notify button (active/disabled) with $has_notify_recipients guard"
  - "file detail: notify button (active/disabled) alongside back link"
  - "all 5 Phase 5 routes wired in public/index.php"
affects:
  - "all Phase 5 user flows are now end-to-end accessible"

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Visibility-based recipient check in GET handler: private→coordinators, public/protected→members"
    - "?notify_success= param handled separately from ?success= for success banner text"
    - "Route ordering: specific paths before catch-all regexes in match(true) block"

key-files:
  created: []
  modified:
    - src/templates/member/layout.php
    - src/templates/admin/layout.php
    - src/coordinator/list_detail_handler.php
    - src/templates/coordinator/list_detail.php
    - src/coordinator/file_detail_handler.php
    - src/templates/coordinator/file_detail.php
    - public/index.php

key-decisions:
  - "Notify button for file_detail wrapped in d-flex gap-2 flex-wrap alongside back link (no separate button row added)"
  - "has_notify_recipients computed in GET path before POST block — consistent with list_detail pattern; re-fetched implicitly on redirect"
  - "Free list guard (!$is_free_list) wraps the entire notify button block — no notify for free lists"

requirements-completed: [EMAIL-01, EMAIL-02, EMAIL-03, EMAIL-05, EMAIL-06, EMAIL-08]

# Metrics
duration: 5min
completed: 2026-07-13
---

# Phase 5 Plan 05: Routes and Trigger Buttons Summary

**Nav tabs, notify buttons, and all 5 Phase 5 routes wired — Phase 5 features are now fully accessible end-to-end**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-07-13
- **Completed:** 2026-07-13
- **Tasks:** 3
- **Files modified:** 7

## Accomplishments

- Updated `src/templates/member/layout.php` — added "Profil" nav item (bi-person-circle) after Statistik in both sidebar and mobile tabs
- Updated `src/templates/admin/layout.php` — added "Benachrichtigung" nav item (bi-envelope) after Einstellungen in both sidebar and mobile tabs
- Updated `src/coordinator/list_detail_handler.php` — computes `$has_notify_recipients` (visibility-based DB query), handles `?notify_success=` banner, passes bool to render closure
- Updated `src/templates/coordinator/list_detail.php` — notify button (active link or disabled button) in button row, guarded by `!$is_free_list`
- Updated `src/coordinator/file_detail_handler.php` — same recipient check pattern, `?notify_success=` handling, `$has_notify_recipients` in closure
- Updated `src/templates/coordinator/file_detail.php` — notify button alongside back link in `d-flex gap-2 flex-wrap` wrapper
- Updated `public/index.php` — all 5 Phase 5 routes added with correct ordering (specific before catch-all)

## Task Commits

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Nav layout updates | 6a8e7a5 | member/layout.php, admin/layout.php |
| 2 | Notify buttons on list/file detail | 43583cb | list_detail_handler.php, list_detail.php, file_detail_handler.php, file_detail.php |
| 3 | Router — all 5 new Phase 5 routes | 8bb67e9 | public/index.php |

## Route Order Summary

Routes added to `public/index.php` with ordering guarantee:

| Route | Line | Before (catch-all) | Line |
|-------|------|--------------------|------|
| `/coordinator/lists/{id}/notify` | 142 | `/coordinator/lists/{id}` | 149 |
| `/coordinator/files/{id}/notify` | 167 | `/coordinator/files/{id}` | 174 |
| `/admin/coordinators/{id}/edit-email` | 78 | `/admin/coordinators/{id}/(deactivate\|reactivate\|reset-password)` | 84 |
| `/admin/notify` | 75 | (no catch-all conflict) | — |
| `/member/profile` | 196 | (simple path match, no catch-all) | — |

## Notify Button State Logic

| Condition | UI |
|-----------|----|
| `!$is_free_list && $has_notify_recipients` | Active `<a href="/coordinator/lists/{id}/notify">` link, `btn-outline-primary` |
| `!$is_free_list && !$has_notify_recipients` | Disabled `<button>` with `title="Keine gültigen E-Mail-Adressen vorhanden"`, `btn-outline-secondary` |
| `$is_free_list` | Notify block not rendered at all |

**Recipient determination (server-side, computed at page load):**
- `visibility = 'private'` → check coordinators table for email
- `visibility = 'public'` or `'protected'` → check members table for email

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None. All nav links, buttons, and routes point to handlers created in plans 05-02 through 05-04.

---

## Self-Check: PASSED

| Check | Result |
|-------|--------|
| src/templates/member/layout.php — bi-person-circle present | FOUND |
| src/templates/admin/layout.php — bi-envelope present | FOUND |
| src/coordinator/list_detail_handler.php — has_notify_recipients | FOUND |
| src/templates/coordinator/list_detail.php — Benachrichtigung senden | FOUND |
| src/coordinator/file_detail_handler.php — has_notify_recipients | FOUND |
| src/templates/coordinator/file_detail.php — notify button | FOUND |
| public/index.php — list_notify_handler.php | FOUND |
| public/index.php — file_notify_handler.php | FOUND |
| public/index.php — notify_coordinators_handler.php | FOUND |
| public/index.php — coordinator_edit_email_handler.php | FOUND |
| public/index.php — member/profile | FOUND |
| Commit 6a8e7a5 (Task 1) | FOUND |
| Commit 43583cb (Task 2) | FOUND |
| Commit 8bb67e9 (Task 3) | FOUND |
