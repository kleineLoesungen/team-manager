---
phase: 09-ui-vereinheitlichung
plan: "06"
subsystem: ui
tags: [php, bootstrap, partials, templates, member]

# Dependency graph
requires:
  - phase: 09-01
    provides: render_page(), render_empty(), render_badge(), render_collection_group(), render_flash(), render_matrix_table(), render_action_bar() partials infrastructure
  - phase: 09-02
    provides: Tracer pattern for mass migration — proven violation checklist
affects: [09-07, 09-08, 09-09]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "render_matrix_table() wraps all EAV cell tables and stats tables — both list_detail.php and stats.php"
    - "render_collection_group() for calendar date-group headers in lists.php"
    - "render_empty() replaces all text-center py-5 and py-4 empty states across member templates"
    - "render_badge() replaces all raw badge HTML for visibility, status, and identity badges"
    - "render_action_bar() added to list_row_form.php for sticky primary action"
    - "render_flash() replaces inline alert divs; profile.php uses $success/$error vars, others use ?success=1"
    - "confirm_profile.php no-player state: csrf_field() captured as string, passed as $action_html to render_empty()"

key-files:
  created: []
  modified:
    - src/templates/member/lists.php
    - src/templates/member/list_detail.php
    - src/templates/member/list_row_form.php
    - src/templates/member/ticker_list.php
    - src/templates/member/ticker_detail.php
    - src/templates/member/file_detail.php
    - src/templates/member/stats.php
    - src/templates/member/profile.php
    - src/templates/member/member_profile.php
    - src/templates/member/coordinators.php
    - src/templates/member/confirm_profile.php

key-decisions:
  - "render_matrix_table() used for list_detail.php member EAV table with tfoot totals row via footer_body callable"
  - "render_matrix_table() used for stats.php both time-window summary table and per-list breakdown table"
  - "Pre-compute col_totals before render_matrix_table() call in list_detail.php to avoid double-loop inside closure"
  - "confirm_profile.php no-player empty state: csrf_field() returns string, safe to concatenate as $action_html"
  - "list_row_form.php: render_action_bar() added, inline submit button replaced with sticky action bar, form gets id=row-form"
  - "ticker_detail.php JS-confirm on delete kept in-place — proper confirm page is an architectural change (out of scope for checklist)"

patterns-established:
  - "All 11 member templates: zero inline style= violations, zero py-5/mb-5/shadow-sm/form-control-sm"
  - "render_matrix_table() is the canonical way to render EAV cell tables and stats tables in member templates"
  - "calendar date groups in lists.php use render_collection_group() — same pattern as coordinator/lists.php"

requirements-completed: []

# Metrics
duration: 20min
completed: "2026-09-13"
---

# Phase 09 Plan 06: Member Templates Migration Summary

**All 11 member-role templates migrated to partials system — zero inline style, py-5, shadow-sm, or form-control-sm violations; render_matrix_table() adopted for EAV cell and stats tables**

## Performance

- **Duration:** 20 min
- **Started:** 2026-09-13T06:17:07Z
- **Completed:** 2026-09-13T06:37:26Z
- **Tasks:** 2
- **Files modified:** 11

## Accomplishments

- All 11 member templates pass `php -l` with zero violations across all checklist categories
- lists.php: calendar date groups converted to render_collection_group(), all visibility badges use render_badge(), calendar/list empty states use render_empty(), shadow-sm removed from all cards
- list_detail.php: full EAV cell table wrapped in render_matrix_table() including dynamic headers (show_all_rows conditional) and totals tfoot via footer_body callable
- stats.php: both time-window summary and per-list breakdown tables wrapped in render_matrix_table()
- list_row_form.php: render_action_bar('Zeile speichern', 'row-form') added, switch inline style removed
- confirm_profile.php: no-player empty state converted to render_empty() with csrf action HTML assembled as string
- member_profile.php: shadow-sm removed from all cards, status badges use render_badge()
- profile.php and confirm_profile.php: inline alert divs replaced with render_flash()

## Task Commits

1. **Task 1: Migrate lists.php, list_detail.php, list_row_form.php, ticker_list.php, ticker_detail.php, file_detail.php** - `1f5ae17` (feat)
2. **Task 2: Migrate stats.php, profile.php, member_profile.php, coordinators.php, confirm_profile.php** - `4d700eb` (feat)

## Files Created/Modified

- `src/templates/member/lists.php` — render_collection_group() for calendar date groups, render_badge() visibility, render_empty() empty states, shadow-sm removed
- `src/templates/member/list_detail.php` — render_matrix_table() for EAV cell table + tfoot totals, render_badge('info','Ich'), render_empty() for no-members
- `src/templates/member/list_row_form.php` — shadow-sm removed, switch inline style removed, render_action_bar() added
- `src/templates/member/ticker_list.php` — render_empty() and render_badge() for status
- `src/templates/member/ticker_detail.php` — render_empty() for no-messages, render_badge() for status
- `src/templates/member/file_detail.php` — shadow-sm removed, inline styles (min-height, resize) removed, render_badge() for visibility
- `src/templates/member/stats.php` — render_matrix_table() for both stats tables (summary + per-list)
- `src/templates/member/profile.php` — render_flash() for success/error, render_empty() for no-player state, py-5 removed
- `src/templates/member/member_profile.php` — shadow-sm removed, render_empty() for not-linked state, render_badge() for team status
- `src/templates/member/coordinators.php` — render_empty() for no-coordinators, inline style removed
- `src/templates/member/confirm_profile.php` — render_flash() for error, render_empty() with csrf string for no-player state

## Decisions Made

- Pre-compute `$col_totals` before calling render_matrix_table() in list_detail.php to keep the footer_body closure clean — avoids re-computing inside closure
- confirm_profile.php no-player state: since csrf_field() returns a string, it can be concatenated directly as $action_html for render_empty() — no ob_start needed
- list_row_form.php: added render_action_bar() since UI-BASELINE requires sticky primary action; form gets id="row-form", submit button moved to action bar
- ticker_detail.php JS confirm on delete (onclick="return confirm(...)") left in-place — converting to a proper confirm page is an architectural change out of scope for this checklist plan

## Deviations from Plan

### Out-of-scope discovery

**[Rule 4 scope] ticker_detail.php: JS confirm on delete button**
- **Found during:** Task 1
- **Issue:** `onclick="return confirm('Nachricht löschen?')"` violates UI-BASELINE "kein JS-Confirm" rule
- **Decision:** NOT fixed — adding a proper confirmation page requires a new route, handler, and template (architectural change, Rule 4 boundary)
- **Deferred to:** `.planning/phases/09-ui-vereinheitlichung/deferred-items.md`

---

**Total deviations:** 1 out-of-scope discovery (not auto-fixed; deferred)
**Impact on plan:** All checklist violations fixed. JS-confirm deferred — it is a pre-existing UX violation not introduced by this plan.

## Known Stubs

None — all templates render real data from handler-provided variables.

## Issues Encountered

None — plan executed without blocking issues.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- All 11 member templates: zero violations, all partials used consistently
- Pattern established for member EAV table (render_matrix_table with tfoot), same as coordinator pattern
- Phase 09 can proceed with plans 09-07 through 09-09 (admin and public templates)

## Self-Check: PASSED

- SUMMARY.md exists: FOUND
- Commit 1f5ae17 (Task 1): FOUND
- Commit 4d700eb (Task 2): FOUND
- Style violations across all 11 files: 0
- Spacing/shadow violations across all 11 files: 0
- render_matrix_table in list_detail.php: 2 (>= 1)
- render_matrix_table in stats.php: 2 (>= 1)
- render_badge in lists.php: 6 (>= 1)
- render_empty in lists.php: 2 (>= 1)
- form-control-sm in profile.php: 0

---
*Phase: 09-ui-vereinheitlichung*
*Completed: 2026-09-13*
