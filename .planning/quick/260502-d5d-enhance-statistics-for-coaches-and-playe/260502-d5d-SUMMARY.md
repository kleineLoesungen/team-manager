---
phase: quick
plan: 260502-d5d
subsystem: statistics
tags: [stats, ranking, time-window, coach, player]
completed: "2026-05-02T07:32:35Z"

key-files:
  modified:
    - src/coach/stats_handler.php
    - src/player/stats_handler.php
    - src/templates/coach/stats.php
    - src/templates/player/stats.php

decisions:
  - "time-window intervals: 28/56/84 days (not calendar weeks) — consistent with INTERVAL arithmetic in PostgreSQL"
  - "sum_all date filter expressed as inline CASE WHEN condition inside SUM (not WHERE) to preserve CROSS JOIN rows for players with zero cells"
  - "sorting done in PHP usort after fetch rather than dynamic ORDER BY in SQL — simpler and safer with parameterized queries"
  - "player stats transposed layout (columns as rows) — more readable on mobile than wide multi-column row"

metrics:
  duration_minutes: ~15
  tasks_completed: 3
  tasks_total: 3
  files_modified: 4
---

# Quick Task 260502-d5d: Enhance Statistics for Coaches and Players — Summary

**One-liner:** Time-window ranking for coaches (Gesamt / Letzte 4 Wo. / 4–8 Wo. / 8–12 Wo. with sortable headers) and transposed 4-window table for players using conditional SQL SUMs with CURRENT_DATE intervals.

---

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Extend coach stats handler with time-window aggregation and sort params | 58dc0da | src/coach/stats_handler.php |
| 2 | Extend player stats handler with time-window columns | 7e421f4 | src/player/stats_handler.php |
| 3 | Update coach and player stats templates for time-window columns and sortable headers | b57f8ba | src/templates/coach/stats.php, src/templates/player/stats.php |

---

## What Was Built

**Coach stats handler (Task 1):**
- Removed old `$leaderboard` / `$leaderboard_column` variables and the separate leaderboard SQL query
- Added a unified ranking query using conditional `CASE WHEN ... THEN ... END` inside `SUM` to compute 4 values per player per global column in a single pass: `sum_all`, `sum_4w`, `sum_4_8w`, `sum_8_12w`
- `sum_all` (Gesamt) continues to respect the existing `date_from` / `date_to` / `include_undated` filters, expressed as an inline SQL condition fragment
- `sum_4w`, `sum_4_8w`, `sum_8_12w` use fixed `CURRENT_DATE - INTERVAL '...'` windows and ignore date filters; `list_id` filter still applies to all windows
- New GET params: `sort_col` (column ID) and `sort_win` (`all`|`4w`|`4_8w`|`8_12w`); both validated against known values; defaults to first column / `all`
- PHP `usort` sorts `$ranking_order` descending by the chosen col+window; ties broken by last_name, first_name
- Handler passes `$ranking`, `$ranking_order`, `$sort_col_id`, `$sort_win` to template

**Player stats handler (Task 2):**
- Replaced flat `[column_id => aggregated_value]` with `[column_id => ['all'=>float, '4w'=>float, '4_8w'=>float, '8_12w'=>float]]`
- Same 4-window conditional SUM pattern, scoped to `cells.player_id = ?` and `lists.visibility IN ('public','protected')`
- `sum_all` includes dated and undated public/protected cells; time-window cols require `lists.date IS NOT NULL`

**Coach stats template (Task 3):**
- Removed leaderboard column-selector `<form>` entirely
- Added new ranking table with two-row header: top row has column names (`colspan="4"`), bottom row has the 4 window labels as `<a>` links
- `ranking_sort_url()` helper builds URL preserving all current filter params plus `sort_col` + `sort_win`
- Active sort header gets `text-primary fw-bold`; active sort cell gets `table-active fw-semibold`
- Existing "Spielerstatistiken" detail table section (using `$player_stats` / `$player_order`) is unchanged

**Player stats template (Task 3):**
- Replaced single-row wide table with transposed layout: one row per global column, 4 value columns (Gesamt / Letzte 4 Wo. / 4–8 Wo. / 8–12 Wo.)
- Transposed layout reads better on mobile (fewer horizontal columns)

---

## Verification

All 4 files pass `php -l` with no syntax errors:
- `php -l src/coach/stats_handler.php` — OK
- `php -l src/player/stats_handler.php` — OK
- `php -l src/templates/coach/stats.php` — OK
- `php -l src/templates/player/stats.php` — OK

Manual verification required:
- Load `/coach/stats` — ranking table shows 4 time-window columns per global column
- Click a column header — page reloads, that column is highlighted, players sorted by it
- Load `/player/stats` — table shows one row per column with 4 value windows

---

## Deviations from Plan

None — plan executed exactly as written.

---

## Known Stubs

None.

---

## Self-Check

Files exist:
- src/coach/stats_handler.php — FOUND
- src/player/stats_handler.php — FOUND
- src/templates/coach/stats.php — FOUND
- src/templates/player/stats.php — FOUND

Commits exist:
- 58dc0da — FOUND
- 7e421f4 — FOUND
- b57f8ba — FOUND

## Self-Check: PASSED
