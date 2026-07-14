# Quick Task 260714-wkp: Move ICS hint to bottom + time range in calendar cards

**Date:** 2026-07-14
**Commit:** 542e156

## What Was Done

**`src/templates/coordinator/lists.php`** and **`src/templates/member/lists.php`** — same 3 changes each:

1. Removed ICS info box from the top of the calendar tab (was between the tab-switcher and the navigation controls — visually intrusive before any content)

2. Added time range display in dated-items timeline cards, below the location line:
   - Shows `HH:MM` when only `time_start` is set
   - Shows `HH:MM – HH:MM` when both `time_start` and `time_end` are set
   - Guarded by `!empty($item['time_start'])` — no output for all-day events

3. Added ICS info box at the bottom of the calendar tab, after the undated section — contextually appropriate since it's a secondary action, not primary content
