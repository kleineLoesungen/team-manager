---
phase: quick
plan: 260517-auz
subsystem: admin-settings, coordinator, layout
tags: [logo, favicon, file-upload, image, branding]
dependency_graph:
  requires: []
  provides: [team-logo-upload, default-logo-fallback, favicon-integration]
  affects: [src/templates/layout.php, src/admin/settings_handler.php, src/coordinator/logo_handler.php, public/index.php]
tech_stack:
  added: [finfo MIME detection, move_uploaded_file, readfile image serving]
  patterns: [uploads-on-disk-protected, three-tier-logo-precedence, settings-key-value]
key_files:
  created:
    - src/coordinator/logo_handler.php
    - src/templates/coordinator/logo.php
    - uploads/.htaccess
    - uploads/.gitkeep
  modified:
    - src/db/connection.php
    - database/schema.sql
    - src/admin/settings_handler.php
    - src/templates/layout.php
    - public/index.php
    - public/.htaccess
decisions:
  - Migration numbered 010 (009 was already used by files table); numbering is sequential not semantic
  - uploads/ directory protected by both uploads/.htaccess (deny-all) and public/.htaccess RewriteRule
  - /logo route is unconditional in <head>; browser silently ignores 404 favicon — no server-side session check needed in the HTML
  - Admin default logo uses settings key/value pattern (consistent with app_title, app_color)
  - Team-specific logo stored in teams.logo_path (VARCHAR 500 NULL); precedence enforced at route time, not write time
metrics:
  duration: 252s
  completed: "2026-05-17"
  tasks: 3
  files: 10
---

# Quick Task 260517-auz: Add Team Logo Image Upload (Admin Sets Default, Coordinator Sets Team)

**One-liner:** Three-tier logo system — coordinator uploads per-team PNG/JPG/SVG, admin sets a fallback default, /logo route serves the correct file as a dynamic favicon on all pages.

## What Was Built

- **Migration 010** in `maybe_migrate_db()`: adds `logo_path VARCHAR(500) NULL` to `teams`, inserts `default_team_logo` key into `settings`
- **`/logo` route** in `public/index.php`: serves image from disk with MIME detection (finfo), Cache-Control header, 404 if no logo configured
- **`uploads/` directory** with `.htaccess` deny-all and `uploads/.gitkeep` for git tracking; also blocked via `public/.htaccess` RewriteRule
- **Admin settings** (`/admin/settings`): enctype multipart, optional file upload field for default logo with MIME/size validation (max 2 MB), old file deleted on re-upload, current logo previewed
- **Coordinator logo page** (`/coordinator/logo`): new handler + template, uploads team-specific logo with MIME/size validation, old file deleted on re-upload, success redirect
- **Favicon** in `render_layout_head()`: `<link rel="icon" href="/logo">` injected before `</head>` on every page

## Logo Precedence (enforced in /logo route)

1. If `$_SESSION['team_id']` set → check `teams.logo_path` → serve if non-empty
2. Else → read `settings.default_team_logo` → serve if non-empty
3. Else → HTTP 404 (browser ignores silently for favicons)

Admin default **never** writes to `teams.logo_path`, so coordinator logos are never overwritten.

## Commits

| Task | Commit | Description |
|------|--------|-------------|
| 1 | e3075b8 | DB migration + /logo route + uploads/ storage |
| 2 | 63087c7 | Admin default logo upload + coordinator logo page |
| 3 | 7286ff9 | Inject favicon link into all pages via render_layout_head |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Migration numbered 010 instead of 009 as planned**
- **Found during:** Task 1
- **Issue:** The plan specified "Migration 009" but that number was already used by the files table (markdown content type) added in quick task 260505-sd0
- **Fix:** Numbered the logo migration as 010 to maintain sequential ordering
- **Files modified:** src/db/connection.php

**2. [Rule 2 - Security] Added uploads/.htaccess deny-all in addition to public/.htaccess RewriteRule**
- **Found during:** Task 1
- **Issue:** On Hetzner shared hosting, `uploads/` may be deployed alongside `index.php` in the webroot. A RewriteRule in `public/.htaccess` only blocks if the path passes through the rewrite engine. A deny-all `.htaccess` directly in `uploads/` provides defense-in-depth.
- **Fix:** Created `uploads/.htaccess` with `Deny from all`
- **Files modified:** uploads/.htaccess (created)

**3. [Rule 1 - Bug] Error handling in settings_handler POST block**
- **Found during:** Task 2
- **Issue:** The plan's pseudo-code placed the logo upload block inside the `else` branch and called `redirect()` at the end unconditionally. If logo upload validation fails after title/color have already been saved, the error would be lost and the form would redirect to success.
- **Fix:** Logo upload errors set `$error`; the `redirect()` is guarded by `if ($error === '')` so validation errors are shown to the user.
- **Files modified:** src/admin/settings_handler.php

## Known Stubs

None — all data paths are fully wired.

## Self-Check: PASSED

- FOUND: src/coordinator/logo_handler.php
- FOUND: src/templates/coordinator/logo.php
- FOUND: uploads/.htaccess
- FOUND: uploads/.gitkeep
- FOUND commit: e3075b8
- FOUND commit: 63087c7
- FOUND commit: 7286ff9
