---
phase: 08-player-club-management
plan: "05"
subsystem: admin-attributes
tags: [admin, player-attributes, eav-schema, crud]
dependency_graph:
  requires: [08-04]
  provides: [player_attribute_groups CRUD, player_attributes CRUD, /admin/attributes routes]
  affects: [admin nav, coordinator/member attribute reads in future plans]
tech_stack:
  added: []
  patterns: [LEFT JOIN + PHP group-by, form-switch toggles, inline CRUD forms per-group-card]
key_files:
  created:
    - src/admin/attributes_handler.php
    - src/admin/attribute_group_action_handler.php
    - src/admin/attribute_action_handler.php
    - src/templates/admin/attributes.php
  modified:
    - public/index.php
decisions:
  - "Passed $error from handler to template via render_admin_page closure (consistent with clubs pattern)"
  - "Boolean flags passed as 'true'/'false' strings to PDO to avoid PDO emulation issues with BOOLEAN columns"
metrics:
  duration_seconds: 454
  completed_date: "2026-08-01"
  tasks_completed: 2
  files_created: 4
  files_modified: 1
---

# Phase 8 Plan 5: Player Attribute Schema Admin UI Summary

**One-liner:** Admin can define player attribute groups (e.g. "Kontakt") and attributes with player visibility flags via a card-based page at /admin/attributes.

## What Was Built

Added admin UI for managing the player attribute EAV schema — the two tables that define what extended profile fields exist and who can see/edit them. No attribute values are stored here; only the schema definitions used by future coordinator/member views.

### Files Created

**src/admin/attributes_handler.php**
- LEFT JOINs `player_attribute_groups` with `player_attributes` ordered by sort_order
- Groups flat rows into nested PHP array keyed by group_id for template rendering
- Passes `$groups` and `$error` to `render_admin_page()` closure

**src/admin/attribute_group_action_handler.php**
- POST handler for create / edit / delete group actions
- CSRF-protected; validates name (non-empty, max 100 chars)
- DELETE relies on ON DELETE CASCADE to remove child attributes + their values

**src/admin/attribute_action_handler.php**
- POST handler for create / edit / delete attribute actions within a group
- CSRF-protected; validates name (non-empty, max 100 chars)
- Handles `visible_to_player` and `editable_by_player` boolean flags from form checkboxes
- DELETE relies on ON DELETE CASCADE to remove `player_attribute_values`

**src/templates/admin/attributes.php**
- "Neue Gruppe" inline create form at top of page
- Per-group Bootstrap card with: group name/sort_order inline edit, delete button with JS confirm
- Nested attribute table with inline edit row per attribute (form-switch toggles for booleans)
- Delete button per attribute with JS confirm
- "Neues Attribut" inline create form at bottom of each card
- Empty-state callout when no groups exist
- All user values escaped with `e()`, all 6 forms include `csrf_field()`

**public/index.php** — 3 new route patterns added:
- `GET /admin/attributes` → attributes_handler.php
- `POST /admin/attributes/groups/create|{id}/edit|delete` → attribute_group_action_handler.php
- `POST /admin/attributes/{group_id}/attributes/create|{id}/edit|delete` → attribute_action_handler.php

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — this plan only defines the schema management UI. Attribute values (read/write by coordinators/members) are wired in subsequent plans.

## Self-Check: PASSED

Files verified:
- src/admin/attributes_handler.php: FOUND
- src/admin/attribute_group_action_handler.php: FOUND
- src/admin/attribute_action_handler.php: FOUND
- src/templates/admin/attributes.php: FOUND
- Routes in public/index.php: FOUND (4 lines matching /admin/attributes)

Commits verified:
- f95e2a5: feat(08-05): add player attribute handlers
- 5ab8fcc: feat(08-05): add attributes.php template and register routes
