---
phase: quick
plan: 260729-ijq
subsystem: templates
tags: [ux, clipboard, share, credentials, mobile]
dependency_graph:
  requires: []
  provides: [share-button-templates, credential-copy-all]
  affects: [coordinator/list_detail, coordinator/file_detail, member/list_detail, member/file_detail, admin/credential_modal]
tech_stack:
  added: []
  patterns: [navigator.clipboard with execCommand fallback, data-share attribute pattern, PHP share URL construction]
key_files:
  created: []
  modified:
    - src/templates/admin/credential_modal.php
    - src/templates/coordinator/list_detail.php
    - src/templates/coordinator/file_detail.php
    - src/templates/member/list_detail.php
    - src/templates/member/file_detail.php
decisions:
  - "Share text embedded as data-share HTML attribute (htmlspecialchars encoded) rather than inline JS variable — avoids JS injection risk"
  - "clipboardFallback extracted as named helper in credential_modal to allow code reuse across copyToClipboard and copyAll"
  - "execCommand fallback added to existing copyToClipboard in credential_modal (Rule 2 — missing fallback for older mobile browsers)"
metrics:
  duration_minutes: 8
  completed: "2026-07-29"
  tasks_completed: 2
  files_modified: 5
---

# Quick Task 260729-ijq: Share Button + One-Click Credential Copy Summary

**One-liner:** Share button on all four list/doc detail pages copies "[TeamName] Titel - URL" to clipboard; credential modal gains "Alles kopieren" for one-tap username+password copy.

## What Was Built

### Task 1: "Alles kopieren" in Credential Modal

Added to `src/templates/admin/credential_modal.php`:
- New `copyAll(btn)` JS function reads `#cred-username` and `#cred-password` textContent and builds a two-line string: `Benutzername: X\nPasswort: Y`
- New `clipboardFallback(text, btn, originalLabel)` helper extracted for shared use by both `copyToClipboard` and `copyAll`
- "Alles kopieren" button placed in modal footer between timer text and Schließen
- Button shows "Kopiert!" for 2 seconds then restores "Alles kopieren"
- Individual per-field Kopieren buttons unchanged and still functional
- execCommand fallback added to `copyToClipboard` (previously missing)

### Task 2: "Teilen" Share Button on Four Templates

Added to all four list/doc detail templates:
- PHP snippet computes `$_share_url` (HTTP_HOST + REQUEST_URI stripped of query params via `strtok(..., '?')`) and `$_share_text` as `[TeamName] Titel - https://...`
- Team name from `$_SESSION['team_name']` with `'Team'` fallback
- Share text embedded as `data-share` attribute with `htmlspecialchars(ENT_QUOTES)` encoding
- `shareItem(btn)` + `shareFallback(text, btn)` JS functions added per template
- Button: `btn-sm btn-outline-secondary min-touch` with Bootstrap Icons share icon

**Placement per template:**
- `coordinator/list_detail.php` — first button in the top-right `d-flex gap-2` button group, before email/Einstellungen buttons
- `coordinator/file_detail.php` — after back-link, before email notification button in `mb-3 d-flex gap-2 flex-wrap` row
- `member/list_detail.php` — inside the header `d-flex justify-content-between` row alongside back link and date
- `member/file_detail.php` — `div.mb-3` changed to `d-flex gap-2 flex-wrap`; button sits beside back link

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing functionality] Added execCommand fallback to existing copyToClipboard**
- **Found during:** Task 1
- **Issue:** The existing `copyToClipboard` function in credential_modal.php had no fallback if `navigator.clipboard` was unavailable or rejected (e.g. older Android WebViews). The plan's copyAll implementation required a shared fallback helper anyway.
- **Fix:** Extracted `clipboardFallback(text, btn, originalLabel)` helper and wired it into both `copyToClipboard` and `copyAll` as the `.catch()` / else branch.
- **Files modified:** `src/templates/admin/credential_modal.php`
- **Commit:** 6de1e1c

## Known Stubs

None — all share text values are computed from live session and server data; no hardcoded placeholders.

## Self-Check: PASSED

- `src/templates/admin/credential_modal.php` — modified, "Alles kopieren" button present
- `src/templates/coordinator/list_detail.php` — modified, Teilen button + shareItem JS present
- `src/templates/coordinator/file_detail.php` — modified, Teilen button + shareItem JS present
- `src/templates/member/list_detail.php` — modified, Teilen button + shareItem JS present
- `src/templates/member/file_detail.php` — modified, Teilen button + shareItem JS present
- Commit 6de1e1c: credential modal task
- Commit 7b52066: share button task
