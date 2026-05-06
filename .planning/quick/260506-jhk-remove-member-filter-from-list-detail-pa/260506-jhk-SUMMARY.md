# Quick Task 260506-jhk — SUMMARY

**Task:** remove member filter from list detail page and rename back-link to Zurück zur Übersicht
**Date:** 2026-05-06
**Commit:** fe83dfa

## What was done

### `src/templates/coordinator/list_detail.php`

- Removed `<details id="member-filter-details" open>` member filter panel (28 lines)
- Removed JS IIFE (80 lines) — `updateTable()`, `setAllMembers()`, change listeners
- Removed `id="member-list-table"` from the `<table>` element
- Removed `data-member-id` attribute from all tbody `<tr>` tags
- Removed `data-col-id` / `data-col-type` attributes from tbody `<td>` and tfoot `<td>` tags
- Renamed back-link label "Zurück zu Listen" → "Zurück zur Übersicht"

Net: −115 lines. PHP server-side totals row (numbers sum, booleans count/pct) unchanged.
