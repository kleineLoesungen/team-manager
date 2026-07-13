---
phase: quick-260713-kxn
plan: 01
subsystem: frontend
tags: [css, animations, mobile-ux, landing-page, design]
dependency_graph:
  requires: []
  provides: [card-entrance-animations, mobile-tab-icons, css-transitions, landing-notification-mockup]
  affects: [all-role-layouts, landing-page]
tech_stack:
  added: []
  patterns: [css-animations, css-transitions, mobile-tab-bar]
key_files:
  created: []
  modified:
    - src/templates/layout.php
    - src/templates/coordinator/layout.php
    - src/templates/member/layout.php
    - src/templates/admin/layout.php
    - landing/index.html
decisions:
  - "Use @media (prefers-reduced-motion: no-preference) to gate card animation — accessibility-safe"
  - "Use HTML entities &#10003; and &#9888; for check/warning badges in landing HTML (avoids raw emoji encoding issues)"
  - "Rangliste screen reduced to col-12 col-lg-6 to pair with notification screen on desktop"
metrics:
  duration: "~10 minutes"
  completed: "2026-07-13T13:10:50Z"
  tasks_completed: 2
  files_modified: 5
---

# Phase quick-260713-kxn Plan 01: Optimize Mobile-First Design and Landing Page Summary

**One-liner:** CSS entrance animations, sidebar hover transitions, icon+label mobile tabs, and a 4th landing screen for the email notification compose flow.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Webapp CSS animations and mobile UX polish | 8abdfbf | src/templates/layout.php, coordinator/layout.php, member/layout.php, admin/layout.php |
| 2 | Landing page — add email notification screen mockup | 19c0abe | landing/index.html |

## What Was Built

### Task 1 — CSS Animations and Mobile UX

**src/templates/layout.php:**
- `@keyframes fadeInUp` (opacity + translateY, 0.28s, nth-child stagger 0/0.05/0.10/0.15s), wrapped in `@media (prefers-reduced-motion: no-preference)`
- `.card`: added `transition: box-shadow 0.2s ease, transform 0.2s ease` and `.card:hover` lift effect
- `.nav-link`: smooth 0.15s transitions, subtle indent on hover for inactive links
- `.btn`: 4-property transition + `.btn:active` press-down effect (`translateY(1px)`)
- `.table tbody tr`: row hover with `background-color: rgba(0,0,0,.025)` transition
- `.form-control, .form-select`: border-color and box-shadow transitions
- `.mobile-tab-bar` / `.mobile-tab-link` CSS — scrollable, icon-above-label, 10px top/bottom padding, 2px bottom-border active indicator, brand color on active
- Navbar scroll shadow JS in `render_layout_foot()` — adds box-shadow when `scrollY > 4`

**Role layouts (coordinator, member, admin):**
- All 3 replaced plain `bg-light border-bottom` text-only tab links with the new `mobile-tab-bar` + `mobile-tab-link` structure
- Each link now shows a Bootstrap Icon above the label (icon font-size 1.15rem), giving ~54px touch target height
- Active state driven by `mobile-tab-link active` class (not custom inline classes)

### Task 2 — Landing Page 4th Screen

**landing/index.html:**
- Rangliste screen changed from `col-12` to `col-12 col-lg-6` so it sits side by side with the new screen on desktop
- New Screen 4 added: email notification compose + recipient preview
  - Message compose area (text preview)
  - Link preview row (indigo, truncated URL)
  - Per-member recipient grid with green "E-Mail" badges (&#10003;) and amber "Fehlt" badges (&#9888;)
  - Footer row: summary count + "Senden" button in indigo

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Used HTML entities instead of raw emoji in landing HTML**
- **Found during:** Task 2
- **Issue:** The plan specified ✓ and ⚠ as raw Unicode emoji characters in HTML. These can cause encoding issues in some editors and render inconsistently across platforms/email clients.
- **Fix:** Replaced with `&#10003;` (checkmark) and `&#9888;` (warning sign) HTML entities which render the same visually and are more robust.
- **Files modified:** landing/index.html
- **Commit:** 19c0abe

None others — plan executed cleanly.

## Known Stubs

None — all CSS rules are fully wired; landing page content is intentionally static mockup (by design).

## Self-Check: PASSED

Files exist:
- src/templates/layout.php — FOUND
- src/templates/coordinator/layout.php — FOUND
- src/templates/member/layout.php — FOUND
- src/templates/admin/layout.php — FOUND
- landing/index.html — FOUND

Commits exist:
- 8abdfbf — FOUND (feat: CSS animations, transitions, and mobile tab bar icons)
- 19c0abe — FOUND (feat: add email notification screen mockup)

Key rules verified:
- `fadeInUp` present in layout.php: YES (2 matches)
- `.mobile-tab-bar` present in layout.php: YES (2 matches)
- `mobile-tab-link` present in coordinator/layout.php: YES (5 matches)
- `mobile-tab-link` present in member/layout.php: YES (3 matches)
- `mobile-tab-link` present in admin/layout.php: YES (4 matches)
- `Benachrichtigung senden` in landing/index.html: YES
- PHP lint all 4 files: PASSED
