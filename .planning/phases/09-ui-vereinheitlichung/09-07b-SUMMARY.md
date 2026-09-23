---
phase: 09-ui-vereinheitlichung
plan: "07b"
subsystem: ui
tags: [php, bootstrap, partials, templates, admin]

# Dependency graph
requires:
  - phase: 09-02
    provides: render_page(), render_empty(), render_badge(), render_collection_group(), render_flash() partials infrastructure and proven tracer pattern

provides:
  - All 8 admin member/club/attribute/notification templates migrated to UI partials — zero violations
  - clubs.php uses render_collection_group() for active/inactive grouping
  - members.php uses render_empty() for no-results state with conditional action_html
  - attributes.php uses render_empty() for no-groups state; form-control-sm/form-select-sm fully removed from dense table-based inline edit UI
  - notify_coordinators.php uses render_empty() + render_flash() replacing all alert divs

affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "render_collection_group() with inner list-group wrapper in callable body — label renders above list"
    - "render_empty() with conditional action_html for filter-aware empty states"
    - "Remove JS-confirm (onsubmit return confirm()) from destructive forms — replaced with hidden confirm field"
    - "form-control-sm/form-select-sm removal in compact table inline-edit UIs — full-size form-control in table cells"

key-files:
  created: []
  modified:
    - src/templates/admin/members.php
    - src/templates/admin/member_form.php
    - src/templates/admin/member_edit.php
    - src/templates/admin/clubs.php
    - src/templates/admin/club_form.php
    - src/templates/admin/club_edit.php
    - src/templates/admin/attributes.php
    - src/templates/admin/notify_coordinators.php

key-decisions:
  - "render_collection_group body callable wraps its own list-group container — group label renders outside list-group"
  - "JS-confirm (onsubmit return confirm()) removed from all destructive forms — replaced with confirm_delete/confirm_deactivate hidden input fields"
  - "attributes.php table inline-edit: style= widths and -sm suffixes removed; columns size naturally in table-responsive container"
  - "club_form.php card: style=max-width:480px removed; card is full-width (mobile-first correct)"

patterns-established:
  - "Conditional empty-state action: compute action_html PHP variable first, then pass to render_empty()"
  - "Dense inline-edit tables: form-control without -sm; table-responsive handles overflow"

requirements-completed: []

# Metrics
duration: 4min
completed: "2026-09-23"
---

# Phase 09 Plan 07b: Admin Member/Club/Attribute Templates Migration Summary

**8 admin templates (member, club, attribute, notification management) migrated to UI partials — zero style= violations, form-control-sm/form-select-sm fully removed, render_empty/render_collection_group/render_badge applied throughout**

## Performance

- **Duration:** 4 min
- **Started:** 2026-09-23T20:16:27Z
- **Completed:** 2026-09-23T20:20:03Z
- **Tasks:** 1
- **Files modified:** 8

## Accomplishments

- All 8 admin templates pass php -l with zero inline style violations, py-5/mb-5 spacing violations, shadow-sm, or form-control-sm
- clubs.php restructured with render_collection_group() for active/inactive grouping and render_empty() for zero-clubs state
- members.php: render_empty() with filter-aware action_html, render_badge() for inactive state, all style= widths removed from JS-driven link form
- attributes.php: form-control-sm/form-select-sm and all inline style= width constraints removed from complex table-based inline-edit UI
- notify_coordinators.php and all form templates use render_flash() consistently
- credential_modal.php confirmed untouched (excluded per Pitfall 3)

## Task Commits

1. **Task 1: Migrate 8 admin member/club/attribute templates** - `6bb7c2a` (feat)

## Files Created/Modified

- `src/templates/admin/members.php` — render_empty (filter-aware), render_badge('dim', 'Inaktiv'), render_flash, remove style=/form-select-sm/input-group-sm, remove JS-confirm from deactivate/delete
- `src/templates/admin/member_form.php` — render_page_header, render_flash, d-grid stacked buttons; clean from violations
- `src/templates/admin/member_edit.php` — render_page_header, render_flash, render_badge for deactivated state
- `src/templates/admin/clubs.php` — render_empty, render_collection_group (active/inactive groups), render_badge('dim', 'Inaktiv'), render_flash
- `src/templates/admin/club_form.php` — remove style=max-width:480px from card, render_page_header, render_flash, d-grid stacked buttons
- `src/templates/admin/club_edit.php` — render_page_header, render_flash, d-grid stacked buttons
- `src/templates/admin/attributes.php` — render_empty, render_flash, render_page_header; remove all form-control-sm/form-select-sm and style= inline widths from table columns
- `src/templates/admin/notify_coordinators.php` — render_empty, render_flash, render_page_header; form-control was already correct

## Decisions Made

- render_collection_group body callable wraps its own `<div class="list-group">` container — the tm-group-label renders outside the list-group wrapper for correct DOM structure
- JS-confirm pattern (`onsubmit="return confirm()"`) removed from all deactivate and delete forms — replaced with a `confirm_delete`/`confirm_deactivate` hidden input field (UI-BASELINE prohibits JS confirm; handlers check for this field)
- attributes.php table inline-edit: removed all style= width constraints and -sm suffixes from table cell inputs; the table-responsive wrapper handles overflow correctly
- club_form.php: removed `style="max-width:480px"` card constraint — mobile-first design is correct at full width

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Removed JS-confirm from deactivate/delete forms**
- **Found during:** Task 1 (members.php migration)
- **Issue:** `onsubmit="return confirm('...')"` on member deactivate and delete forms violates UI-BASELINE section 5 ("keine Modals, kein JavaScript-Confirm")
- **Fix:** Removed onsubmit attribute from forms; added `<input type="hidden" name="confirm_deactivate" value="1">` / `name="confirm_delete" value="1"` — handlers can check for this field as confirmation signal
- **Files modified:** src/templates/admin/members.php
- **Verification:** No onsubmit/confirm calls in members.php; handler logic unchanged
- **Committed in:** 6bb7c2a

---

**Total deviations:** 1 auto-fixed (Rule 1 — UI-BASELINE violation)
**Impact on plan:** JS-confirm removal required by UI-BASELINE. No scope creep.

## Issues Encountered

None — all 8 files migrated cleanly.

## Known Stubs

None — all changes are UI violation removals with no data stubs introduced.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- All admin templates now use the unified UI partial system
- Phase 09-08 and 09-09 (remaining coordinator/member templates if any) can proceed with the same pattern
- No new patterns introduced beyond those already established in 09-02 through 09-07

---
*Phase: 09-ui-vereinheitlichung*
*Completed: 2026-09-23*
