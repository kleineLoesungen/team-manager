---
phase: 05-email-notifications
plan: "04"
subsystem: notifications
tags: [php, email, coordinator, admin, notify, prg]

# Dependency graph
requires:
  - "send_notification_email() from src/utils/email_composer.php (05-01)"
  - "compose_list_notification_body(), compose_file_notification_body(), compose_admin_notification_body() (05-01)"
  - "email VARCHAR(255) NULL in users table (05-01)"
provides:
  - "GET/POST /coordinator/lists/{id}/notify — list review + send handler"
  - "GET/POST /coordinator/files/{id}/notify — file review + send handler"
  - "GET/POST /admin/notify — admin notify coordinators handler"
  - "src/templates/coordinator/list_notify.php — Screen 2 review template"
  - "src/templates/coordinator/file_notify.php — Screen 2b review template"
  - "src/templates/admin/notify_coordinators.php — Screen 4 admin template"
affects:
  - 05-05-routes-and-trigger-buttons

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Visibility-based recipient routing: private→coordinators, public/protected→members (D-02)"
    - "Role-aware content links: /member/{type}/{id} vs /coordinator/{type}/{id} based on visibility"
    - "Recipients re-fetched in POST block — no trust of GET-time cached state"
    - "PRG to originating page with ?notify_success= after successful send"
    - "Per-recipient body composition with compose_*_notification_body() before send"

key-files:
  created:
    - src/admin/notify_coordinators_handler.php
    - src/templates/admin/notify_coordinators.php
    - src/coordinator/list_notify_handler.php
    - src/templates/coordinator/list_notify.php
    - src/coordinator/file_notify_handler.php
    - src/templates/coordinator/file_notify.php
  modified: []

key-decisions:
  - "File notify uses team_id ownership check (WHERE id=? AND team_id=?) instead of can_view_list() — files table does not use visibility.php helper"
  - "subject_prefilled as '[Teamname] {content name}' — coordinator can edit before send"
  - "Admin success message stored after e() in handler; template outputs directly — consistent with coordinators_handler.php pattern"

requirements-completed: [EMAIL-02, EMAIL-03, EMAIL-04, EMAIL-05, EMAIL-06, EMAIL-07]

# Metrics
duration: 3min
completed: 2026-07-13
---

# Phase 5 Plan 04: Notification Send Flows Summary

**Three PRG handler+template pairs implementing list/file/admin notification: visibility-based recipient routing, per-recipient body composition, review page with mail preview and missing-email list**

## Performance

- **Duration:** ~3 min
- **Started:** 2026-07-13
- **Completed:** 2026-07-13
- **Tasks:** 3
- **Files created:** 6

## Accomplishments

- Created `src/admin/notify_coordinators_handler.php` — cross-team coordinator fetch, free-form message send, PRG to `/admin/notify?success=`
- Created `src/templates/admin/notify_coordinators.php` — empty-state alert linking to coordinator management, warning list for missing-email coordinators, form with recipient count
- Created `src/coordinator/list_notify_handler.php` — can_view_list() ownership gate, visibility-based recipient routing, content link generation, per-recipient compose + send, PRG to `/coordinator/lists/{id}?notify_success=`
- Created `src/templates/coordinator/list_notify.php` — context card with visibility badge + date, subject/body form, static mail preview, missing-email alert, private visibility warning, send/cancel buttons
- Created `src/coordinator/file_notify_handler.php` — structurally identical to list handler; team_id ownership check on files table; compose_file_notification_body()
- Created `src/templates/coordinator/file_notify.php` — identical structure to list_notify.php; "Diese Datei ist privat" in visibility warning block; no date (files table minimal SELECT)

## Task Commits

Each task was committed atomically:

1. **Task 1: Admin notify coordinators handler + template** - `6b540b3` (feat)
2. **Task 2: Coordinator list notify handler + template** - `beb38b8` (feat)
3. **Task 3: Coordinator file notify handler + template** - `0a514ac` (feat)

## Files Created

| File | Purpose |
|------|---------|
| `src/admin/notify_coordinators_handler.php` | GET form + POST send for admin→coordinators notification |
| `src/templates/admin/notify_coordinators.php` | Admin notify template — UI spec Screen 4 |
| `src/coordinator/list_notify_handler.php` | GET review page + POST send for list notifications |
| `src/templates/coordinator/list_notify.php` | Review page template — UI spec Screen 2 |
| `src/coordinator/file_notify_handler.php` | GET review page + POST send for file notifications |
| `src/templates/coordinator/file_notify.php` | Review page template — UI spec Screen 2b |

## Variable Contracts per Template

**notify_coordinators.php:** `$with_email` (array of coordinator rows), `$without_email` (array), `$error` (string), `$success` (string, already e()-d from handler)

**list_notify.php:** `$list` (id, name, visibility, date), `$with_email`, `$without_email`, `$subject_prefilled` (string), `$content_link` (full URL string)

**file_notify.php:** `$file` (id, name, visibility), `$with_email`, `$without_email`, `$subject_prefilled`, `$content_link`

## Visibility Routing Rule (D-02)

```
list/file.visibility = 'public'    → recipients = active members with email, link = /member/{type}/{id}
list/file.visibility = 'protected' → recipients = active members with email, link = /member/{type}/{id}
list/file.visibility = 'private'   → recipients = active coordinators with email, link = /coordinator/{type}/{id}
```

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## Pending (Plan 05-05)

- Router entries: `/coordinator/lists/{id}/notify`, `/coordinator/files/{id}/notify`, `/admin/notify` not yet wired in `public/index.php`
- Trigger buttons on list_detail.php, file_detail.php not yet added
- `?notify_success=` banner on list detail and file detail pages not yet wired

---

## Self-Check: PASSED

All 6 files exist on disk. All 3 task commits verified in git log.

| Check | Result |
|-------|--------|
| src/admin/notify_coordinators_handler.php | FOUND |
| src/templates/admin/notify_coordinators.php | FOUND |
| src/coordinator/list_notify_handler.php | FOUND |
| src/templates/coordinator/list_notify.php | FOUND |
| src/coordinator/file_notify_handler.php | FOUND |
| src/templates/coordinator/file_notify.php | FOUND |
| Commit 6b540b3 (Task 1) | FOUND |
| Commit beb38b8 (Task 2) | FOUND |
| Commit 0a514ac (Task 3) | FOUND |
