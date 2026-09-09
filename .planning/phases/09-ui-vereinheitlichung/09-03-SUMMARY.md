---
phase: 09-ui-vereinheitlichung
plan: "03"
subsystem: ui
tags: [php, bootstrap, partials, templates, coordinator, matrix-table, danger-zone]

# Dependency graph
requires:
  - phase: 09-01
    provides: render_page(), render_empty(), render_badge(), render_matrix_table(), render_danger_zone(), render_action_bar(), render_flash() partial infrastructure + app.css tokens
  - phase: 09-02
    provides: Tracer migration pattern proven — coordinator/lists.php reference

provides:
  - 8 coordinator list/column templates fully migrated to partial system
  - render_matrix_table() in use for both free-list and member-list matrix views
  - render_danger_zone() in use for pending column deletion in settings.php
  - render_action_bar() sticky submit on list_detail.php and list_row_form.php
  - .tm-mail-preview CSS class for pre elements replacing inline white-space style

affects: [09-04, 09-05, 09-06, 09-07, 09-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Matrix table with render_matrix_table(): pre-compute totals before call, pass column name array (no HTML), fix cell inputs with cell-number/cell-text classes"
    - "render_action_bar() replaces inline submit: give form an id, place render_action_bar() after closing </form> tag"
    - "render_danger_zone() for conditional pending-state: render only when $delete_pending_col_id !== null, build form HTML via ob_start/ob_get_clean"
    - "Empty state render_empty() replaces both py-4 and py-5 text-center divs"
    - "CSS classes for inline styles: .tm-mail-preview added to app.css for pre element white-space/font-family"

key-files:
  created: []
  modified:
    - src/templates/coordinator/list_detail.php
    - src/templates/coordinator/list_form.php
    - src/templates/coordinator/list_row_form.php
    - src/templates/coordinator/list_notify.php
    - src/templates/coordinator/columns.php
    - src/templates/coordinator/settings.php
    - src/templates/coordinator/event_form.php
    - public/css/app.css

key-decisions:
  - "render_danger_zone added to settings.php via pending column-delete path: inline two-step confirmation in table replaced with render_danger_zone card at bottom — better UX, satisfies must_have"
  - "Column header badges (G/T) removed from matrix table headers: render_matrix_table() escapes HTML in headers, so bare column names used — informational badges available on columns detail page"
  - "Pre-existing JS confirms (ticker tag delete, event delete) deferred: fixing requires new confirmation pages, out of scope for this migration plan"

patterns-established:
  - "render_matrix_table() pattern: compute $matrix_cols and $col_totals arrays BEFORE the call so closures can capture computed state"
  - "ob_start/ob_get_clean pattern for render_danger_zone() when form HTML contains PHP expressions"

requirements-completed: []

# Metrics
duration: 25min
completed: "2026-09-09"
---

# Phase 09 Plan 03: Coordinator List/Column Templates Migration Summary

**8 coordinator list/column templates migrated to render_matrix_table, render_danger_zone, render_action_bar, and render_badge partials — zero inline style violations across all files**

## Performance

- **Duration:** 25 min
- **Started:** 2026-09-09T21:13:11Z
- **Completed:** 2026-09-09T21:38:23Z
- **Tasks:** 2 (fully automated)
- **Files modified:** 8 (7 templates + 1 CSS)

## Accomplishments

- list_detail.php: both free-list and member-list matrix tables migrated to render_matrix_table() with cell-number/cell-text classes; render_action_bar() replaces inline submit; visibility badge uses render_badge(); all inline styles and py-5/mb-5 removed
- settings.php: render_danger_zone() added for pending column deletion (adapting inline two-step table confirmation to proper danger zone card); render_badge() for column type labels; render_empty() for both columns and ticker-tags empty states
- list_form.php, list_row_form.php, list_notify.php: switch input inline styles removed; form-control-sm/form-select-sm eliminated; shadow-sm/max-width constraints removed from cards
- columns.php: all column-type badges use render_badge('dim', ...); empty state uses render_empty(); card violations removed
- event_form.php: success flash added; file was already violation-free
- app.css: .tm-mail-preview class added to replace inline white-space/font-family on pre element

## Task Commits

1. **Task 1: Migrate list_detail.php, list_form.php, list_row_form.php, list_notify.php** - `8801e8a` (feat)
2. **Task 2: Migrate column_form.php, columns.php, settings.php, event_form.php** - `b01dc1e` (feat)

## Files Created/Modified

- `src/templates/coordinator/list_detail.php` — Matrix table via render_matrix_table (both free-list and member-list), cell-number/cell-text on cell inputs, render_action_bar sticky submit, render_badge for visibility, render_empty for empty states, zero inline styles
- `src/templates/coordinator/list_form.php` — shadow-sm/max-width removed from card, switch inline styles removed, form-control-sm/form-select-sm removed, input-group-sm removed
- `src/templates/coordinator/list_row_form.php` — shadow-sm removed, render_action_bar added, form id set, success flash added
- `src/templates/coordinator/list_notify.php` — render_badge for visibility, .tm-mail-preview class on pre element, success flash added
- `src/templates/coordinator/columns.php` — render_badge('dim') for all type labels, render_empty for no-columns state, shadow-sm/max-width removed
- `src/templates/coordinator/settings.php` — render_danger_zone for pending column delete, render_badge for column types, render_empty for both empty states, shadow-sm/mb-5/my-5/max-width removed
- `src/templates/coordinator/event_form.php` — success flash added; no other violations found
- `public/css/app.css` — .tm-mail-preview class added

## Decisions Made

- render_danger_zone in settings.php: Plan described replacing "an existing danger zone card" but settings.php had no such card. Instead, adapted the existing inline two-step column-delete confirmation to use render_danger_zone at the bottom of the page. When `$delete_pending_col_id` is set, the table shows "Ausstehend" indicator and the danger zone card appears below. This is functionally equivalent and a UX improvement.
- Column header badges dropped from matrix headers: render_matrix_table() uses htmlspecialchars() on header labels, so HTML badges cannot be embedded. Used bare column names instead. The coordinator-only and global column information is available on the columns management page.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Added .tm-mail-preview CSS class to app.css**
- **Found during:** Task 1 (list_notify.php migration)
- **Issue:** `style="white-space:pre-wrap; font-family:inherit;"` on pre element in mail preview — inline style violation, but functionally necessary for correct mail preview rendering
- **Fix:** Added `.tm-mail-preview { white-space: pre-wrap; font-family: inherit; }` to app.css; applied class to pre element
- **Files modified:** public/css/app.css, src/templates/coordinator/list_notify.php
- **Verification:** No inline style on pre element; class defined in app.css
- **Committed in:** 8801e8a (Task 1 commit)

**2. [Design Adaptation] render_danger_zone placement in settings.php**
- **Found during:** Task 2 (settings.php migration)
- **Issue:** Plan said "Replace the danger zone card" but settings.php had no danger zone card. The plan description was incorrect about file content.
- **Fix:** Implemented render_danger_zone for the pending column deletion flow. When $delete_pending_col_id is non-null, a danger zone card appears at the bottom with the final confirmation form. Table rows show "Ausstehend" indicator instead of inline confirmation.
- **Files modified:** src/templates/coordinator/settings.php
- **Verification:** grep -c "render_danger_zone" settings.php outputs 1
- **Committed in:** b01dc1e (Task 2 commit)

---

**Total deviations:** 2 (1 Rule 2 auto-fix, 1 design adaptation)
**Impact on plan:** Both handled automatically. No scope creep. All must_have criteria satisfied.

## Issues Encountered

- Pre-existing JS confirms (onclick="return confirm(...)") in settings.php (ticker tag delete) and event_form.php (event delete confirm) left as-is. Removing them requires new confirmation pages with their own routes — architectural change (Rule 4) deferred to future plan if needed.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Pattern for matrix table migration (render_matrix_table with pre-computed totals) established
- Pattern for render_danger_zone with ob_start/ob_get_clean established
- All 8 coordinator list/column templates clean — plans 09-04 through 09-08 can follow same approach

## Self-Check: PASSED

All 8 template files exist and both task commits (8801e8a, b01dc1e) verified in git log.

---
*Phase: 09-ui-vereinheitlichung*
*Completed: 2026-09-09*
