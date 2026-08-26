---
phase: quick-260826-vyn
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/templates/layout.php
  - src/templates/coordinator/lists.php
  - src/templates/member/lists.php
autonomous: false
requirements: [QUICK-260826-vyn]
must_haves:
  truths:
    - "Kalender/Liste switcher spans full container width on mobile"
    - "Woche/Monat toggle is a full-width segmented control, not a small btn-group"
    - "Period navigation shows only arrow icons left/right with the label centered between them"
    - "All controls respect --surface, --surface-2, --t1, --t3 design tokens in both light and dark themes"
    - "Navigation still works via plain <a> links (no JS)"
  artifacts:
    - path: "src/templates/layout.php"
      provides: ".seg-ctrl and .period-nav CSS component definitions"
      contains: ".seg-ctrl"
    - path: "src/templates/coordinator/lists.php"
      provides: "Updated Kalender/Liste tab, Woche/Monat switcher, and period nav"
      contains: "seg-ctrl"
    - path: "src/templates/member/lists.php"
      provides: "Same updates as coordinator template"
      contains: "seg-ctrl"
  key_links:
    - from: "src/templates/layout.php"
      to: "src/templates/coordinator/lists.php"
      via: ".seg-ctrl CSS class"
      pattern: "seg-ctrl"
    - from: "src/templates/layout.php"
      to: "src/templates/member/lists.php"
      via: ".seg-ctrl CSS class"
      pattern: "seg-ctrl"
---

<objective>
Replace Bootstrap nav-tabs and btn-group controls on the coordinator and member list overview pages with iOS-style full-width segmented controls and compact arrow period navigation.

Purpose: Mobile-first UX — segmented controls span the full container width, are touch-friendly, and match the iOS design language already used in the landing page mock.
Output: Updated CSS in layout.php + updated HTML in both list overview templates.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

<!-- Key interfaces the executor needs -->
<interfaces>
<!-- From src/templates/layout.php lines 44-88: design tokens already in the <style> block -->
CSS variables available:
  --surface       #FFFFFF / dark: #1C1C1E
  --surface-2     #F2F2F7 / dark: #2C2C2E
  --t1            #000000 / dark: #FFFFFF
  --t3            rgba(60,60,67,.65) / dark: rgba(235,235,245,.38)
  --line          rgba(60,60,67,.15) / dark: rgba(255,255,255,.10)
  --r             12px (border-radius token)

<!-- From landing/index.html — source pattern to translate -->
Landing CSS (token-agnostic, hardcoded colors):
  .ap-seg   { background:rgba(120,120,128,.12); margin:8px; border-radius:9px; display:flex; padding:2px; }
  .ap-seg-b { flex:1; text-align:center; padding:5px; font-size:12px; color:#8E8E93; border-radius:7px; }
  .ap-seg-b.on { background:#fff; color:#1C1C1E; font-weight:600; box-shadow:0 1px 4px rgba(0,0,0,.12); }

Period nav landing mock (spans → <a> links in app):
  <span class="ap-mprev">‹ Vorheriger</span>
  <span class="ap-mcur">September 2026</span>
  <span class="ap-mnext">Nächster ›</span>

<!-- From coordinator/lists.php lines 29-110: blocks to replace -->
$base_url = '/coordinator/lists';
$cal_url  = fn(string $v, int $off) => $base_url.'?view='.urlencode($v).'&offset='.$off;

Block 1 — nav-tabs (lines 33–46): replace with .seg-ctrl
Block 2 — btn-group Woche/Monat (lines 86–95): replace with .seg-ctrl
Block 3 — period nav d-flex (lines 98–110): replace with .period-nav

<!-- From member/lists.php lines 26-77: identical structure, different $base_url -->
$base_url = '/member/lists';
Same three blocks at equivalent line positions.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add .seg-ctrl and .period-nav CSS to layout.php</name>
  <files>src/templates/layout.php</files>
  <action>
Inside the `<style>` block in `render_layout_head()`, add the following two CSS components after the existing `.card` or `.badge` section (before any existing `@media` rules). Do NOT alter any existing rules.

```css
/* ── Segmented Control (iOS-style full-width toggle) ──────────────────── */
.seg-ctrl {
    background: var(--surface-2);
    border-radius: 9px;
    display: flex;
    padding: 2px;
    margin-bottom: 12px;
}
.seg-ctrl a {
    flex: 1;
    text-align: center;
    padding: 6px 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--t3);
    border-radius: 7px;
    text-decoration: none;
    transition: color .12s;
    white-space: nowrap;
}
.seg-ctrl a.on {
    background: var(--surface);
    color: var(--t1);
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(0, 0, 0, .12);
}

/* ── Period navigation (‹ label ›) ─────────────────────────────────────── */
.period-nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}
.period-nav a {
    min-width: 44px;
    min-height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    line-height: 1;
    color: var(--t2);
    text-decoration: none;
    border-radius: 8px;
    flex-shrink: 0;
}
.period-nav a:hover { background: var(--surface-2); }
.period-nav .period-label {
    font-size: 15px;
    font-weight: 600;
    color: var(--t1);
    text-align: center;
    flex: 1;
}
```
  </action>
  <verify>
    <automated>php -l src/templates/layout.php &amp;&amp; grep -c 'seg-ctrl' src/templates/layout.php</automated>
  </verify>
  <done>layout.php passes PHP lint and contains .seg-ctrl and .period-nav CSS blocks using only design token variables (no hardcoded hex colors).</done>
</task>

<task type="auto">
  <name>Task 2: Replace nav-tabs, btn-group, and period nav in both list templates</name>
  <files>src/templates/coordinator/lists.php, src/templates/member/lists.php</files>
  <action>
Apply identical HTML replacements to both `src/templates/coordinator/lists.php` and `src/templates/member/lists.php`. The only difference between the two files is `$base_url` (already set correctly in each file's URL helper section — do not touch that).

**Replace Block 1 — Kalender/Liste tab (nav-tabs):**

Remove:
```html
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= ($view !== 'list') ? 'active' : '' ?>"
           href="<?= $cal_url('calendar', 0) ?>">
            <i class="bi bi-calendar3 me-1"></i>Kalender
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= ($view === 'list') ? 'active' : '' ?>"
           href="<?= $base_url . '?view=list' ?>">
            <i class="bi bi-list-ul me-1"></i>Liste
        </a>
    </li>
</ul>
```

Replace with:
```html
<!-- ── View switcher: Kalender / Liste ───────────────────────────────── -->
<div class="seg-ctrl">
    <a href="<?= $cal_url('calendar', 0) ?>" class="<?= ($view !== 'list') ? 'on' : '' ?>">
        <i class="bi bi-calendar3 me-1"></i>Kalender
    </a>
    <a href="<?= $base_url . '?view=list' ?>" class="<?= ($view === 'list') ? 'on' : '' ?>">
        <i class="bi bi-list-ul me-1"></i>Liste
    </a>
</div>
```

**Replace Block 2 — Woche/Monat toggle (btn-group):**

Remove:
```html
<!-- Week/Month toggle (D-02) -->
<div class="btn-group btn-group-sm mb-3">
    <a href="<?= $cal_url('week', 0) ?>"
       class="btn <?= $periodView === 'week' ? 'btn-primary' : 'btn-outline-secondary' ?>">
        Woche
    </a>
    <a href="<?= $cal_url('month', 0) ?>"
       class="btn <?= $periodView === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>">
        Monat
    </a>
</div>
```

Replace with:
```html
<!-- Week/Month toggle ───────────────────────────────────────────────── -->
<div class="seg-ctrl">
    <a href="<?= $cal_url('week', 0) ?>" class="<?= $periodView === 'week' ? 'on' : '' ?>">Woche</a>
    <a href="<?= $cal_url('month', 0) ?>" class="<?= $periodView === 'month' ? 'on' : '' ?>">Monat</a>
</div>
```

**Replace Block 3 — Period navigation (d-flex with text buttons):**

Remove:
```html
<!-- Period navigation: ◀ label ▶ (D-02) -->
<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <a href="<?= $cal_url($periodView, $offset - 1) ?>"
       class="btn btn-outline-secondary btn-sm min-touch">
        <i class="bi bi-chevron-left me-1"></i><?= $periodView === 'week' ? 'Vorherige Woche' : 'Vorheriger Monat' ?>
    </a>
    <small class="text-muted text-center flex-shrink-0">
        <?= $periodView === 'week' ? 'Woche: ' : '' ?><?= e($boundaries['label']) ?>
    </small>
    <a href="<?= $cal_url($periodView, $offset + 1) ?>"
       class="btn btn-outline-secondary btn-sm min-touch">
        <?= $periodView === 'week' ? 'Nächste Woche' : 'Nächster Monat' ?><i class="bi bi-chevron-right ms-1"></i>
    </a>
</div>
```

Replace with:
```html
<!-- Period navigation: ‹ label › ────────────────────────────────────── -->
<div class="period-nav">
    <a href="<?= $cal_url($periodView, $offset - 1) ?>" title="<?= $periodView === 'week' ? 'Vorherige Woche' : 'Vorheriger Monat' ?>">‹</a>
    <span class="period-label"><?= e($boundaries['label']) ?></span>
    <a href="<?= $cal_url($periodView, $offset + 1) ?>" title="<?= $periodView === 'week' ? 'Nächste Woche' : 'Nächster Monat' ?>">›</a>
</div>
```

In the coordinator template there is also an "Add button row" div between the view-switcher and the Woche/Monat toggle — do NOT touch it. The block order in coordinator/lists.php is:
1. View switcher (Block 1) — replace
2. Add button row (d-flex justify-content-end) — keep as-is
3. Woche/Monat toggle (Block 2) — replace
4. Period nav (Block 3) — replace

In the member template the add button row does not exist, so Blocks 1, 2, 3 are consecutive.
  </action>
  <verify>
    <automated>php -l src/templates/coordinator/lists.php &amp;&amp; php -l src/templates/member/lists.php &amp;&amp; grep -c 'seg-ctrl' src/templates/coordinator/lists.php &amp;&amp; grep -c 'seg-ctrl' src/templates/member/lists.php &amp;&amp; ! grep -q 'btn-group\|nav-tabs\|Vorherige Woche\|Nächste Woche\|Vorheriger Monat\|Nächster Monat' src/templates/coordinator/lists.php &amp;&amp; ! grep -q 'btn-group\|nav-tabs\|Vorherige Woche\|Nächste Woche\|Vorheriger Monat\|Nächster Monat' src/templates/member/lists.php &amp;&amp; echo "ALL OK"</automated>
  </verify>
  <done>Both template files pass PHP lint, contain `seg-ctrl` class references, and contain no remaining `btn-group`, `nav-tabs`, or long period navigation text strings.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>iOS-style segmented controls for Kalender/Liste and Woche/Monat, plus compact arrow-only period navigation, on both coordinator and member list overview pages.</what-built>
  <how-to-verify>
    1. Open the coordinator list overview (e.g. http://localhost/coordinator/lists)
    2. Confirm the Kalender/Liste switcher spans full width as a pill-shaped segmented control
    3. Switch to calendar view — confirm Woche/Monat is also a full-width segmented control (not a small btn-group)
    4. Confirm the period nav shows only ‹ and › arrows flanking the centered label (no "Vorherige Woche" text)
    5. Tap arrows to verify navigation still works
    6. Open member list overview (e.g. http://localhost/member/lists) — confirm same controls appear
    7. Toggle dark mode — confirm all controls still look correct (no hardcoded white/black colors)
  </how-to-verify>
  <resume-signal>Type "approved" or describe visual issues to fix</resume-signal>
</task>

</tasks>

<verification>
- PHP lint passes on all three modified files
- No `btn-group`, `nav-tabs`, or long period navigation text remains in either list template
- `.seg-ctrl` and `.period-nav` CSS rules are present in layout.php and use only design token variables
- Navigation links (`$cal_url(...)`) are preserved unchanged — only the wrapping HTML changes
</verification>

<success_criteria>
- Full-width segmented controls replace Bootstrap btn-group and nav-tabs on both coordinator and member list overview pages
- Period navigation is compact (arrows only, 44px touch targets) with the label centered
- Controls work correctly in both light and dark themes
- All navigation remains link-based (no JS added)
</success_criteria>

<output>
After completion, create `.planning/quick/260826-vyn-add-week-month-date-switcher-to-coordina/260826-vyn-SUMMARY.md`
</output>
