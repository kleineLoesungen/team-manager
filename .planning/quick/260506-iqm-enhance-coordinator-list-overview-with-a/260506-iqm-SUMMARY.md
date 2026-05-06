# Quick Task 260506-iqm — SUMMARY

**Task:** enhance coordinator list overview with all members default selected, sum up numbers and count booleans with percentage of possible total
**Date:** 2026-05-06
**Commit:** 8022bb7

## What was done

### `src/templates/coordinator/list_detail.php`

**Member list section:**
- Added collapsible member filter (`<details id="member-filter-details">`) above the table
  - All members checked by default
  - "Alle" / "Keine" quick-select buttons
  - Individual form-switch toggles per member with name label
  - Summary line shows "Alle N Mitglieder ausgewählt" or "X / N Mitglieder ausgewählt"
- Added `data-member-id` attribute to each tbody `<tr>`
- Added `data-col-id` and `data-col-type` attributes to each data `<td>` and tfoot `<td>`
- Boolean tfoot: changed from bare count to `count / total (X%)` format
- Added inline JS (IIFE):
  - `updateTable()`: shows/hides rows, updates filter label, recalculates tfoot
  - `setAllMembers(bool)`: toggles all filter checkboxes
  - Change listeners on filter checkboxes AND on cell inputs (numbers + booleans)

**Free list section:**
- Boolean tfoot: changed from bare count to `count / total_rows (X%)` format
