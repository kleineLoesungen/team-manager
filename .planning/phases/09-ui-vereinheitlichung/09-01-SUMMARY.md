---
phase: 09-ui-vereinheitlichung
plan: "01"
subsystem: frontend/layout
tags: [css, layout, partials, refactor]
dependency_graph:
  requires: []
  provides:
    - render_page() unified layout for all roles
    - 11 partial functions in partials.php
    - app.css as sole CSS source (inline block removed)
  affects:
    - All 61 templates that use role layout functions (will migrate in subsequent plans)
    - Login page (inline styles cleaned)
tech_stack:
  added: []
  patterns:
    - D-03 thin wrapper pattern for role layouts
    - D-04 minimal inline style (only --brand token injected per-request)
key_files:
  created:
    - src/templates/components/partials.php
  modified:
    - public/css/app.css
    - src/templates/layout.php
    - src/templates/coordinator/layout.php
    - src/templates/member/layout.php
    - src/templates/admin/layout.php
    - src/templates/login.php
decisions:
  - "D-03: Role layout files become thin wrappers that delegate to render_page() — call sites unchanged"
  - "D-04: Only --brand CSS token injected per-request as inline style; all other CSS is static in app.css"
  - "render_navbar() removed — was unused and contained inline style= violation"
metrics:
  duration: "~7 minutes"
  completed_date: "2026-08-30"
  tasks_completed: 2
  files_modified: 6
---

# Phase 09 Plan 01: Foundation (CSS + Layout + Partials) Summary

**One-liner:** Moved the 358-line per-request inline CSS block to app.css, added render_page() unified layout, created 11 partial functions in partials.php, and replaced three role layout files with thin wrappers — all existing call sites unchanged.

---

## Tasks Completed

| Task | Name | Commit | Key Files |
|------|------|--------|-----------|
| 1 | CSS migration — inline block to app.css, SRI hash, clean login.php | 4d2df2c | public/css/app.css, src/templates/layout.php, src/templates/login.php |
| 2 | render_page() + partials.php + thin wrappers | cbe3e77 | src/templates/components/partials.php, src/templates/layout.php, 3 role layouts |

---

## What Was Built

### Task 1: CSS Migration

- **public/css/app.css:** Appended the full CSS block previously rendered inline per-request (~358 lines). Two targeted changes: `.btn-primary` background changed from hardcoded `#4B5563` to `var(--brand)` (enables admin-configured brand color to apply to primary buttons). The `--brand` token itself was NOT moved to app.css — it is injected per-request via a minimal D-04 inline style.
- **Additional utility classes added** to app.css: `.cell-number`, `.cell-text` (matrix table cell sizing), `.login-wrapper`, `.login-card-wrap`, `.text-xs` (login page layout).
- **layout.php render_layout_head():** Replaced 358-line `<style>` block with `<style>:root{--brand:<?= $safe_color ?>;}</style>`. Added Bootstrap Icons SRI hash (`sha384-EVSTQN3...`). Added `<link rel="stylesheet" href="/css/app.css">`.
- **render_navbar() deleted** from layout.php — was unused (zero call sites outside layout.php) and contained inline `style=` violation.
- **login.php:** 5 inline `style=` violations removed — replaced with new CSS classes (`login-wrapper`, `login-card-wrap`, `text-xs`) or removed where Bootstrap defaults suffice.

### Task 2: render_page() + Partials + Thin Wrappers

- **src/templates/components/partials.php (new):** 11 partial functions matching the exact HTML output from 09-UI-SPEC.md Component Partial Contracts: `render_flash`, `render_page_header`, `render_empty`, `render_badge`, `render_collection_group`, `render_form_section`, `render_form_field`, `render_matrix_table`, `render_filter_pills`, `render_danger_zone`, `render_action_bar`.
- **layout.php:** Added `require_once __DIR__ . '/components/partials.php'` at top (before any function definition). Added `render_page(array $opts, callable $body)` — the single authoritative layout for all 4 roles, with per-role tab maps baked in.
- **coordinator/layout.php:** Replaced 82-line full layout with 9-line thin wrapper. `render_coach_page()` now delegates to `render_page(['role' => 'coordinator', ...])`.
- **member/layout.php:** Same pattern — thin wrapper, all call sites unchanged.
- **admin/layout.php:** Same pattern — thin wrapper, all call sites unchanged.

---

## Deviations from Plan

None — plan executed exactly as written.

---

## Known Stubs

None — this plan is infrastructure only (no data-rendering templates). No stubs introduced.

---

## Self-Check

### Files created/modified
- [x] `src/templates/components/partials.php` — FOUND
- [x] `public/css/app.css` — modified
- [x] `src/templates/layout.php` — modified
- [x] `src/templates/coordinator/layout.php` — modified
- [x] `src/templates/member/layout.php` — modified
- [x] `src/templates/admin/layout.php` — modified
- [x] `src/templates/login.php` — modified

### Commits
- [x] `4d2df2c` — Task 1 commit
- [x] `cbe3e77` — Task 2 commit

## Self-Check: PASSED
