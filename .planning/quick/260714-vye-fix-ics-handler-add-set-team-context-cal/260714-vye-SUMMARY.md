# Quick Task 260714-vye: Fix ICS Handler RLS Context

**Date:** 2026-07-14  
**Commit:** d37b010

## What Was Done

Added `set_team_context($pdo, $team_id)` call in `src/ics_handler.php` immediately after `get_db()` and before the team-exists check.

## Root Cause

The ICS endpoint is intentionally unauthenticated (D-10), so it never goes through the normal session/auth flow that calls `set_team_context()`. Without the RLS session variables set, `NULLIF(current_setting('app.current_team_id', true), '')::integer` evaluates to NULL, causing `team_id = NULL` to be always false — blocking both the team-exists check and the lists query.

## Fix

`src/ics_handler.php` — 3 lines added between `get_db()` and the team-exists query:

```php
// Set RLS context so public/protected lists are visible without auth (D-10)
set_team_context($pdo, $team_id);
```

The `lists_visibility_select` RLS policy's third branch — `visibility IN ('public', 'protected') AND team_id = ?` — now matches, returning public and protected dated lists as intended.
