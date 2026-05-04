---
id: 260504-sn6
title: "Fix free list nested form bug and replace checkboxes with toggles"
type: quick
date: 2026-05-04
commit: 2090acf
files_modified:
  - src/templates/coach/list_detail.php
  - src/templates/coach/list_form.php
tags: [bug-fix, html, forms, ui-consistency]
---

# Quick Task 260504-sn6 Summary

**One-liner:** Extracted nested delete-row forms outside the save-cells form using HTML5 `form=` attribute, and converted three plain checkboxes to Bootstrap form-switch toggles with disambiguated IDs.

## Tasks Completed

| Task | Description | Status |
|------|-------------|--------|
| 1 | Fix nested form bug in free list table (list_detail.php) | Done |
| 2 | Replace checkboxes with form-switch toggles in both templates | Done |

## Changes Made

### Task 1 — Nested form fix (list_detail.php)

The save-cells `<form>` in the free list `else` branch (with columns and rows) previously contained a per-row `<form action=delete_row>` inside each Aktion `<td>`. Browsers fold all inputs from the last-parsed form (the nested delete form) into the outer form, causing the save action to carry `action=delete_row` with no `confirm`, triggering the deletion confirmation screen instead of saving.

Fix: emitted all delete-row forms as standalone `<form id="delete-row-{id}">` elements immediately before the save-cells form opens (using a PHP foreach loop). The Aktion column button now uses the HTML5 `form="delete-row-{id}"` attribute to reference its external form. The `elseif (empty($columns))` branch was left unchanged (its delete forms are already standalone).

### Task 2 — Toggle switches (list_detail.php + list_form.php)

Three plain `<div class="form-check">` checkboxes were converted to Bootstrap `form-switch` toggles matching the existing cell-level boolean toggle style (3em × 1.75em, `role="switch"`, `cursor:pointer`):

- **Change A** (`list_form.php` line 70): `show_all_rows`
- **Change B** (`list_detail.php` ~line 94): free list `coach_only` — id changed from `coach_only_chk` to `coach_only_free_chk`
- **Change C** (`list_detail.php` ~line 306): member list `coach_only` — id changed from `coach_only_chk` to `coach_only_member_chk`

The id disambiguation resolves an invalid-HTML duplicate-id issue (both sections rendered on the same page when viewing a member list).

## Verification

```
grep -c "form-switch" src/templates/coach/list_detail.php  → 4
grep -c "form-switch" src/templates/coach/list_form.php    → 1
grep -c 'id="coach_only_chk"' src/templates/coach/list_detail.php → 0
```

No `<form` tag appears inside the save-cells form block.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- src/templates/coach/list_detail.php: modified and committed
- src/templates/coach/list_form.php: modified and committed
- Commit 2090acf: verified present
