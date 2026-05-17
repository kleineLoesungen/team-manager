---
phase: quick-260517-fq2
plan: 01
subsystem: ui
tags: [logo, navbar, coordinator, admin, bootstrap]

requires: []
provides:
  - Coordinator logo page shows admin default logo as read-only preview when team has no own logo
  - Navbar logo img inline before app title text across all roles
affects: [coordinator, layout, admin-logo]

tech-stack:
  added: []
  patterns:
    - "Read-only admin default preview: fetch from settings, pass via use() closure, render conditionally in template"
    - "Navbar logo: decorative img with onerror hide fallback, height:1lh CSS unit for font-size match"

key-files:
  created: []
  modified:
    - src/coordinator/logo_handler.php
    - src/templates/coordinator/logo.php
    - src/templates/layout.php

key-decisions:
  - "Show admin default logo at /logo endpoint (existing fallback) — no separate URL needed for read-only preview"
  - "Use onerror attribute to hide broken img in navbar — no JS dependency, one-liner HTML attribute"
  - "height:1lh CSS unit for navbar logo height — matches font line-height natively in modern browsers"
  - "Default logo preview appears ONLY when $current_logo is falsy AND $default_logo is truthy — no UI when neither is set"

patterns-established:
  - "Admin default logo read-only display: pass $default_logo via use() in render_coach_page closure"

requirements-completed: [FQ2-01, FQ2-02]

duration: 8min
completed: 2026-05-17
---

# Quick Task 260517-fq2: Show Admin Default Logo Read-Only on Coordinator Page + Navbar Logo Summary

**Admin default logo shown as read-only preview on coordinator logo page; navbar logo img added inline before app title with onerror fallback across all roles**

## Performance

- **Duration:** ~8 min
- **Started:** 2026-05-17T00:00:00Z
- **Completed:** 2026-05-17
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- Coordinator logo page now shows the admin-set default logo as a read-only preview (labelled "Standard-Logo (vom Admin)") when the team has no own logo, so coordinators know what members see
- Navbar renders a logo img (src="/logo") inline before the app title, matching font height via `height:1lh`, hidden silently when no logo is set
- No new JS dependencies — onerror is a single HTML attribute

## Task Commits

1. **Task 1: Pass admin default logo to coordinator logo template** - `792d9dd` (feat)
2. **Task 2: Add logo img to navbar before app title** - `e4cefab` (feat)

## Files Created/Modified
- `src/coordinator/logo_handler.php` - Fetches `default_team_logo` from settings, passes `$default_logo` to template closure
- `src/templates/coordinator/logo.php` - Renders read-only admin default logo preview block when `!$current_logo && $default_logo`
- `src/templates/layout.php` - `render_navbar()` now wraps brand text in `d-flex` span with decorative logo img

## Decisions Made
- Used the existing `/logo` endpoint for both the navbar img and the read-only preview — no new URLs needed; the endpoint already falls back to admin default
- `onerror="this.style.display='none'"` chosen over PHP-side logic so no extra DB query is needed in `render_navbar()` for the logo existence check
- `height:1lh` CSS unit naturally matches line-height of surrounding text without magic pixel values

## Deviations from Plan
None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Both visual features are live: coordinator read-only preview and navbar branding logo
- No blockers

---
*Phase: quick-260517-fq2*
*Completed: 2026-05-17*
