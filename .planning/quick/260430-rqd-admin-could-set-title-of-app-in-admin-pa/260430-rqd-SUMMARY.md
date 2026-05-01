---
id: 260430-rqd
description: "admin could set title of app in admin panel. as player and coach you see app title + team name"
completed: "2026-05-01"
duration_seconds: 74
tasks_completed: 3
files_changed: 5
files_created: 1
commits:
  - 842508f
  - 38b8aa7
  - 3c21e8a
key_decisions:
  - "settings table uses simple key/value (VARCHAR PRIMARY KEY + TEXT) — no over-engineering for a single config value"
  - "render_navbar() uses PHP static variable to cache app_title for the request lifetime — one DB query per request regardless of how many components render the navbar"
  - "try/catch in render_navbar() ensures graceful fallback before migration is applied to existing DB"
---

# Quick Task 260430-rqd: App Title Branding — Summary

**One-liner:** Admin-configurable app title stored in `settings` table; navbar shows `{app_title} · {team_name}` for coaches/players, just `{app_title}` for admin.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Schema + session team_name | 842508f | `database/schema.sql`, `src/auth/login_handler.php` |
| 2 | Admin settings route + page | 38b8aa7 | `public/index.php`, `src/templates/admin/layout.php`, `src/admin/settings_handler.php` (new) |
| 3 | Navbar shows app title + team name | 3c21e8a | `src/templates/layout.php` |

## What Was Built

- **`settings` table** — key/value pairs in PostgreSQL; seeded with `('app_title', 'Team Manager')` on first run via `ON CONFLICT DO NOTHING`
- **`$_SESSION['team_name']`** — populated at login after RLS context is set; admin session never gets this (correct — admin is config-based, not team-based)
- **`/admin/settings`** — GET renders form, POST validates non-empty, upserts via `ON CONFLICT (key) DO UPDATE`, PRG redirect on success
- **Admin nav** — third item "Einstellungen" added to both desktop sidebar and mobile tabs
- **`render_navbar()`** — fetches `app_title` with PHP `static` cache; appends ` · {team_name}` for non-admin sessions; falls back to `'Team Manager'` on any DB error (safe before migration)

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — settings table is fully wired. Admin can set the title; navbar reads it on every request.

## Self-Check: PASSED

- `src/admin/settings_handler.php` — FOUND
- `database/schema.sql` — FOUND
- commit `842508f` — FOUND
- commit `38b8aa7` — FOUND
- commit `3c21e8a` — FOUND
