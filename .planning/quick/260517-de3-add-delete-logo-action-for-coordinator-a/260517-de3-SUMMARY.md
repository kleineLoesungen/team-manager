---
phase: quick-260517-de3
plan: "01"
subsystem: coordinator-logo / admin-settings / coordinator-nav
tags: [logo, delete, coordinator, admin, nav]
dependency_graph:
  requires: [260517-auz]
  provides: [LOGO-DELETE-01, LOGO-DELETE-02, LOGO-NAV-01]
  affects: [coordinator-logo-page, admin-settings-page, coordinator-nav]
tech_stack:
  added: []
  patterns: [POST action dispatch, CSRF-protected delete forms, PRG pattern]
key_files:
  created: []
  modified:
    - src/coordinator/logo_handler.php
    - src/templates/coordinator/logo.php
    - src/admin/settings_handler.php
    - src/templates/coordinator/layout.php
decisions:
  - "Call require_csrf() once at top of POST block (before action dispatch) so all branches are CSRF-protected without duplication"
  - "Delete branch fires before upload branch to allow clean action=delete_logo routing"
metrics:
  duration: "~8 minutes"
  completed: "2026-05-17T07:41:58Z"
  tasks_completed: 3
  files_modified: 4
---

# Phase quick-260517-de3 Plan 01: Add Delete-Logo Actions + Coordinator Nav Logo Link Summary

**One-liner:** CSRF-protected delete-logo POST actions for coordinator (NULLs teams.logo_path) and admin (removes settings row), plus Logo nav item in coordinator sidebar and mobile tabs.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Coordinator delete-logo action + template button | f6fc8db | src/coordinator/logo_handler.php, src/templates/coordinator/logo.php |
| 2 | Admin delete-default-logo action + settings button | 7fd8419 | src/admin/settings_handler.php |
| 3 | Coordinator nav Logo link (sidebar + mobile tabs) | 0731cb1 | src/templates/coordinator/layout.php |

## What Was Built

**Coordinator logo delete (Task 1):**
- Added `action=delete_logo` branch in `logo_handler.php` inside the existing `POST` block, firing before the upload branch
- Branch unlinks the file from disk (if it exists) and runs `UPDATE teams SET logo_path = NULL WHERE id = ?`
- Redirects to `/coordinator/logo?deleted=1` (PRG pattern)
- Added `$deleted` variable; passed into `render_coach_page` closure alongside existing vars
- Changed `render_coach_page` active param from `'members'` to `'logo'`
- In `logo.php`: added "Logo gelöscht." alert for `$deleted`, and a delete form (CSRF token + `action=delete_logo` hidden input + trash icon button with inline JS confirm) shown only when `$current_logo` is set, placed between the current-logo `<img>` and the upload form

**Admin default-logo delete (Task 2):**
- Added `action=delete_default_logo` branch at top of POST block in `settings_handler.php`, before the `$app_title` processing
- Branch fetches existing path from `settings` table, unlinks file, runs `DELETE FROM settings WHERE key = 'default_team_logo'`
- Redirects to `/admin/settings?logo_deleted=1`
- Added `$logo_deleted` variable; passed into `render_admin_page` closure
- In inline template: added "Standard-Logo gelöscht." alert for `$logo_deleted`, and a separate delete form (CSRF + `action=delete_default_logo` + confirm dialog) shown only when `$default_logo` is set, placed after the current logo `<img>` preview

**Coordinator nav Logo link (Task 3):**
- Added "Logo" `<li>` nav item with `bi-image` icon to desktop sidebar after the Statistik item
- Added "Logo" `<a>` tab to mobile tab bar after Statistik
- Both use `$active === 'logo'` for active highlighting; active param was updated in Task 1

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- `src/coordinator/logo_handler.php` modified: confirmed (commit f6fc8db)
- `src/templates/coordinator/logo.php` modified: confirmed (commit f6fc8db)
- `src/admin/settings_handler.php` modified: confirmed (commit 7fd8419)
- `src/templates/coordinator/layout.php` modified: confirmed (commit 0731cb1)
