---
phase: quick
plan: 260502-wfw
subsystem: coach-templates, player-templates, stats
tags: [ui, totals, list-view, statistics, ranking, filter]
dependency_graph:
  requires: []
  provides: [tfoot-totals-row, stats-col-filter-dropdown]
  affects: [list_detail-coach, list_detail-player, stats-coach]
tech_stack:
  added: []
  patterns: [tfoot-aggregation-in-template, GET-param-filter-with-hidden-inputs]
key_files:
  created: []
  modified:
    - src/templates/coach/list_detail.php
    - src/templates/player/list_detail.php
    - src/coach/stats_handler.php
    - src/templates/coach/stats.php
decisions:
  - Totals computed in PHP template over $cells/$players map — no new SQL query needed
  - col_filter validated against $valid_col_ids in handler, default 0 = alle Spalten
  - ranking_sort_url() extended with col_filter to preserve filter state in sort links
  - Spielerstatistiken table filtered by col_filter for consistent view with Rangliste
metrics:
  duration: 15m
  completed: "2026-05-02T21:25:00Z"
  tasks_completed: 2
  files_modified: 4
---

# Quick Task 260502-wfw Summary

**One-liner:** tfoot Gesamt-Zeile (Summe/Anzahl/leer) in Listenansichten + Spalten-Dropdown auf Statistik-Rangliste filtert auf einzelne globale Spalte mit URL-State-Erhalt.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Gesamt-Zeile in Coach- und Spieler-Listenansicht | fb64634 | src/templates/coach/list_detail.php, src/templates/player/list_detail.php |
| 2 | Spalten-Dropdown auf Coach-Statistik-Rangliste | 3448187 | src/coach/stats_handler.php, src/templates/coach/stats.php |

## What Was Built

### Task 1: Gesamt-Zeile

Both list_detail templates now have a `<tfoot class="table-light">` row after `</tbody>`:

- **number** columns: sum of all non-null/non-empty numeric values via `is_numeric()`. Displayed as integer if `floor == n`, else 2 decimals with `,`/`.`.
- **boolean** columns: count of entries where value `=== '1'`.
- **text** columns: empty string.

Coach template: computed over all `$players` from `$cells` map. No input fields in tfoot.

Player template: computed over visible `$players` (respects `show_all_rows`). First cell (`Gesamt` label) only rendered when `show_all_rows`. `$can_edit` column gets empty `<td></td>`.

### Task 2: Spalten-Dropdown

**Handler** (`src/coach/stats_handler.php`):
- `$col_filter` validated against `$valid_col_ids`; default `0` = alle Spalten.
- Added to `render_coach_page()` closure `use` list.

**Template** (`src/templates/coach/stats.php`):
- `ranking_sort_url()` now includes `col_filter` in URL params.
- Spalten-Dropdown form inserted before Rangliste heading; preserves all existing filter params (list_id, date_from, date_to, include_undated, sort_col, sort_win) as hidden inputs; auto-submits on change.
- Spielerstatistiken thead + tbody: `if ($col_filter !== 0 && (int)$col['id'] !== $col_filter) continue;` in each `$global_columns` foreach.
- Rangliste thead (colspan row + window subrow) + tbody: same `continue` guard.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- src/templates/coach/list_detail.php — modified, syntax OK
- src/templates/player/list_detail.php — modified, syntax OK
- src/coach/stats_handler.php — modified, syntax OK
- src/templates/coach/stats.php — modified, syntax OK
- Commits fb64634 and 3448187 exist in git log
