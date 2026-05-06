---
phase: quick
plan: 260506-jb7
subsystem: database / deploy / docs
tags: [cleanup, migrations, release-prep, readme]
dependency_graph:
  requires: []
  provides: [clean-database-folder, clean-deploy-script, accurate-readme]
  affects: [database/, deploy.sh, README.md]
tech_stack:
  added: []
  patterns: []
key_files:
  created: []
  modified:
    - deploy.sh
    - README.md
  deleted:
    - database/migration_008_moderator_to_coordinator.sql
decisions:
  - Applied migration_008 deleted from repository; history preserved in git log
  - README intro and technical notes updated to current role names (Koordinator/Mitglied)
metrics:
  duration: "~5 minutes"
  completed: "2026-05-06"
  tasks_completed: 2
  files_changed: 3
---

# Quick Task 260506-jb7: Remove migrations from source — release cleanup

**One-liner:** Deleted migration_008, removed stale migrate_004/005 echo lines from deploy.sh, and updated README role names to Koordinator/Mitglied.

## Tasks Completed

| # | Name | Commit |
|---|------|--------|
| 1 | Delete migration_008 and clean deploy.sh migration references | e1ffa87 |
| 2 | Verify and update README.md for release accuracy | 3e23a9f |

## What Was Done

**Task 1 — Delete migration_008 + clean deploy.sh:**
- `git rm database/migration_008_moderator_to_coordinator.sql` — this one-time script (rename moderator→coordinator) was applied to production and no longer belongs in the repo
- Removed the trailing 4-line block from deploy.sh that echoed instructions for `migrate_004_rename_roles.sql` and `migrate_005_rename_member.sql` (both deleted in quick task 260504-rg6)
- deploy.sh now ends cleanly after "DB tables are created automatically on the first HTTP request."

**Task 2 — README accuracy:**
- Intro sentence: "Trainer" → "Koordinatoren", "Spieler" → "Mitglieder"
- RLS note: "Coach/Spieler-Requests" → "Koordinator/Mitglied-Requests"
- Projektstruktur: `admin/` comment "Trainer" → "Koordinatoren"
- Verified: no `migrate_*` references anywhere in README

## Verification

```
database/: rls_policies.sql  schema.sql     ← exactly two files, no migrations
grep migrate_ database/ deploy.sh README.md → no matches
```

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- `database/` contains only `schema.sql` and `rls_policies.sql` — FOUND
- `migration_008_moderator_to_coordinator.sql` removed — CONFIRMED (git rm)
- Commits e1ffa87 and 3e23a9f — FOUND
- No `migrate_` references in deploy.sh or README.md — CONFIRMED
