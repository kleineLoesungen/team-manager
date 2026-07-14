---
phase: 06-calendar-lists-with-date-location-ics-export
plan: 02
subsystem: calendar-ics
tags: [ics, calendar, rfc5545, public-endpoint, utility]
dependency_graph:
  requires: []
  provides: [calendar-utils, ics-export-endpoint]
  affects: [public/index.php]
tech_stack:
  added: []
  patterns: [RFC-5545-VCALENDAR, CRLF-line-endings, public-route-no-auth, PHP-DateTime-modifiers]
key_files:
  created:
    - src/utils/calendar.php
    - src/ics_handler.php
  modified:
    - public/index.php
decisions:
  - "ICS route registered without any auth guard (D-10: intentionally public endpoint)"
  - "Private lists excluded via SQL filter: visibility IN ('public', 'protected') (D-13)"
  - "All-day events: DTSTART;VALUE=DATE and DTEND;VALUE=DATE both set to same date"
  - "UID generated as md5(team_id . '-' . list_id) . '@team-manager.local' for stable deduplication"
  - "Cache-Control: no-cache, no-store on ICS response (D-12: on-demand generation)"
metrics:
  duration: 85s
  completed: "2026-07-14"
  tasks: 2
  files_created: 2
  files_modified: 1
---

# Phase 06 Plan 02: Calendar Utility Helpers and ICS Export Summary

RFC 5545-compliant per-team ICS feed at `/ics/{team_id}.ics` generated on-demand with no auth, plus PHP DateTime boundary helpers for week/month calendar views using German labels.

## What Was Built

### Task 1 — src/utils/calendar.php (commit 7404501)

Three utility functions used by calendar view handlers and the ICS generator:

- `getWeekBoundaries(DateTime $now, int $offset): array` — Returns ISO week Mon–Sun boundaries as `Y-m-d` SQL strings and a German label (e.g. "14.–20. Juli 2026" or cross-month "28. Juli–3. August 2026")
- `getMonthBoundaries(DateTime $now, int $offset): array` — Returns first/last day of a calendar month with German label (e.g. "Juli 2026")
- `escapeIcsField(string $value): string` — RFC 5545 §3.3.11 escaping: backslash first, then CRLF/LF → `\n`, comma → `\,`, semicolon → `\;`

### Task 2 — src/ics_handler.php + public/index.php route (commit 7118e6e)

ICS handler generates a complete `VCALENDAR` document:
- Queries only `public` and `protected` lists with `date IS NOT NULL` (private excluded per D-13)
- Verifies team exists and is active before emitting output
- Each list becomes a `VEVENT` with: stable UID, DTSTAMP (UTC), DTSTART/DTEND (VALUE=DATE all-day), SUMMARY, optional LOCATION
- All line endings are CRLF (`\r\n`) throughout, per RFC 5545
- No auth guard anywhere in handler or route closure

Route registered in `public/index.php` match(true) block before `default =>` case:
```
/ics/(\d+)\.ics  →  src/ics_handler.php  (no require_coordinator/require_member)
```

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

| Item | Result |
|------|--------|
| src/utils/calendar.php exists | FOUND |
| src/ics_handler.php exists | FOUND |
| 06-02-SUMMARY.md exists | FOUND |
| commit 7404501 (calendar.php) | FOUND |
| commit 7118e6e (ics_handler + route) | FOUND |
