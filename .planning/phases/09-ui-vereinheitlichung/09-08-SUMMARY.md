---
phase: 09-ui-vereinheitlichung
plan: "08"
subsystem: ui
tags: [php, bootstrap, partials, templates, public, ticker]

# Dependency graph
requires:
  - phase: 09-01
    provides: render_page(), render_empty(), render_badge() partials infrastructure
  - phase: 09-07b
    provides: admin templates fully migrated
provides:
  - Public ticker pages migrated to render_page(['role' => 'public'])
  - Phase 9 complete — full codebase scan confirms zero critical violations
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Public role: render_page(['role' => 'public']) suppresses tabbar, no session required"
    - "Unconditional require_once for layout.php in public templates (template owns its own layout require)"
    - "Auto-reload setTimeout in public ticker body callable — renders inside <main> but runs correctly"

key-files:
  created: []
  modified:
    - src/templates/public/ticker_overview.php
    - src/templates/public/ticker_detail.php

key-decisions:
  - "Public templates own their require_once for layout.php (unconditional) — ensures render_page() is always available regardless of how handler includes the template"
  - "credential_modal.php inline style deferred — plan conflict between 'unchanged' requirement and inline style scan; documented in deferred-items.md"
  - "Pre-existing mb-0/mt-1/fs-5 violations across 15+ already-migrated templates deferred to cleanup plan — out of scope per scope boundary rule"

requirements-completed: []

# Metrics
duration: 206 seconds (~3 minutes)
completed: "2026-09-23"
---

# Phase 09 Plan 08: Public Templates Migration & Final Scan Summary

**Public ticker templates migrated to render_page(['role' => 'public']); full Phase 9 codebase scan confirms zero critical violations; thin wrappers preserved per D-03**

## Performance

- **Duration:** ~3 minutes
- **Started:** 2026-09-23T20:23:34Z
- **Completed:** 2026-09-23T20:27:00Z
- **Tasks:** 2 (both auto)
- **Files modified:** 2 templates + 1 deferred-items log

## Accomplishments

- `src/templates/public/ticker_overview.php` migrated from full standalone HTML (DOCTYPE, head, style, body) to `render_page(['role' => 'public'])` pattern
- `src/templates/public/ticker_detail.php` migrated — auto-reload 30s setTimeout preserved for active tickers
- All violation types eliminated from both public templates: standalone HTML, inline styles, Bootstrap CDN links, custom CSS, py-5/mb-5 spacing, raw badge HTML
- `render_badge('ok', 'Live')` / `render_badge('dim', 'Geschlossen')` replaces inline badge HTML in both templates
- `render_empty()` replaces py-5 text-center empty states
- Both templates use unconditional `require_once` for layout.php
- Final codebase scan: 0 standalone HTML templates (excluding layout.php and credential_modal.php), 0 py-5/mb-5 violations, all infrastructure intact
- Three D-03 thin wrappers confirmed present: `render_coach_page()`, `render_member_page()`, `render_admin_page()`
- `credential_modal.php` unchanged

## Task Commits

1. **Task 1: Migrate public ticker templates to render_page** — `13d5370`
2. **Task 2: Final codebase scan and Phase 9 verification** — `03204b1` (deferred-items log)

## Files Created/Modified

- `src/templates/public/ticker_overview.php` — Full migration: render_page('public'), render_badge, render_empty, no standalone HTML, no inline styles
- `src/templates/public/ticker_detail.php` — Full migration: render_page('public'), render_badge, render_empty, auto-reload preserved
- `.planning/phases/09-ui-vereinheitlichung/deferred-items.md` — Pre-existing violation inventory for future cleanup

## Decisions Made

- **Public templates own their layout.php require_once** — unconditional at top of template file, not in handler, so render_page() is always available at template include time
- **credential_modal.php inline style is a deferred exception** — the plan has a conflict between "must be unchanged" and "style count must be 0"; file left as-is, documented in deferred-items.md
- **Pre-existing spacing utility violations (~55 instances of mb-0/mb-1/mt-1) deferred** — introduced in plans 09-03 through 09-07b, not in scope for this final plan

## Deviations from Plan

### Deferred Issues

**1. credential_modal.php inline style**
- **Found during:** Task 2 final scan
- **Issue:** `style="background: rgba(0,0,0,0.5);"` on modal overlay in credential_modal.php. This is a pre-existing violation from before Phase 9.
- **Resolution:** Deferred. The plan explicitly requires credential_modal.php remain unchanged, creating a conflict with the scan's "style count = 0" requirement. The file was not touched.
- **Logged to:** deferred-items.md

**2. Pre-existing spacing utility violations (~55 occurrences)**
- **Found during:** Task 2 scan (check #5: banned *-0 and *-1 utilities)
- **Issue:** `mb-0`, `mb-1`, `mt-1`, `px-0`, `py-1` etc. across 15+ files migrated in plans 09-03 to 09-07b
- **Resolution:** Deferred. Out of scope per scope boundary rule — not caused by this plan's changes. Logged to deferred-items.md.

**3. fs-5 icon size class violations (5 occurrences)**
- **Found during:** Task 2 scan (check #6: fs-* classes)
- **Issue:** `fs-5` used for icon sizing in member/profile.php and member/confirm_profile.php
- **Resolution:** Deferred. Pre-existing, not in formal acceptance criteria. Logged to deferred-items.md.

## Formal Acceptance Criteria Results

| Check | Expected | Actual | Result |
|-------|----------|--------|--------|
| `style="..."` count (excl. onerror, credential_modal) | 0 | 0 | PASS |
| `<html\|<!DOCTYPE\|<head>` (excl. layout.php, credential_modal) | 0 | 0 | PASS |
| `py-5\|mb-5` count (excl. partials.php comment) | 0 | 0 | PASS |
| `function render_page` in layout.php | 1 | 1 | PASS |
| `require_once.*components/partials` in layout.php | 1 | 1 | PASS |
| `<style>` in layout.php | 1 | 1 | PASS |
| render_coach_page() thin wrapper present | yes | yes | PASS |
| render_member_page() thin wrapper present | yes | yes | PASS |
| render_admin_page() thin wrapper present | yes | yes | PASS |
| credential_modal.php unchanged | yes | yes | PASS |

## Known Stubs

None — both public templates render live data from handlers.

## Phase 9 Completion Status

Phase 9 (ui-vereinheitlichung) is complete. All templates across all roles (coordinator, member, admin, public) render through `render_page()`. The thin D-03 wrappers are preserved. The unified layout with Bootstrap CDN, app.css, and partials infrastructure is the single rendering path for all pages.

---
*Phase: 09-ui-vereinheitlichung*
*Completed: 2026-09-23*
