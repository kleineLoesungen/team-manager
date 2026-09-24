---
phase: quick-260924-oht
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - bin/generate-pwa-icons.php
  - public/icons/icon-192.png
  - public/icons/icon-512.png
  - public/icons/icon-maskable-512.png
  - public/icons/apple-touch-icon-180.png
  - public/manifest.webmanifest
  - src/templates/layout.php
  - public/.htaccess
autonomous: false
requirements: [QUICK-260924-oht]
must_haves:
  truths:
    - "User can install the app to their home screen from the browser menu on Android Chrome and iOS Safari"
    - "Installed app launches standalone, without browser address bar/chrome"
    - "Home screen icon is crisp, brand-appropriate artwork — not a blurry page screenshot (iOS fallback)"
    - "Android's adaptive/maskable icon is not clipped when the OS crops it to a circle"
    - "manifest.webmanifest is served with a MIME type browsers accept, so the manifest actually parses"
  artifacts:
    - path: "public/manifest.webmanifest"
      provides: "Static Web App Manifest — name, icons, display: standalone, start_url, scope, lang: de"
      contains: "\"display\": \"standalone\""
    - path: "public/icons/icon-192.png"
      provides: "192x192 standard install icon"
    - path: "public/icons/icon-512.png"
      provides: "512x512 standard install icon"
    - path: "public/icons/icon-maskable-512.png"
      provides: "512x512 maskable icon, glyph confined to Android's safe zone"
    - path: "public/icons/apple-touch-icon-180.png"
      provides: "180x180 opaque icon for iOS home screen (iOS ignores the manifest for this)"
    - path: "bin/generate-pwa-icons.php"
      provides: "Committed, re-runnable GD script that generates all 4 PNGs above"
      contains: "function draw_icon"
    - path: "src/templates/layout.php"
      provides: "<head> now links the manifest and apple-touch-icon, sets theme-color"
      contains: "rel=\"manifest\""
    - path: "public/.htaccess"
      provides: "Correct MIME type for .webmanifest so browsers parse it"
      contains: "application/manifest+json"
  key_links:
    - from: "src/templates/layout.php"
      to: "public/manifest.webmanifest"
      via: "<link rel=\"manifest\" href=\"/manifest.webmanifest\">"
      pattern: "rel=\"manifest\""
    - from: "src/templates/layout.php"
      to: "public/icons/apple-touch-icon-180.png"
      via: "<link rel=\"apple-touch-icon\" href=\"/icons/apple-touch-icon-180.png\">"
      pattern: "apple-touch-icon"
    - from: "public/manifest.webmanifest"
      to: "public/icons/*.png"
      via: "manifest icons[] array src paths"
      pattern: "/icons/icon-"
    - from: "public/.htaccess"
      to: "public/manifest.webmanifest"
      via: "AddType application/manifest+json .webmanifest"
      pattern: "manifest\\+json"
---

<objective>
Make Team Manager installable to the home screen on Android Chrome and iOS Safari: a static web app manifest, a generated icon set (including a maskable variant and an iOS apple-touch-icon), and the `<head>` wiring to connect them — with zero service worker and zero offline caching.

Purpose: Users currently get a bare browser tab with no home-screen presence. Adding a manifest + icons lets them install the app like a native one (standalone window, real icon) via the browser's own install menu. Per locked decision, no service worker is shipped — Chrome/Android's automatic install *prompt* won't fire without one, but manual install via the browser menu works on both platforms, which is the accepted scope for this task.
Output: `public/manifest.webmanifest`, 4 PNG icons under `public/icons/`, a re-runnable icon generator script, and `<head>` changes in the shared layout.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/UI-BASELINE.md

<!-- Design source + constraints the executor needs -->
<interfaces>
<!-- UI-BASELINE.md §8: "Team-Logos färben das Theme nicht ein. Primärfarbe ist immer Anthrazit #2f3640."
     The app's fixed primary color is Anthrazit #2f3640 — use this as the icon background,
     NOT the per-team brand color (that would require a dynamic manifest, which is explicitly out of scope). -->

<!-- landing/icon.svg — the existing product-landing icon, a "T" monogram (Team Manager).
     Reuse the same glyph shape for visual continuity, recolored to the app's Anthrazit per UI-BASELINE
     (the landing page's indigo #6366f1 is marketing-only, not the app's primary color): -->
<svg viewBox="0 0 32 32">
  <rect width="32" height="32" rx="7" fill="#6366f1"/>
  <rect x="5" y="7" width="22" height="5" rx="2.5" fill="white"/>
  <rect x="13" y="7" width="6" height="19" rx="2.5" fill="white"/>
</svg>
<!-- Treat this viewBox as the coordinate space to scale from. Skip the rx corner-rounding on the
     background for the generated icons (see Task 1 rationale) — sharp-square full-bleed background,
     OS applies its own icon-shape mask on top. -->

<!-- src/templates/layout.php:35-50 — current <head>, exact block to edit in Task 2 -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= $full_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" ...>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet" ...>
    <link rel="stylesheet" href="/css/app.css">
    <style>:root{--brand:<?= $safe_color ?>;}</style>
    <link rel="icon" href="/logo">
</head>
<!-- Keep `<link rel="icon" href="/logo">` exactly as-is — that's the dynamic per-team favicon,
     unrelated to the static install icons this plan adds. Do not touch public/index.php. -->

<!-- deploy.sh:40-43 — confirmed by inspection, no change needed:
mirror --reverse \
    public/ public_html/team-manager/
     lftp `mirror --reverse` recurses into the local public/ tree by default (this is already how
     public/css/ reaches the server today). A new public/icons/ subdirectory is picked up
     automatically — no deploy.sh edit required. Task 2 includes a check that confirms this;
     it does not modify deploy.sh. -->
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Generate the PWA icon set via a committed GD script</name>
  <files>bin/generate-pwa-icons.php, public/icons/icon-192.png, public/icons/icon-512.png, public/icons/icon-maskable-512.png, public/icons/apple-touch-icon-180.png</files>
  <action>
Create `bin/generate-pwa-icons.php` — a standalone CLI script (no framework bootstrap, no DB) using the `gd` extension (verified loaded) to draw a generic "T" monogram (Team Manager) in white on a solid Anthrazit `#2f3640` background, per UI-BASELINE.md §8's fixed primary color. This is a static, non-per-team icon per the locked decision.

The script must define one reusable function:

```php
function draw_icon(int $size, float $glyph_scale, string $out_path): void
```

- Fill the entire `$size`x`$size` canvas edge-to-edge with `#2f3640` (no transparency, no rounded corners baked in — the OS applies its own icon-shape mask on top of "any"/apple-touch icons, and Android's maskable spec requires a full-bleed background with no baked-in shape).
- Draw the "T" glyph in white by transforming the two rectangles from the 32-unit viewBox in `<interfaces>` above (horizontal bar `x5,y7,w22,h5`; vertical stem `x13,y7,w6,h19`) into the target canvas: scale each coordinate by `$size/32`, then scale around the canvas center by `$glyph_scale` (shrinks/grows the glyph without moving its center — compute the original bbox center at 32-space (16, 16.5) as the pivot). Plain `imagefilledrectangle()` calls are fine — skip corner rounding on the glyph itself, it's visually negligible at these sizes and keeps the script simple and robust.
- Save as PNG via `imagepng()`, then `imagedestroy()`.

Call it to produce all 4 files:
- `draw_icon(192, 1.0, .../public/icons/icon-192.png)` — manifest icon, purpose "any"
- `draw_icon(512, 1.0, .../public/icons/icon-512.png)` — manifest icon, purpose "any"
- `draw_icon(512, 0.65, .../public/icons/icon-maskable-512.png)` — manifest icon, purpose "maskable". The 0.65 scale keeps the glyph's farthest corner well inside Android's safe-zone circle (80% diameter): the unscaled glyph's farthest point from center sits at roughly 91% of the half-canvas radius, so a 0.65 scale brings it to roughly 59% — comfortably inside the 80% safe-zone limit.
- `draw_icon(180, 1.0, .../public/icons/apple-touch-icon-180.png)` — iOS home-screen icon. Must be fully opaque (it is, by construction) — iOS renders transparent pixels badly and applies its own corner rounding on top.

Use `__DIR__ . '/../public/icons/'` for the output path so the script works regardless of cwd; create the directory with `mkdir(..., 0755, true)` if missing. Guard the top of the script with `if (!extension_loaded('gd')) { fwrite(STDERR, "GD extension not available.\n"); exit(1); }`.

Run the script after writing it: `php bin/generate-pwa-icons.php`.
  </action>
  <verify>
    <automated>php -l bin/generate-pwa-icons.php &amp;&amp; php bin/generate-pwa-icons.php &amp;&amp; php -r '$exp=["public/icons/icon-192.png"=>192,"public/icons/icon-512.png"=>512,"public/icons/icon-maskable-512.png"=>512,"public/icons/apple-touch-icon-180.png"=>180]; $ok=true; foreach ($exp as $f=>$s) { if (!is_file($f)) { fwrite(STDERR,"MISSING $f\n"); $ok=false; continue; } [$w,$h]=getimagesize($f); if ($w!==$s||$h!==$s) { fwrite(STDERR,"BAD SIZE $f: {$w}x{$h}\n"); $ok=false; } } echo $ok ? "ALL OK\n" : "FAIL\n"; exit($ok?0:1);'</automated>
  </verify>
  <done>bin/generate-pwa-icons.php exists, lints clean, and running it produces all 4 PNGs at their exact target dimensions (192, 512, 512, 180) under public/icons/.</done>
</task>

<task type="auto">
  <name>Task 2: Create the manifest, wire &lt;head&gt;, set MIME type, confirm deploy coverage</name>
  <files>public/manifest.webmanifest, src/templates/layout.php, public/.htaccess</files>
  <action>
**1. Create `public/manifest.webmanifest`** (static JSON file, real file under `public/` — NOT a PHP route, per the front-controller constraint in `public/.htaccess`):

```json
{
  "name": "Team Manager",
  "short_name": "Team Manager",
  "description": "Team- und Spielereinsatz-Verwaltung für Sportteams",
  "start_url": "/",
  "scope": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#2f3640",
  "lang": "de",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" },
    { "src": "/icons/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ]
}
```

`start_url`/`scope` of `"/"` is correct: the app resolves `/` to the login page or the caller's role dashboard depending on session state, and covers `/admin`, `/coordinator`, `/member`, and the public `/ticker` areas alike. Do not add the apple-touch-icon to this `icons` array — iOS ignores manifest icons for the home screen entirely (handled separately via the `<link>` tag below); keeping it out avoids implying it does anything there.

**2. Edit `src/templates/layout.php`** — in `render_layout_head()`, inside the exact block shown in `<interfaces>` above (lines 35-50), insert after the existing `<meta name="apple-mobile-web-app-capable" content="yes">` line and before `<title>`:

```html
    <meta name="apple-mobile-web-app-title" content="Team Manager">
    <meta name="theme-color" content="#2f3640">
```

Then insert after the existing `<link rel="icon" href="/logo">` line (last line before `</head>`):

```html
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon-180.png">
```

Do not touch `<link rel="icon" href="/logo">` itself, the FOUC-prevention `<script>` block, or anything outside this `<head>` region. Do not touch `public/index.php`.

**3. Edit `public/.htaccess`** — add MIME type mapping for the manifest extension (Hetzner's Apache may not know `.webmanifest`). Insert this block after the existing "Block direct access to uploads directory" section and before the "# Front controller" comment. The file's existing `<FilesMatch>` block uses Apache 2.2 syntax (`Order deny,allow`), so wrap the new directive defensively in `<IfModule>` for portability:

```apache
# MIME type for the PWA manifest (some Apache configs lack this by default)
<IfModule mod_mime.c>
    AddType application/manifest+json .webmanifest
</IfModule>
```

**4. Confirm deploy.sh needs no change (verification only, do not edit deploy.sh).** `deploy.sh`'s second `lftp mirror --reverse public/ public_html/team-manager/` call already recurses into the entire local `public/` tree by default — this is the same mechanism that already ships `public/css/` today. The new `public/icons/` subdirectory and `public/manifest.webmanifest` will be picked up automatically on the next deploy with no script change. Run the grep check in `<verify>` below to confirm this line is still present and unmodified.
  </action>
  <verify>
    <automated>php -l src/templates/layout.php &amp;&amp; php -r 'json_decode(file_get_contents("public/manifest.webmanifest"), false, 512, JSON_THROW_ON_ERROR); echo "valid json\n";' &amp;&amp; grep -q 'rel="manifest"' src/templates/layout.php &amp;&amp; grep -q 'apple-touch-icon' src/templates/layout.php &amp;&amp; grep -q 'theme-color' src/templates/layout.php &amp;&amp; grep -q 'application/manifest+json' public/.htaccess &amp;&amp; grep -q 'public/ public_html/team-manager/' deploy.sh &amp;&amp; echo "ALL OK"</automated>
  </verify>
  <done>manifest.webmanifest is valid JSON with display=standalone and 3 icon entries; layout.php's &lt;head&gt; links the manifest and apple-touch-icon and sets theme-color; .htaccess declares the .webmanifest MIME type; deploy.sh's public/ mirror line is confirmed unchanged (no edit needed).</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>Static manifest + generated icon set (192/512/512-maskable/180) + `&lt;head&gt;` wiring in layout.php + `.htaccess` MIME type. No service worker, no install-prompt UI (none was built, per scope) — this is manual "Add to Home Screen" installability only.</what-built>
  <how-to-verify>
    1. Deploy or serve the app so it's reachable over HTTP(S) (e.g. `php -S localhost:8000 -t public` locally, or push to staging).
    2. Check the manifest is served correctly: `curl -sI http://localhost:8000/manifest.webmanifest` — confirm `200` and a `Content-Type` containing `manifest+json` (falls back to browser sniffing if the Apache MIME type isn't picked up locally by the PHP dev server — the important check is on the real Hetzner deploy).
    3. Android Chrome: open the site, tap the 3-dot menu → "App installieren" / "Zum Startbildschirm hinzufügen". Confirm the install dialog shows the Anthrazit "T" icon and the name "Team Manager". Confirm the installed app launches standalone (no address bar).
    4. iOS Safari: open the site, tap Share → "Zum Home-Bildschirm". Confirm a crisp icon appears (not a blurry page screenshot) and the name reads "Team Manager". Tap the new home screen icon and confirm it launches standalone (no Safari chrome).
    5. On Android, long-press the installed icon (or check the app drawer) to confirm the icon isn't clipped/off-center when the OS applies its circular/adaptive mask.
  </how-to-verify>
  <resume-signal>Type "approved" once confirmed installable and standalone on at least one real device, or describe what's wrong (e.g. blurry icon, browser chrome still visible, manifest 404).</resume-signal>
</task>

</tasks>

<verification>
- `php -l` passes on `bin/generate-pwa-icons.php` and `src/templates/layout.php`
- All 4 PNGs exist under `public/icons/` at their exact target dimensions (192, 512, 512, 180)
- `public/manifest.webmanifest` parses as valid JSON with `display: standalone`, `start_url: "/"`, `scope: "/"`, `lang: "de"`, and 3 icon entries pointing at real files under `/icons/`
- `src/templates/layout.php` `<head>` contains `rel="manifest"`, `rel="apple-touch-icon"`, and a `theme-color` meta tag
- `public/.htaccess` declares `AddType application/manifest+json .webmanifest`
- `deploy.sh` is unmodified and its existing `public/` mirror already covers the new `public/icons/` subdirectory
- No service worker file exists anywhere in the repo (explicitly out of scope)
- `public/index.php` is unmodified (working tree has unrelated in-progress changes there)
</verification>

<success_criteria>
- App is installable to the home screen via the browser's own menu on both Android Chrome and iOS Safari, launching standalone with no browser chrome
- Home screen icon is a real, crisp static image (not a screenshot fallback), and survives Android's adaptive-icon circular crop without clipping
- No service worker, no offline caching, no push/VAPID work shipped — strictly installability
- `public/index.php` untouched; no unrelated in-progress work swept into this task's changes
</success_criteria>

<output>
After completion, create `.planning/quick/260924-oht-ship-installability/260924-oht-SUMMARY.md`
</output>
