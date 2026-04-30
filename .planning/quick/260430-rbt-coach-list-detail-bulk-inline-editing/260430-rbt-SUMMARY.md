---
phase: quick
plan: 260430-rbt
subsystem: coach/lists
tags: [inline-edit, bulk-save, form, cells, ux]
dependency_graph:
  requires: []
  provides: [bulk-cell-post, inline-edit-form]
  affects: [src/coach/list_detail_handler.php, src/templates/coach/list_detail.php]
tech_stack:
  added: []
  patterns: [POST-redirect-GET, UPSERT ON CONFLICT, CSRF validation, inline form]
key_files:
  created: []
  modified:
    - src/coach/list_detail_handler.php
    - src/templates/coach/list_detail.php
decisions:
  - "Boolean columns use checkbox absence = '0' (unchecked checkboxes are not submitted by browsers)"
  - "Invalid number values silently become null rather than rejecting the whole form"
  - "'Zurück zu Listen' link placed outside the form so it is always accessible without submitting"
metrics:
  duration: ~10min
  completed: "2026-04-30"
  tasks_completed: 2
  files_modified: 2
---

# Quick Task 260430-rbt: Coach List Detail — Bulk Inline Editing

**One-liner:** Replace per-row Bearbeiten navigate-away with a single inline-edit POST form; all player cells editable in place with one Speichern button.

## What Was Built

### Task 1 — Bulk POST handler (list_detail_handler.php)

Added a POST branch before the GET render path. The handler:

- Calls `require_csrf()` as the first action on POST
- Iterates every player in the team (from the existing `$players` array)
- For each column, validates the submitted value by `data_type`:
  - **boolean**: checkbox absence = `'0'`, presence = `'1'`
  - **number**: `filter_var` with `FILTER_VALIDATE_INT` or `FILTER_VALIDATE_FLOAT`; invalid = `null`
  - **text**: `mb_substr` to 255 chars
- Executes an `INSERT INTO cells ... ON CONFLICT (list_id, column_id, player_id) DO UPDATE SET value` UPSERT per cell
- Redirects to `/coach/lists/{id}?success=1` on success
- Catches `PDOException`, logs it, and sets `$post_error` for display
- `$post_error` is initialized before the POST block so GET path scope is clean; `$error` prefers `$post_error` over `$_GET['error']`

### Task 2 — Inline-edit table form (list_detail.php)

Replaced the static value display + per-row Bearbeiten link with:

- `<form method="POST" action="/coach/lists/{id}">` wrapping the entire table, with `csrf_field()`
- Each cell `<td>` renders the correct input by `data_type`:
  - **boolean**: `<input type="checkbox">` with `checked` attribute when value is `'1'`
  - **number**: `<input type="number">` with `min-width:70px; max-width:100px` for mobile scroll
  - **text**: `<input type="text">` with `min-width:100px` and `maxlength="255"`
- All inputs use `name="cells[{player_id}][{column_id}]"` so POST body maps directly to the handler's `$_POST['cells']` structure
- Edit-button `<th>` and `<td>` columns removed entirely
- `<button type="submit" class="btn btn-primary min-touch">Speichern</button>` after `</table>` inside form
- "Zurück zu Listen" link remains outside the form (after `<?php endif; ?>`)

## Deviations from Plan

None — plan executed exactly as written.

## Success Criteria Verification

| Criterion | Status |
|-----------|--------|
| Coach edits any cell directly in table (no navigate-away) | Met — form wraps full table |
| All rows save in single POST submission | Met — single Speichern button |
| Inputs pre-populated from stored values on GET | Met — `$cells` map feeds `value=` attribute |
| Boolean = checkbox, number = number input, text = text input | Met — switch/if on `data_type` |
| Invalid number values silently null; text truncated at 255 | Met — filter_var + mb_substr |
| CSRF enforced on POST | Met — `require_csrf()` first line of POST branch |
| Zurück zu Listen accessible outside form | Met — link after `<?php endif; ?>` |

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | 6f50bdc | feat(quick-260430-rbt): add bulk POST handler with CSRF, type validation, UPSERT |
| 2 | 6e984a1 | feat(quick-260430-rbt): replace per-row Bearbeiten link with inline-edit form |

## Known Stubs

None.

## Self-Check: PASSED
