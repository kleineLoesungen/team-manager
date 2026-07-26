---
phase: 07-live-ticker
plan: "03"
subsystem: coordinator-ticker
tags: [ticker, coordinator, crud, messages, freigabe]
dependency_graph:
  requires: [07-01]
  provides: [coordinator-ticker-handlers, coordinator-ticker-templates]
  affects: [public/index.php routing in 07-05]
tech_stack:
  added: []
  patterns: [PRG, two-step-delete, mb_strlen-validation, transaction-for-batch-insert, ownership-check-via-team_id]
key_files:
  created:
    - src/coordinator/ticker_handler.php
    - src/coordinator/ticker_create_handler.php
    - src/coordinator/ticker_detail_handler.php
    - src/coordinator/ticker_close_handler.php
    - src/coordinator/ticker_delete_handler.php
    - src/templates/coordinator/ticker.php
    - src/templates/coordinator/ticker_form.php
    - src/templates/coordinator/ticker_detail.php
    - src/templates/coordinator/ticker_delete_confirm.php
  modified: []
decisions:
  - "ticker_delete_handler supports GET (show confirm) + POST (execute delete) unlike list_delete_handler which is POST-only"
  - "Coordinator can post/edit messages on closed tickers (for corrections) — no status gate on message form"
  - "edit_message flow uses ?edit_message_id query param on GET, conditional form render in template"
metrics:
  duration_minutes: 5
  completed_date: "2026-07-26"
  tasks_completed: 2
  files_created: 9
  files_modified: 0
---

# Phase 07 Plan 03: Coordinator Ticker Handlers and Templates Summary

Coordinator ticker CRUD with full message lifecycle: 5 handlers (list, create, detail, close, delete) + 4 templates; all POST forms CSRF-protected, PRG pattern throughout, message validation uses mb_strlen for UTF-8 correctness.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Ticker list handler + create handler + list template + create/edit form template | 251b6c9 | 4 files created |
| 2 | Ticker detail handler + close handler + delete handler + detail template + delete confirm template | 8e2b48b | 5 files created |

## What Was Built

### Handlers (5 files)

**ticker_handler.php** — `GET /coordinator/ticker`
Fetches all team tickers ordered by active-first then by created_at DESC. Passes `$success` flash message from `?success=created|closed|deleted`.

**ticker_create_handler.php** — `GET/POST /coordinator/ticker/new`
GET serves the create form with active members for freigabe checkboxes. POST validates name (mb_strlen, max 255), opens a transaction to INSERT the ticker and batch-INSERT `ticker_members` rows for selected members, then redirects to the ticker detail page.

**ticker_detail_handler.php** — `GET/POST /coordinator/ticker/{id}`
Ownership check (ticker WHERE id=? AND team_id=?). Dispatches POST to three actions: `post_message` (INSERT with mb_strlen max 280), `delete_message` (DELETE by id+ticker_id), `edit_message` (UPDATE). GET loads messages with LEFT JOIN to `ticker_tags`, all team tags, and freigabe members. `?edit_message_id=X` pre-loads a message for the edit form.

**ticker_close_handler.php** — `POST /coordinator/ticker/{id}/close`
Single-action handler: validates CSRF, UPDATEs `status = 'closed'` with team_id ownership check, redirects to list.

**ticker_delete_handler.php** — `GET/POST /coordinator/ticker/{id}/delete`
GET renders the delete confirmation template. POST validates CSRF and DELETEs the ticker (messages cascade via FK ON DELETE CASCADE). Redirects to list.

### Templates (4 files)

**ticker.php** — Ticker list with `bg-success`/`bg-secondary` status badges, empty state, inline close form per active ticker, delete link.

**ticker_form.php** — Create form: name, description, scrollable freigabe checkboxes (active members), CSRF token.

**ticker_detail.php** — Detail view: ticker header with status badge + close/delete actions, freigabe member list, conditional post/edit form (post form when no edit target, edit form when `$edit_message` is set), JS char counter (oninput), message feed newest-first with edit link + delete form per message.

**ticker_delete_confirm.php** — Danger card styled with `border-danger`/`bg-danger`, includes "dauerhaft gelöscht" warning text and POST confirmation form.

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None — handlers query real DB tables (`tickers`, `ticker_messages`, `ticker_tags`, `ticker_members`). Tags list will be empty until plan 07-04 creates the tag management UI, but the query runs without error.

## Self-Check: PASSED

Files created:
- src/coordinator/ticker_handler.php: FOUND
- src/coordinator/ticker_create_handler.php: FOUND
- src/coordinator/ticker_detail_handler.php: FOUND
- src/coordinator/ticker_close_handler.php: FOUND
- src/coordinator/ticker_delete_handler.php: FOUND
- src/templates/coordinator/ticker.php: FOUND
- src/templates/coordinator/ticker_form.php: FOUND
- src/templates/coordinator/ticker_detail.php: FOUND
- src/templates/coordinator/ticker_delete_confirm.php: FOUND

Commits verified: 251b6c9, 8e2b48b
