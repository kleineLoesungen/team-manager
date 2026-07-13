---
phase: quick-260713-kxn
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/templates/layout.php
  - src/templates/coordinator/layout.php
  - src/templates/member/layout.php
  - src/templates/admin/layout.php
  - landing/index.html
autonomous: true
requirements: []
must_haves:
  truths:
    - "App cards and main content fade in smoothly on page load"
    - "Mobile tab bar shows icons above labels with larger touch targets"
    - "Sidebar nav links animate smoothly on hover/active"
    - "Landing page Einblicke section shows a 4th screen mockup for the email notification flow"
  artifacts:
    - path: "src/templates/layout.php"
      provides: "CSS animations, transitions, improved mobile base styles"
    - path: "src/templates/coordinator/layout.php"
      provides: "Mobile tabs with icons + bigger touch targets"
    - path: "src/templates/member/layout.php"
      provides: "Mobile tabs with icons + bigger touch targets"
    - path: "src/templates/admin/layout.php"
      provides: "Mobile tabs with icons + bigger touch targets"
    - path: "landing/index.html"
      provides: "4th mock screen for email notification flow in Einblicke section"
  key_links:
    - from: "src/templates/layout.php"
      to: "All role layouts"
      via: "require_once layout.php"
      pattern: "require_once.*layout\\.php"
---

<objective>
Upgrade the webapp's visual design with CSS animations and improved mobile-first UX, and update the landing page to include a notification screen mockup.

Purpose: The app has functional styling but no entrance animations, small mobile touch targets, and no animated transitions. Landing page is also missing a screen for Phase 5 email feature.

Output:
- Webapp: card entrance animations, smooth hover/active transitions, mobile tab icons with larger touch targets, navbar scroll shadow
- Landing: 4th mock screen in "Einblicke" section showing the email notification compose + preview flow
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@src/templates/layout.php
@src/templates/coordinator/layout.php
@src/templates/member/layout.php
@src/templates/admin/layout.php
@landing/index.html
</context>

<tasks>

<task type="auto">
  <name>Task 1: Webapp CSS animations and mobile UX polish</name>
  <files>
    src/templates/layout.php,
    src/templates/coordinator/layout.php,
    src/templates/member/layout.php,
    src/templates/admin/layout.php
  </files>
  <action>
**src/templates/layout.php** — extend the existing `<style>` block with:

1. **Entrance animation** — `@keyframes fadeInUp` (from opacity:0 + translateY(12px) to opacity:1 + translateY(0), 0.28s ease). Apply to `.card` with `animation: fadeInUp 0.28s ease both`. Stagger sibling cards with `nth-child` delays (0.05s, 0.10s, 0.15s). Wrap in `@media (prefers-reduced-motion: no-preference)` so it respects accessibility.

2. **Smooth transitions on interactive elements:**
   - Sidebar `.nav-link`: `transition: background-color 0.15s ease, color 0.15s ease, padding-left 0.15s ease;`
   - Sidebar inactive `.nav-link:hover`: `background-color: rgba(0,0,0,.05); padding-left: calc(1rem + 3px);` (subtle indent on hover)
   - `.card`: add `transition: box-shadow 0.2s ease, transform 0.2s ease;` to the existing card rule. Add hover: `transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.10);`
   - `.btn`: add `transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;`
   - `.btn:active`: `transform: translateY(1px);` (physical press feel)
   - `.table tbody tr`: `transition: background-color 0.12s ease;`
   - `.table tbody tr:hover td`: `background-color: rgba(0,0,0,.025);`
   - `.form-control, .form-select`: `transition: border-color 0.15s ease, box-shadow 0.15s ease;`

3. **Mobile tab bar base styles** — add new class `.mobile-tab-bar` with `background: #fff; border-bottom: 1px solid #e9ecef; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;` and `.mobile-tab-bar::-webkit-scrollbar { display: none; }`. Add `.mobile-tab-link` with `flex-shrink: 0; display: flex; flex-direction: column; align-items: center; gap: 3px; padding: 10px 14px; font-size: 0.68rem; font-weight: 500; color: #6c757d; text-decoration: none; border-bottom: 2px solid transparent; transition: color 0.15s ease, border-color 0.15s ease; white-space: nowrap;` and `.mobile-tab-link.active { color: var(--brand); border-bottom-color: var(--brand); font-weight: 700; }` and `.mobile-tab-link .tab-icon { font-size: 1.15rem; }`.

4. **Navbar scroll shadow** — append a small `<script>` block just before `</style>` close or inline in render_layout_foot. Actually, add the JS in `render_layout_foot()` just before the closing `</script>` of Bootstrap bundle. Add `<script>` after Bootstrap bundle:
```html
<script>
(function(){
  var nav = document.querySelector('nav.navbar');
  if (!nav) return;
  window.addEventListener('scroll', function(){
    nav.style.boxShadow = window.scrollY > 4
      ? '0 2px 12px rgba(0,0,0,0.15)'
      : 'none';
  }, {passive: true});
})();
</script>
```

**src/templates/coordinator/layout.php** — update mobile top tabs div:

Replace current plain `<div class="d-md-none w-100 border-bottom bg-light">` block with:
```html
<div class="d-md-none w-100 mobile-tab-bar">
    <div class="d-flex">
        <a class="mobile-tab-link <?= $active === 'members' ? 'active' : '' ?>" href="/coordinator/members">
            <i class="bi bi-people-fill tab-icon"></i><span>Mitglieder</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'lists' ? 'active' : '' ?>" href="/coordinator/lists">
            <i class="bi bi-collection tab-icon"></i><span>Inhalte</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'columns' ? 'active' : '' ?>" href="/coordinator/columns">
            <i class="bi bi-layout-three-columns tab-icon"></i><span>Spalten</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'stats' ? 'active' : '' ?>" href="/coordinator/stats">
            <i class="bi bi-graph-up tab-icon"></i><span>Statistik</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'logo' ? 'active' : '' ?>" href="/coordinator/logo">
            <i class="bi bi-image tab-icon"></i><span>Logo</span>
        </a>
    </div>
</div>
```

**src/templates/member/layout.php** — same pattern, replace mobile tabs for Inhalte / Statistik / Profil:
```html
<div class="d-md-none w-100 mobile-tab-bar">
    <div class="d-flex">
        <a class="mobile-tab-link <?= $active === 'lists' ? 'active' : '' ?>" href="/member/lists">
            <i class="bi bi-collection tab-icon"></i><span>Inhalte</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'stats' ? 'active' : '' ?>" href="/member/stats">
            <i class="bi bi-graph-up tab-icon"></i><span>Statistik</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'profile' ? 'active' : '' ?>" href="/member/profile">
            <i class="bi bi-person-circle tab-icon"></i><span>Profil</span>
        </a>
    </div>
</div>
```

**src/templates/admin/layout.php** — same pattern for Teams / Koordinatoren / Einstellungen / Benachrichtigung:
```html
<div class="d-md-none w-100 mobile-tab-bar">
    <div class="d-flex">
        <a class="mobile-tab-link <?= $active === 'teams' ? 'active' : '' ?>" href="/admin/teams">
            <i class="bi bi-people-fill tab-icon"></i><span>Teams</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'coordinators' ? 'active' : '' ?>" href="/admin/coordinators">
            <i class="bi bi-person-badge tab-icon"></i><span>Koordinatoren</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings">
            <i class="bi bi-gear-fill tab-icon"></i><span>Einstellungen</span>
        </a>
        <a class="mobile-tab-link <?= $active === 'notify' ? 'active' : '' ?>" href="/admin/notify">
            <i class="bi bi-envelope tab-icon"></i><span>Benachrichtigung</span>
        </a>
    </div>
</div>
```

Note: Remove `bg-light` from the outer div in all three files — the new `.mobile-tab-bar` CSS uses `background: #fff` which looks cleaner. Keep the `d-md-none w-100` structure or combine into `.d-md-none`. The inner `d-flex` div stays to push tabs side by side.
  </action>
  <verify>
    <automated>php -l src/templates/layout.php &amp;&amp; php -l src/templates/coordinator/layout.php &amp;&amp; php -l src/templates/member/layout.php &amp;&amp; php -l src/templates/admin/layout.php</automated>
  </verify>
  <done>
    - PHP syntax valid on all 4 files
    - `@keyframes fadeInUp` and `.card` animation rules present in layout.php
    - `.mobile-tab-bar` and `.mobile-tab-link` CSS rules present in layout.php
    - All 3 role layout mobile tab blocks use new `.mobile-tab-link` class with icon + label structure
    - Navbar scroll shadow JS present in render_layout_foot()
  </done>
</task>

<task type="auto">
  <name>Task 2: Landing page — add email notification screen mockup</name>
  <files>landing/index.html</files>
  <action>
In `landing/index.html`, locate the "Einblicke" section (`.tm-screens`, id="einblicke"). Currently contains 3 mock screens in `<div class="row g-4">`. Add a 4th screen after Screen 3 (the Rangliste full-width screen).

The new screen should show the email notification compose + pre-send preview interface. Insert this block inside the `<div class="row g-4">` after the third screen's closing `</div>` (col-12 for Rangliste):

```html
<!-- Screen 4: Email notification compose + preview -->
<div class="col-12 col-lg-6">
  <div class="mock">
    <div class="mock-bar">
      <div class="mock-dot" style="background:#ff5f57"></div>
      <div class="mock-dot" style="background:#febc2e"></div>
      <div class="mock-dot" style="background:#28c840"></div>
    </div>
    <div class="mock-body">
      <div style="font-weight:800;font-size:14px;margin-bottom:2px;">Benachrichtigung senden</div>
      <div style="color:#6c757d;font-size:10px;margin-bottom:12px;">Training 18.05.26 · 8 Mitglieder</div>

      <!-- Message compose area -->
      <div style="margin-bottom:10px;">
        <div style="font-size:10px;font-weight:700;color:#495057;margin-bottom:4px;">Nachricht</div>
        <div style="background:#fff;border:1px solid #ced4da;border-radius:6px;padding:7px 9px;color:#212529;font-size:10px;line-height:1.5;min-height:36px;">
          Hallo Team, das Training am 18.05. findet statt. Bitte bestätigt eure Teilnahme.
        </div>
      </div>

      <!-- Link preview -->
      <div style="margin-bottom:10px;">
        <div style="font-size:10px;font-weight:700;color:#495057;margin-bottom:4px;">Link im Inhalt</div>
        <div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:5px;padding:5px 8px;color:#6366f1;font-size:9.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          🔗 team-manager.example.com/member/lists/42
        </div>
      </div>

      <!-- Recipient status -->
      <div style="margin-bottom:10px;">
        <div style="font-size:10px;font-weight:700;color:#495057;margin-bottom:5px;">Empfänger</div>
        <div style="display:grid;gap:4px;">
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:10px;">
            <span>Tobias B.</span>
            <span style="background:#d1fae5;color:#065f46;font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;">✓ E-Mail</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:10px;">
            <span>Felix W.</span>
            <span style="background:#d1fae5;color:#065f46;font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;">✓ E-Mail</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:10px;color:#94a3b8;">
            <span>Stefan H.</span>
            <span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;">⚠ Fehlt</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:10px;">
            <span>Lukas M.</span>
            <span style="background:#d1fae5;color:#065f46;font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;">✓ E-Mail</span>
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:10px;color:#94a3b8;">
            <span>Simon L.</span>
            <span style="background:#fef3c7;color:#92400e;font-size:9px;font-weight:700;padding:1px 6px;border-radius:4px;">⚠ Fehlt</span>
          </div>
        </div>
      </div>

      <!-- Send button -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;">
        <span style="color:#6c757d;font-size:9.5px;">5 Mitglieder erhalten E-Mail, 2 fehlen</span>
        <span class="mk-btn" style="background:#6366f1;color:#fff;">Senden</span>
      </div>
    </div>
  </div>
  <p class="tm-screen-caption">Benachrichtigung — Empfänger-Vorschau mit E-Mail-Status</p>
</div>
```

Also update the Rangliste screen (Screen 3) to span only `col-12 col-lg-6` instead of `col-12` (full-width), so it sits next to the new notification screen. Change `<div class="col-12">` to `<div class="col-12 col-lg-6">` for the Rangliste screen. This makes the bottom two screens appear side by side on desktop.
  </action>
  <verify>
    <automated>grep -c "Benachrichtigung senden" /Users/sebastianwiller/Documents/github/team-manager/landing/index.html</automated>
  </verify>
  <done>
    - New 4th screen block present in landing/index.html with "Benachrichtigung senden" heading
    - Recipient list shows ✓/⚠ status badges for members with/without email
    - Rangliste screen changed to col-12 col-lg-6 so it pairs with notification screen on desktop
    - Page renders without broken HTML (well-formed tags)
  </done>
</task>

</tasks>

<verification>
After both tasks complete:
1. `php -l src/templates/layout.php` — no syntax errors
2. `php -l src/templates/coordinator/layout.php src/templates/member/layout.php src/templates/admin/layout.php` — no syntax errors
3. Open the webapp in browser on a mobile-width viewport (375px) — verify tab bar shows icon + label, bigger touch targets, transitions visible
4. Open landing/index.html in browser — verify 4 screens appear in "Einblicke" section, notification screen shows recipient status badges
</verification>

<success_criteria>
- All 4 PHP layout files pass `php -l` lint check
- `.card` elements fade in on page load in the webapp (verified by inspector: `animation: fadeInUp` rule present)
- Mobile tab bars in all 3 role layouts: icon + label structure, ~54px tall touch targets
- Hover effects visible on sidebar links, cards, buttons via CSS transition rules
- Navbar gains a drop-shadow when scrolling down (JS scroll handler active)
- landing/index.html "Einblicke" section has 4 screens including notification compose mockup
- Notification mockup shows message textarea, link preview, per-member email status, send button
</success_criteria>

<output>
After completion, create `.planning/quick/260713-kxn-optimize-mobile-first-design-and-take-mo/260713-kxn-SUMMARY.md`
</output>
