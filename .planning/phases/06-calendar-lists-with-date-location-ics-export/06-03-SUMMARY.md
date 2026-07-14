---
phase: 06-calendar-lists-with-date-location-ics-export
plan: 03
subsystem: coordinator-calendar-view
tags: [calendar, coordinator, tab-switcher, ics, week-month-nav]
dependency_graph:
  requires: [06-01, 06-02]
  provides: [coordinator-calendar-view]
  affects: [src/coordinator/lists_handler.php, src/templates/coordinator/lists.php]
tech_stack:
  added: []
  patterns: [tab-switcher, day-grouped-timeline, week-month-nav, PHP-DateTime, Bootstrap-nav-tabs]
key_files:
  created: []
  modified:
    - src/coordinator/lists_handler.php
    - src/templates/coordinator/lists.php
decisions:
  - "Calendar tab is the default view (view=calendar maps to week period view per D-01)"
  - "Coordinator sees all visibility states in calendar (public + protected + private per D-09)"
  - "Offset clamped to [-120, +120] to prevent absurd date ranges"
  - "ICS URL constructed from $_SERVER['HTTP_HOST'] and $_SESSION['team_id'] — no config dependency"
  - "List view card layout preserved exactly under Liste tab with no changes to existing behavior"
metrics:
  duration: 4m
  completed: "2026-07-14"
  tasks: 2
  files_created: 0
  files_modified: 2
---

# Phase 06 Plan 03: Coordinator Calendar View Summary

Calendar tab added to coordinator lists overview — week/month timeline with day-grouped entries, ICS subscription info box, navigation arrows, and undated section; existing card list view preserved under "Liste" tab.

## What Was Built

### Task 1 — src/coordinator/lists_handler.php (commit cb0a95d)

Extended the lists handler with calendar view logic:

- `location` column added to the `lists` SELECT; `NULL AS location` added to `files` SELECT for consistent array shape
- GET param `view` parsed with allowlist validation (`calendar|week|month|list`), defaults to `calendar`
- `showCalendar` flag derived: true when `view !== 'list'`
- `periodView` derived: `'month'` when `view === 'month'`, otherwise `'week'`
- `offset` parsed as integer, clamped to `[-120, +120]`
- When `$showCalendar`: requires `src/utils/calendar.php`, computes boundaries via `getWeekBoundaries` or `getMonthBoundaries`
- `$datedItems`: items with `date` within current period, sorted ascending by date
- `$undatedItems`: items with `date === null`, inheriting existing descending `created_at` order
- ICS URL built as `scheme://HTTP_HOST/ics/{team_id}.ics`
- `render_coach_page()` use() list expanded to include all 8 new calendar variables

### Task 2 — src/templates/coordinator/lists.php (commit ba8b4a5)

Complete rewrite adding calendar UI while preserving list view branch:

**Tab-Switcher (top of template):**
- Bootstrap `nav-tabs` with Kalender and Liste tabs
- Active state driven by `$view !== 'list'`

**Calendar branch (`$showCalendar === true`):**
- ICS info box (`alert alert-info`) with subscription URL in `<code class="user-select-all">`
- Add button row (Mitgliederliste + dropdown for Freie Liste and Datei)
- Week/Month toggle `btn-group` with active state on current `$periodView`
- Period navigation row: `◀ Vorherige Woche/Monat` | label | `Nächste Woche/Monat ▶`
- Day-grouped timeline: groups `$datedItems` by date, renders `h6` day header with German weekday name (e.g. "Montag, 14.07.2026"), then item cards with name link, optional `bi-geo-alt` location line, visibility badge
- Empty state message when no dated items in period
- "Ohne Datum" section for `$undatedItems` below the timeline

**List branch (`$showCalendar === false`):**
- Existing `$render_card` closure preserved exactly
- `$visible` / `$hidden` split unchanged
- Hidden-items collapse with `Versteckte Einträge (N)` toggle unchanged
- Location now shown on cards (date row and standalone location row added)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

| Item | Result |
|------|--------|
| src/coordinator/lists_handler.php exists | FOUND |
| src/templates/coordinator/lists.php exists | FOUND |
| 06-03-SUMMARY.md exists | FOUND |
| commit cb0a95d (lists_handler) | FOUND |
| commit ba8b4a5 (lists template) | FOUND |
