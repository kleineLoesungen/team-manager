---
phase: 09-ui-vereinheitlichung
plan: "07"
subsystem: ui
tags: [php, bootstrap, partials, templates, admin]

# Dependency graph
requires:
  - phase: 09-02
    provides: render_page(), render_empty(), render_badge(), render_collection_group(), render_flash() partials infrastructure

provides:
  - 11 admin templates (team + coordinator + column management) migrated to UI partial system with zero violations
  - Bestätigungsseite pattern applied to column_delete_confirm and column_merge_confirm
  - credential_modal.php confirmed untouched (Pitfall 3 respected)

affects: [09-07b]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "render_collection_group() used for active/inactive team grouping in admin dashboard"
    - "render_empty() replaces text-center py-5 empty states"
    - "render_badge('ok'/'dim') replaces raw badge HTML for team/column status"
    - "render_flash() replaces raw alert divs for error and success feedback"
    - "Bestätigungsseite: btn-danger w-100 mb-2 + btn-outline-secondary w-100 cancel — stacked full-width, no JS confirm"

key-files:
  created: []
  modified:
    - src/templates/admin/dashboard.php
    - src/templates/admin/team_create.php
    - src/templates/admin/team_edit.php
    - src/templates/admin/team_form.php
    - src/templates/admin/coach_form.php
    - src/templates/admin/coordinator_settings.php
    - src/templates/admin/coordinator_edit_email.php
    - src/templates/admin/columns.php
    - src/templates/admin/column_delete_confirm.php
    - src/templates/admin/column_merge_confirm.php
    - src/templates/admin/column_rename.php

key-decisions:
  - "credential_modal.php excluded — renders via handler include with Cache-Control: no-store before HTML, cannot be wrapped in render_page layout"
  - "Bootstrap collapse for inactive teams removed in favor of plain render_collection_group — simpler, no JS dependency, consistent with UI-BASELINE"
  - "column_merge_confirm: border-warning card changed to border-danger and btn-warning changed to btn-danger — merge is destructive (deletes source column), red danger framing is correct"

patterns-established:
  - "Admin confirmation pages: stacked full-width btn-danger + btn-outline-secondary cancel, no shadow-sm, no max-width style"
  - "Admin flash messages: all raw <div class='alert'> replaced with render_flash() calls"

requirements-completed: []

# Metrics
duration: 31min
completed: "2026-09-13"
---

# Phase 09 Plan 07: Admin Team/Coordinator/Column Templates Migration Summary

**11 admin templates migrated to UI partial system — zero style violations, confirmation pages follow Bestätigungsseite archetype with btn-danger + cancel, credential_modal.php untouched**

## Performance

- **Duration:** 31 min
- **Started:** 2026-09-13T20:16:00Z
- **Completed:** 2026-09-13T20:47:03Z
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments

- dashboard.php: render_collection_group for active/inactive team groups, render_empty for zero-teams state, render_badge for Aktiv/Inaktiv status, render_flash for alerts — Bootstrap collapse for inactive group removed (simpler, no JS dependency)
- task_create.php, team_edit.php, team_form.php, coach_form.php: render_flash replaces raw alert divs; all inputs already had form-control without -sm
- coordinator_settings.php: form-control-sm removed from all 4 personal data inputs, form-select-sm removed from team select, style= max-width removed, team removal badge-button combo simplified to clean btn-sm btn-outline-secondary
- coordinator_edit_email.php: render_flash replaces raw alerts; no other violations present
- columns.php: render_empty for zero columns state, render_badge('dim') for Ja/Nein / Zahl type badges, shadow-sm + style= max-width removed from create form card, render_flash for all 6 flash conditions
- column_delete_confirm.php: Bestätigungsseite pattern — shadow-sm + style= max-width removed, form restructured to full-width btn-danger w-100 mb-2 confirm + btn-outline-secondary w-100 cancel
- column_merge_confirm.php: style= on card-header removed, btn-warning changed to btn-danger, cancel link added below confirm form, card border changed from border-warning to border-danger
- column_rename.php: style= max-width removed, render_flash for error
- credential_modal.php confirmed untouched throughout (Pitfall 3 respected)

## Task Commits

1. **Task 1: Migrate dashboard.php, team_create.php, team_edit.php, team_form.php, coach_form.php** - `e133704` (feat)
2. **Task 2: Migrate coordinator_settings.php, coordinator_edit_email.php, columns.php, column_delete_confirm.php, column_merge_confirm.php, column_rename.php** - `551e027` (feat)

**Plan metadata:** (pending final docs commit)

## Files Created/Modified

- `src/templates/admin/dashboard.php` — render_collection_group (active/inactive groups), render_empty (zero teams), render_badge (status), render_flash (alerts), style= removed
- `src/templates/admin/team_create.php` — render_flash replaces raw alert
- `src/templates/admin/team_edit.php` — render_flash replaces raw alert
- `src/templates/admin/team_form.php` — render_flash replaces raw alert
- `src/templates/admin/coach_form.php` — render_flash replaces raw alert
- `src/templates/admin/coordinator_settings.php` — form-control-sm/form-select-sm removed, style= max-width removed, render_flash, simplified team-chip buttons
- `src/templates/admin/coordinator_edit_email.php` — render_flash replaces raw alerts
- `src/templates/admin/columns.php` — render_empty, render_badge for type badges, shadow-sm + style= removed, render_flash for all flash conditions
- `src/templates/admin/column_delete_confirm.php` — Bestätigungsseite: shadow-sm + style= removed, btn-danger w-100 + cancel btn-outline-secondary w-100
- `src/templates/admin/column_merge_confirm.php` — style= on card-header removed, btn-warning → btn-danger, cancel link added, border-warning → border-danger
- `src/templates/admin/column_rename.php` — style= max-width removed, render_flash

## Decisions Made

- Bootstrap collapse for inactive teams removed — render_collection_group just wraps with a group label, no collapse toggle. Simpler, no Bootstrap JS dependency, consistent with UI-BASELINE preference for no-JS interactions.
- column_merge_confirm border/button changed from warning to danger — merging deletes the source column; red framing is appropriate for destructive confirmation.
- credential_modal.php explicitly excluded per Pitfall 3 — it sets Cache-Control: no-store header before HTML output and is included directly by handlers, not via layout functions.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] column_merge_confirm.php had no cancel link near the confirm button**
- **Found during:** Task 2 (column_merge_confirm.php migration)
- **Issue:** The plan specifies "Confirm button: btn-danger, cancel: btn-outline-secondary or text link" — only a back link at the top existed, not near the form.
- **Fix:** Added `<a href="/admin/columns" class="btn btn-outline-secondary w-100">Abbrechen</a>` below the form inside the card body.
- **Files modified:** src/templates/admin/column_merge_confirm.php
- **Committed in:** 551e027 (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (Rule 2 — missing required UI element)
**Impact on plan:** Fix aligns the confirmation page exactly with the Bestätigungsseite archetype. No scope creep.

## Known Stubs

None — all templates have live data from handler variables.

## Issues Encountered

None — all files passed php -l, zero style= violations confirmed, credential_modal.php untouched.

## Next Phase Readiness

- 11 of the admin templates in this plan are migrated with zero violations
- Remaining admin templates (members.php, member_form.php, member_edit.php, clubs.php, club_form.php, club_edit.php, attributes.php, notify_coordinators.php) are handled in plan 09-07b
- Pattern is consistent: confirmation pages use btn-danger w-100 + cancel btn-outline-secondary w-100, all status badges use render_badge(), all flash messages use render_flash()

---
*Phase: 09-ui-vereinheitlichung*
*Completed: 2026-09-13*
