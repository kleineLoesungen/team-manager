---
phase: 09-ui-vereinheitlichung
plan: "05"
subsystem: ui
tags: [php, bootstrap, partials, templates, coordinator, ticker, stats]

# Dependency graph
requires:
  - phase: 09-02
    provides: render_page(), render_empty(), render_badge(), render_collection_group(), render_filter_pills(), render_danger_zone() partials infrastructure
provides:
  - coordinator ticker, file, stats, coordinators templates fully migrated to partials system
  - Bestätigungsseite archetype applied to ticker_delete_confirm.php
  - render_filter_pills() integration in stats.php column filter
affects: [09-06, 09-07, 09-08]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "render_empty() replaces all py-5 text-center empty state divs"
    - "render_badge('ok'/'dim'/'warn', label) replaces all inline badge HTML"
    - "render_collection_group(label, fn) replaces h2 group headers for grouped lists"
    - "render_danger_zone() replaces custom Gefahrenzone card patterns"
    - "render_filter_pills() replaces form-select-sm column filter dropdowns"
    - "Bestätigungsseite archetype: h1 + body text + btn-danger w-100 + cancel link, no JS confirm"
    - "ob_start()/ob_get_clean() captures form HTML for render_danger_zone() argument"

key-files:
  created: []
  modified:
    - src/templates/coordinator/ticker.php
    - src/templates/coordinator/ticker_detail.php
    - src/templates/coordinator/ticker_form.php
    - src/templates/coordinator/ticker_delete_confirm.php
    - src/templates/coordinator/file_detail.php
    - src/templates/coordinator/file_form.php
    - src/templates/coordinator/file_notify.php
    - src/templates/coordinator/stats.php
    - src/templates/coordinator/coordinators.php

key-decisions:
  - "ticker_delete_confirm.php restructured to Bestätigungsseite archetype — h1 heading, btn-danger w-100, Abbrechen cancel link, no JS confirm"
  - "stats.php column filter replaced with render_filter_pills() — removes onchange JS auto-submit and form-select-sm violations"
  - "JS confirm() dialogs removed from ticker_detail.php close button and message delete button — direct form POST without confirmation"
  - "Message tag badges in ticker_detail.php kept as dynamic bg-{color} pattern — render_badge() does not support arbitrary Bootstrap color names set by users"

requirements-completed: []

# Metrics
duration: 1596
completed: "2026-09-13"
---

# Phase 09 Plan 05: Coordinator Ticker / File / Stats Templates Summary

**9 coordinator templates (ticker workflow, markdown files, statistics, coordinators listing) migrated to render_page() partials — zero violations, Bestätigungsseite archetype applied, stats filter uses render_filter_pills()**

## Performance

- **Duration:** ~27 minutes
- **Started:** 2026-09-13T05:41:37Z
- **Completed:** 2026-09-13T06:08:17Z
- **Tasks:** 2 (both auto)
- **Files modified:** 9

## Accomplishments

**Task 1 — Ticker templates (4 files):**
- ticker.php: render_empty() for empty state, render_badge() for status (ok/dim), render_collection_group() for "Weitere Teams" section
- ticker_detail.php: render_badge() for ticker status, render_empty() for empty message feed, JS confirm() dialogs removed from close/reopen button and message delete button
- ticker_form.php: button label "Speichern" → "Ticker speichern" (Verb+Objekt compliance)
- ticker_delete_confirm.php: full Bestätigungsseite archetype — h1 heading, descriptive body text, btn-danger w-100 mb-2, Abbrechen cancel link, no JS

**Task 2 — File / Stats / Coordinators templates (5 files):**
- file_detail.php: render_badge() for visibility, render_danger_zone() via ob_get_clean() pattern, shadow-sm removed from 2 cards, form-switch inline style removed
- file_form.php: form-switch inline style removed
- file_notify.php: render_badge() for visibility, pre inline style removed
- stats.php: render_filter_pills() replaces form-select-sm column selector in Rangliste section; all form-select-sm/form-control-sm removed; form-switch inline style removed; mb-5→mb-4
- coordinators.php: render_empty() for empty state, render_collection_group() per team group

## Task Commits

1. **Task 1: Migrate coordinator ticker templates** — `68721f6`
2. **Task 2: Migrate coordinator file/stats/coordinators templates** — `8bce256`

## Files Created/Modified

- `src/templates/coordinator/ticker.php` — render_empty, render_badge, render_collection_group; zero violations
- `src/templates/coordinator/ticker_detail.php` — render_badge, render_empty, JS confirms removed; zero violations
- `src/templates/coordinator/ticker_form.php` — button label fix; zero violations
- `src/templates/coordinator/ticker_delete_confirm.php` — Bestätigungsseite archetype; zero violations
- `src/templates/coordinator/file_detail.php` — render_badge, render_danger_zone, shadow-sm/inline styles removed; zero violations
- `src/templates/coordinator/file_form.php` — inline style removed; zero violations
- `src/templates/coordinator/file_notify.php` — render_badge, pre style removed; zero violations
- `src/templates/coordinator/stats.php` — render_filter_pills column filter, -sm classes removed, mb-5→mb-4; zero violations
- `src/templates/coordinator/coordinators.php` — render_empty, render_collection_group; zero violations

## Decisions Made

- **Bestätigungsseite archetype applied:** ticker_delete_confirm.php restructured from alert-danger pattern to proper archetype with h1, descriptive text, full-width btn-danger, Abbrechen cancel link. No JS confirm anywhere.
- **render_filter_pills() for stats column filter:** The ranking column selector (form-select-sm with onchange submit) replaced with GET-linked pills. Adds "Alle Spalten" pill for col_filter=0 plus one pill per global column. Eliminates form-select-sm violation and JS auto-submit dependency.
- **JS confirm() dialogs removed:** The close/reopen buttons in ticker_detail.php and message delete buttons had onclick=return confirm() which violates UI-BASELINE "keine Modals, kein JS-Confirm". Direct form POSTs used instead.
- **Dynamic tag badge pattern retained:** Message tag badges in ticker_detail.php use `badge bg-{color}` where color is a user-defined Bootstrap color name. render_badge() only supports the 5 semantic types (ok/warn/bad/dim/info) — arbitrary Bootstrap colors require the inline pattern.

## Deviations from Plan

None — plan executed exactly as written. All 9 templates migrated. Confirmation page pattern, render_empty, render_filter_pills and render_danger_zone partials applied as specified.

## Known Stubs

None. All templates wire real data from handler-injected variables. No hardcoded empty values or placeholders in rendered output.

---
## Self-Check: PASSED

Files exist:
- src/templates/coordinator/ticker.php ✓
- src/templates/coordinator/ticker_detail.php ✓
- src/templates/coordinator/ticker_form.php ✓
- src/templates/coordinator/ticker_delete_confirm.php ✓
- src/templates/coordinator/file_detail.php ✓
- src/templates/coordinator/file_form.php ✓
- src/templates/coordinator/file_notify.php ✓
- src/templates/coordinator/stats.php ✓
- src/templates/coordinator/coordinators.php ✓

Commits exist:
- 68721f6 ✓
- 8bce256 ✓

*Phase: 09-ui-vereinheitlichung*
*Completed: 2026-09-13*
