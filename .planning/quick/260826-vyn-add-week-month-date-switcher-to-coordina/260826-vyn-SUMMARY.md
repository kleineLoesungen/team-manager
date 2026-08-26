---
phase: quick-260826-vyn
plan: 01
subsystem: frontend/templates
tags: [ui, mobile, ios-design, calendar, segmented-control]
dependency_graph:
  requires: []
  provides: [.seg-ctrl CSS component, .period-nav CSS component]
  affects: [coordinator list overview, member list overview]
tech_stack:
  added: []
  patterns: [iOS segmented control, compact period navigation]
key_files:
  created: []
  modified:
    - src/templates/layout.php
    - src/templates/coordinator/lists.php
    - src/templates/member/lists.php
decisions:
  - ".seg-ctrl added to shared layout.php so both roles inherit the component"
  - "Period nav title attributes preserve accessibility text even though it is hidden from view"
metrics:
  duration: "~10 min"
  completed: "2026-08-26"
  tasks: 2
  files: 3
---

# Phase quick-260826-vyn Plan 01: Add iOS-Style Segmented Controls to List Overview Pages Summary

**One-liner:** Full-width iOS segmented controls (.seg-ctrl) and compact arrow period navigation (.period-nav) replace Bootstrap nav-tabs and btn-group on coordinator and member list overview pages.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Add .seg-ctrl and .period-nav CSS to layout.php | 3964271 | src/templates/layout.php |
| 2 | Replace nav-tabs, btn-group, and period nav in both templates | ee0e5cc | src/templates/coordinator/lists.php, src/templates/member/lists.php |

## What Was Built

- **`.seg-ctrl`** — iOS-style full-width segmented control using `--surface-2` background, `--surface` active pill with `box-shadow`, `--t3`/`--t1` text tokens. Works in both light and dark themes.
- **`.period-nav`** — Compact `‹ label ›` navigation row with 44px minimum touch targets on the arrow links, centered label using `--t1`.
- Both coordinator (`/coordinator/lists`) and member (`/member/lists`) list overview pages now use these controls instead of Bootstrap `nav-tabs` and `btn-group`.

## Deviations from Plan

### Minor: Verification grep too strict

The plan's verification script checked for absence of `Vorherige Woche`, `Nächste Woche`, `Vorheriger Monat`, `Nächster Monat` — but the plan's own replacement HTML includes these strings as `title` attributes on the arrow `<a>` links (for accessibility/tooltips). The strings are no longer visible button labels; they are tooltip text. The functional requirement (compact arrow-only nav) is correctly implemented.

## Checkpoints (Manual Verification Required)

The plan includes a `checkpoint:human-verify` task that cannot be automated. Perform these steps manually:

1. Open coordinator list overview (e.g. http://localhost/coordinator/lists)
2. Confirm Kalender/Liste switcher spans full width as a pill-shaped segmented control
3. Switch to calendar view — confirm Woche/Monat is also a full-width segmented control (not a small btn-group)
4. Confirm period nav shows only ‹ and › arrows flanking the centered label (no "Vorherige Woche" text)
5. Tap arrows to verify navigation still works
6. Open member list overview (e.g. http://localhost/member/lists) — confirm same controls appear
7. Toggle dark mode — confirm all controls still look correct

## Known Stubs

None.

## Self-Check: PASSED

- `src/templates/layout.php` — modified, contains `.seg-ctrl` and `.period-nav` CSS
- `src/templates/coordinator/lists.php` — modified, contains `seg-ctrl` class references, no `btn-group`/`nav-tabs`
- `src/templates/member/lists.php` — modified, contains `seg-ctrl` class references, no `btn-group`/`nav-tabs`
- Commits 3964271 and ee0e5cc exist in git log
