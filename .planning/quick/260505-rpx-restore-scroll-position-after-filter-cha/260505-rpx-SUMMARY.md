# Quick Task 260505-rpx — SUMMARY

**Task:** Restore scroll position after filter change on coordinator stats page
**Date:** 2026-05-05
**Commit:** 8a941f0

## What was done

Added a 9-line vanilla JS IIFE at the bottom of `src/templates/coordinator/stats.php`:

- **On load (immediate):** reads `stats_scroll` from `sessionStorage`, calls `window.scrollTo()`, removes the key
- **On DOMContentLoaded:** attaches a `submit` listener to every `form[method="get"]` that saves `window.scrollY`

Covers all three GET forms on the page:
1. Main filter (list/date/include_undated) — triggered by submit button
2. Column filter — triggered by `onchange="this.form.submit()"` on the select
3. Member selector — triggered by submit button

No dependencies, no frameworks, no changes to PHP logic.
