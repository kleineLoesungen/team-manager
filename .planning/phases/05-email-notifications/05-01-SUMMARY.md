---
phase: 05-email-notifications
plan: "01"
subsystem: infra
tags: [php, email, phpmailer, smtp, database, config]

# Dependency graph
requires: []
provides:
  - "email VARCHAR(255) NULL column in team_manager.users table"
  - "7 MAIL_* config constants in config.php (driver, host, port, username, password, from-address, from-name)"
  - "send_notification_email() with header injection prevention and UTF-8 handling"
  - "compose_list_notification_body(), compose_file_notification_body(), compose_admin_notification_body() body helpers"
affects:
  - 05-02-member-email-profile
  - 05-03-coordinator-notify-list
  - 05-04-coordinator-notify-file
  - 05-05-admin-notify-coordinators

# Tech tracking
tech-stack:
  added:
    - "PHPMailer ^6.9 (optional, only when MAIL_DRIVER=smtp)"
  patterns:
    - "MAIL_DRIVER=mail|smtp toggle pattern — safe default, SMTP upgrade path via env var"
    - "Dev-mode email logging: APP_ENV=development logs to error_log instead of sending"
    - "Header injection prevention: str_replace([\r, \n, \0], '', $subject)"
    - "getenv() pattern for all credentials — no hardcoded values"

key-files:
  created:
    - src/utils/email_composer.php
  modified:
    - database/schema.sql
    - config.php

key-decisions:
  - "MAIL_DRIVER=mail default (PHP built-in mail()) with optional SMTP upgrade via env var — no forced Composer dependency"
  - "email column is NULL-able — existing members have no email; population handled in later plans (05-02)"
  - "Dev-mode guard uses APP_ENV=development to suppress sends and log bodies — identical code path in prod"
  - "PHPMailer loaded conditionally only when MAIL_DRIVER=smtp and vendor/ exists — no autoload required for mail() driver"

patterns-established:
  - "Email body composition: always plain-text, German greeting 'Hallo {name}', separator ---"
  - "Credential safety: MAIL_PASSWORD never appears in error_log() calls"
  - "Defense-in-depth: filter_var(FILTER_VALIDATE_EMAIL) in send function even if caller validates"

requirements-completed: [EMAIL-01, EMAIL-02, EMAIL-03, EMAIL-04, EMAIL-07, EMAIL-08]

# Metrics
duration: 20min
completed: 2026-07-13
---

# Phase 5 Plan 01: Email Notifications Foundation Summary

**PHP mail() + optional PHPMailer SMTP email infrastructure with header injection prevention, UTF-8 support, and dev-mode logging — ready for all Phase 5 notification features**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-07-13
- **Completed:** 2026-07-13
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Added nullable `email VARCHAR(255)` column to `team_manager.users` in schema.sql with idempotent migration comment for live DB
- Added 7 `MAIL_*` constants to config.php reading from environment variables — zero hardcoded credentials
- Created `src/utils/email_composer.php` with `send_notification_email()`, three body composition helpers, PHPMailer SMTP path, and dev-mode logging

## Task Commits

Each task was committed atomically:

1. **Task 1: DB email column + MAIL config constants** - `9e51d0f` (feat)
2. **Task 2: Email utility — send_notification_email() + body composers** - `5a9b1aa` (feat)

## Files Created/Modified

- `database/schema.sql` — Added `email VARCHAR(255) NULL` inside CREATE TABLE team_manager.users; added migration comment for live DB ALTER TABLE
- `config.php` — Appended 7 MAIL_* constants: MAIL_DRIVER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS, MAIL_FROM_NAME
- `src/utils/email_composer.php` — New file: `send_notification_email()`, `_send_via_phpmailer()`, `compose_list_notification_body()`, `compose_file_notification_body()`, `compose_admin_notification_body()`

## Decisions Made

- **MAIL_DRIVER default is `mail`** — PHP built-in, no Composer dependency required. SMTP opt-in via env var for production use.
- **email column is NULL-able** — Existing users have no email; plan 05-02 adds the member profile edit flow to populate it.
- **PHPMailer loaded conditionally** — Only required_once when MAIL_DRIVER=smtp and vendor/autoload.php exists; mail() path has zero Composer dependency.
- **Dev-mode log guard** — APP_ENV=development suppresses actual sends and logs to error_log; allows safe local testing with identical code path.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

**Live database requires a manual migration before Phase 5 features work:**

```sql
ALTER TABLE team_manager.users ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL;
```

**Environment variables to configure in production `.env` / server config:**

| Variable | Required | Description |
|---|---|---|
| MAIL_DRIVER | No | `mail` (default) or `smtp` |
| MAIL_FROM_ADDRESS | Yes | Sender email address |
| MAIL_FROM_NAME | No | Display name (default: "Team Manager") |
| MAIL_HOST | Only if SMTP | SMTP server hostname |
| MAIL_PORT | Only if SMTP | SMTP port (default: 587) |
| MAIL_USERNAME | Only if SMTP | SMTP auth username |
| MAIL_PASSWORD | Only if SMTP | SMTP auth password |

For local development, set `APP_ENV=development` to suppress sends and log email content to PHP error log instead.

## Next Phase Readiness

- **05-02 (Member email profile):** Ready — email column exists, send_notification_email() available
- **05-03 (Coordinator notify list):** Ready — body composer compose_list_notification_body() implemented
- **05-04 (Coordinator notify file):** Ready — body composer compose_file_notification_body() implemented
- **05-05 (Admin notify coordinators):** Ready — body composer compose_admin_notification_body() implemented
- **Blocker:** Live DB ALTER TABLE must be run before any Phase 5 feature can store member email addresses

---
*Phase: 05-email-notifications*
*Completed: 2026-07-13*
