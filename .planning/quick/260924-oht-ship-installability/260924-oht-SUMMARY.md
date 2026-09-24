---
phase: quick-260924-oht
plan: 01
subsystem: frontend/pwa
tags: [pwa, manifest, icons, mobile, installability]
dependency_graph:
  requires: []
  provides: [web app manifest, static PWA icon set, apple-touch-icon]
  affects: [shared layout head, all roles]
tech_stack:
  added: []
  patterns: [static manifest served via front-controller bypass, GD-generated icon set]
key_files:
  created:
    - bin/generate-pwa-icons.php
    - public/manifest.webmanifest
    - public/icons/icon-192.png
    - public/icons/icon-512.png
    - public/icons/icon-maskable-512.png
    - public/icons/apple-touch-icon-180.png
  modified:
    - src/templates/layout.php
    - public/.htaccess
decisions:
  - "Static manifest file, not a PHP route — .htaccess rewrites to index.php only when the path does not exist on disk, so a real file under public/ bypasses the front controller entirely and needs no routing change"
  - "No service worker — FTP deploy to shared hosting has no build step and no asset hashing, so a cache would pin users to stale CSS with no reliable invalidation path"
  - "Generic Anthrazit #2f3640 icon per UI-BASELINE.md §8, not the per-team brand colour — a per-team manifest would depend on session state at install time"
  - "Maskable glyph scaled to 0.65 so its farthest corner sits at ~59% of half-canvas, inside Android's 80% safe zone"
  - "apple-touch-icon deliberately kept out of the manifest icons[] array — iOS ignores manifest icons for the home screen and uses the <link> tag instead"
metrics:
  duration: "~5 min"
  completed: "2026-09-24"
  tasks: 2
  files: 8
---

# Phase quick-260924-oht Plan 01: Ship Installability Summary

**One-liner:** Team Manager is now installable to the home screen on Android Chrome and iOS Safari — static web app manifest, generated icon set including a maskable variant and iOS apple-touch-icon, wired into the shared layout head. No service worker.

## Tasks Completed

| # | Task | Commit | Files |
|---|------|--------|-------|
| 1 | Generate the PWA icon set via a committed GD script | 8970563 | bin/generate-pwa-icons.php, public/icons/*.png (4 files) |
| 2 | Create the manifest, wire `<head>`, set MIME type, confirm deploy coverage | e6a9be0 | public/manifest.webmanifest, src/templates/layout.php, public/.htaccess |

## Task 3 — Outstanding

Task 3 is a blocking `checkpoint:human-verify`. It requires installing on a physical Android and iOS device and cannot be automated. **Not yet performed.** See "Verification" below for what was and was not confirmed.

## What Was Built

- **`public/manifest.webmanifest`** — static manifest: `display: standalone`, `start_url`/`scope` `/`, `lang: de`, `theme_color #2f3640`, three icon entries (192 any, 512 any, 512 maskable).
- **`public/icons/`** — four PNGs generated from a white "T" monogram on a full-bleed Anthrazit `#2f3640` field: `icon-192`, `icon-512`, `icon-maskable-512` (glyph at 0.65 scale for Android's adaptive crop), `apple-touch-icon-180` (opaque, for iOS).
- **`bin/generate-pwa-icons.php`** — committed, re-runnable GD script. Icons are reproducible rather than hand-made binaries; re-run with `php bin/generate-pwa-icons.php`.
- **`src/templates/layout.php`** — four lines added to `<head>`: `rel="manifest"`, `rel="apple-touch-icon"`, `theme-color`, `apple-mobile-web-app-title`. The pre-existing dynamic per-team favicon `<link rel="icon" href="/logo">` is untouched.
- **`public/.htaccess`** — `AddType application/manifest+json .webmanifest`, wrapped in `<IfModule mod_mime.c>` since Hetzner's Apache may not know the extension.

## Verification

Confirmed automatically:
- Both plan `<verify>` blocks pass.
- All four PNGs exist at exact target dimensions (192, 512, 512, 180) and render correctly on visual inspection; the maskable glyph is visibly inside the safe zone.
- Manifest parses as valid JSON.
- Served over HTTP: manifest returns `200 application/manifest+json`, all four icons return `200 image/png`.
- No service worker file exists anywhere in the repo.
- `public/index.php` and `deploy.sh` unmodified; both commits contain only the eight intended files.

NOT confirmed — requires the Task 3 device check:
- Actual install flow and standalone launch on Android Chrome.
- Actual install flow, icon crispness and standalone launch on iOS Safari.
- `Content-Type` header on the real Hetzner Apache (only the PHP dev server was exercised locally).

## Notes for Future Phases

- **Chrome will not show its automatic install prompt.** That requires a service worker with a fetch handler. Install is via the browser's own menu on both platforms. This was an accepted tradeoff, not an oversight.
- **Push notifications for the live ticker were split into a separate phase.** Web Push requires a service worker, so the "no service worker" decision here will need revisiting at that point — the recommended shape is a push-only worker (`push` + `notificationclick` handlers, plus a no-op `fetch` listener to satisfy Chrome's installability check) that caches nothing, preserving the staleness-safety reasoning above. That phase also needs VAPID keys, a `push_subscriptions` table, and Web Push crypto (VAPID JWT + AES128GCM) in a project with no Composer.
- `theme_color` in the manifest is necessarily static, while `layout.php` injects a per-team `--brand` colour inline. Minor and accepted; a per-team manifest was explicitly ruled out.
