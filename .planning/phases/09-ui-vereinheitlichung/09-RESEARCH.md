# Phase 9: UI-Vereinheitlichung — Research

**Researched:** 2026-08-29
**Domain:** PHP template system refactoring — layout unification, CSS token migration, component partials
**Confidence:** HIGH (all findings from direct codebase inspection; no external research needed)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01 Migration wave order:** Claude's discretion — suggested order: Infrastructure first
  (render_page, partials, app.css token migration), then tracer (coordinator lists), then
  coordinator role (remaining 27 templates), then member (11), then admin (20), then public (2).
- **D-02 ?success= flash:** Use `?success=1` GET param (consistent with `?error=`). Each template
  maps `$_GET['success'] ?? null` to a context-specific string. No URL-encoded messages, no session
  flash. JS cleans up with `history.replaceState`.
- **D-03 Old layout function strategy:** Keep `render_coach_page()`, `render_member_page()`,
  `render_admin_page()` as thin wrappers delegating to `render_page()` during Phase 9. Delete
  old functions in the final cleanup plan. This avoids big-bang refactor.
- **D-04 Dynamic --brand variable:** After CSS migration, keep a minimal single-line inline style
  in `render_layout_head()` ONLY for the dynamic `--brand` token:
  `<style>:root{--brand:<?= $safe_color ?>;}</style>`. Everything else moves to app.css.

### Claude's Discretion
- Exact plan count and task breakdown within each wave
- Exact PHP helper signatures for any intermediate utilities
- Order of templates within each role's migration plan
- How to handle edge cases (templates with complex inline JS that references CSS classes)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

## Summary

Phase 9 is a pure refactoring phase. Zero new features, zero DB changes. The goal is to unify
all 62 templates across 4 roles under a single `render_page(array $opts, callable $body)` layout
function and a single `public/css/app.css` stylesheet. Twelve standard component partials go into
`src/templates/components/`.

The large inline `<style>` block in `src/templates/layout.php` (lines 44–400, ~350 lines of CSS)
moves wholesale to `public/css/app.css`. The three role-specific layout functions become thin
wrappers so handler call sites do not change during the migration. Templates change in two ways:
(a) violations are fixed (inline styles removed, `-sm` form controls replaced, banned spacing
utilities replaced), and (b) repeated UI patterns are replaced with the 12 new partials.

**Primary recommendation:** Build infrastructure in Plan 1 (render_page + partials + CSS migration),
validate end-to-end with the tracer (Plan 2: coordinator lists), then migrate roles in order:
coordinator (Plan 3) → member (Plan 4) → admin (Plan 5) → public + cleanup (Plan 6).

---

## Standard Stack

No additional libraries. This phase uses only what already exists.

| Component | Location | Status |
|-----------|----------|--------|
| Bootstrap 5.3.0 CDN | `render_layout_head()` line 37–40 | Already present |
| Bootstrap Icons 1.11.0 CDN | `render_layout_head()` line 41–42 | Present, **missing SRI hash** |
| `public/css/app.css` | 221 lines, loaded after Bootstrap CDN | Extends to ~570+ lines post-migration |
| PHP native templates | `src/templates/` | All 62 templates migrated in-place |

**No npm, no build step, no new dependencies.**

**Bootstrap Icons SRI hash to add (per UI-SPEC):**
```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
      rel="stylesheet"
      integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
      crossorigin="anonymous">
```

---

## Architecture Patterns

### File Layout After Phase 9

```
src/templates/
  layout.php                    # render_layout_head(), render_layout_foot(), render_page() [NEW],
                                # render_login_page() — require_once of components/partials.php added here
  components/
    partials.php                # All 12 partial functions (render_flash, render_empty, etc.)
  coordinator/
    layout.php                  # render_coach_page() thin wrapper → render_page()
  member/
    layout.php                  # render_member_page() thin wrapper → render_page()
  admin/
    layout.php                  # render_admin_page() thin wrapper → render_page()
  public/
    ticker_overview.php         # Migrated to render_page(['role' => 'public', ...])
    ticker_detail.php           # Migrated to render_page(['role' => 'public', ...])
public/css/
  app.css                       # Absorbs all CSS currently in layout.php inline <style> block
```

### Pattern 1: `render_page()` — Unified Layout

Location: `src/templates/layout.php` (add alongside existing functions).

```php
// Source: 09-UI-SPEC.md Component Partial Contracts §1
function render_page(array $opts, callable $body): void {
    $title  = $opts['title']  ?? 'Team Manager';
    $role   = $opts['role']   ?? 'coordinator';
    $active = $opts['active'] ?? '';
    $back   = $opts['back']   ?? null;

    render_layout_head($title);

    // Tab key maps per role (unchanged from existing layouts)
    $tab_maps = [
        'coordinator' => [
            'members' => ['href' => '/coordinator/members', 'icon' => 'bi-person-vcard',   'label' => 'Mitglieder'],
            'lists'   => ['href' => '/coordinator/lists',   'icon' => 'bi-collection',      'label' => 'Listen'],
            'ticker'  => ['href' => '/coordinator/ticker',  'icon' => 'bi-megaphone',        'label' => 'Ticker'],
            'stats'   => ['href' => '/coordinator/stats',   'icon' => 'bi-graph-up',         'label' => 'Statistik'],
            'profile' => ['href' => '/coordinator/profile', 'icon' => 'bi-person-circle',    'label' => 'Profil'],
        ],
        'member' => [
            'lists'   => ['href' => '/member/lists',   'icon' => 'bi-collection',   'label' => 'Listen'],
            'ticker'  => ['href' => '/member/ticker',  'icon' => 'bi-megaphone',     'label' => 'Ticker'],
            'stats'   => ['href' => '/member/stats',   'icon' => 'bi-graph-up',      'label' => 'Statistik'],
            'profile' => ['href' => '/member/profile', 'icon' => 'bi-person-circle', 'label' => 'Profil'],
        ],
        'admin' => [
            'teams'        => ['href' => '/admin/teams',        'icon' => 'bi-people-fill',   'label' => 'Teams'],
            'coordinators' => ['href' => '/admin/coordinators', 'icon' => 'bi-person-badge',  'label' => 'Koordinatoren'],
            'players'      => ['href' => '/admin/members',      'icon' => 'bi-person-vcard',  'label' => 'Mitglieder'],
            'clubs'        => ['href' => '/admin/clubs',        'icon' => 'bi-building',       'label' => 'Klubs'],
            'settings'     => ['href' => '/admin/settings',     'icon' => 'bi-gear-fill',     'label' => 'Einstellungen'],
        ],
    ];

    $team_name = htmlspecialchars($_SESSION['team_name'] ?? 'Team Manager', ENT_QUOTES);
    ?>
    <div class="app">
        <header class="topbar">
            <img src="/logo" alt="" class="topbar-logo" onerror="this.style.display='none'" loading="eager">
            <span class="topbar-title"><?= $team_name ?></span>
            <?php if ($back): ?>
            <a href="<?= htmlspecialchars($back, ENT_QUOTES) ?>" class="topbar-context text-decoration-none">
                <i class="bi bi-chevron-left"></i> Zurück
            </a>
            <?php else: ?>
            <span class="topbar-context"><?= e($title) ?></span>
            <?php endif; ?>
            <button class="btn-theme" id="theme-toggle" aria-label="Dunkelmodus">
                <i class="bi bi-moon"></i>
            </button>
        </header>

        <main class="app-content">
            <?php $body(); ?>
        </main>

        <?php if ($role !== 'public' && isset($tab_maps[$role])): ?>
        <nav class="tabbar" aria-label="Hauptnavigation">
            <?php foreach ($tab_maps[$role] as $key => $tab): ?>
            <a href="<?= $tab['href'] ?>"
               class="tab-item <?= $active === $key ? 'is-on' : '' ?>"
               aria-current="<?= $active === $key ? 'page' : 'false' ?>">
                <i class="bi <?= $tab['icon'] ?>"></i>
                <span class="tab-label"><?= $tab['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php
    render_layout_foot();
    exit;
}
```

### Pattern 2: Thin Wrapper (D-03)

Old layout functions become one-liners during Phase 9:

```php
// src/templates/coordinator/layout.php — AFTER D-03 change
function render_coach_page(string $title, string $active, callable $body): void {
    render_page(['title' => $title, 'role' => 'coordinator', 'active' => $active], $body);
}
```

Same pattern for `render_member_page` and `render_admin_page`.

**Effect:** ALL 63 handler call sites continue working unchanged. No handler files touched.

### Pattern 3: CSS Token Migration

Move entire inline `<style>` block (layout.php lines 44–400) to `app.css`. Remove the block.
Replace with D-04 minimal inline style for dynamic `--brand` only:

```php
// In render_layout_head(), replace 350-line <style> block with:
?>
<link rel="stylesheet" href="/css/app.css">
<style>:root{--brand:<?= $safe_color ?>;}</style>
<?php
```

`app.css` receives all the moved CSS plus these new additions:
```css
/* Must add in Phase 9: */
.cell-number { min-width: 70px;  max-width: 100px; }   /* matrix table numeric input */
.cell-text   { min-width: 100px; }                       /* matrix table text input */
```

`.btn-primary` fix in the migrated CSS:
```css
/* Change from: background: #4B5563 */
/* Change to:   background: var(--brand) */
```

### Pattern 4: `?success=1` PRG Flash

Already partially implemented in `coordinator/lists.php` lines 363–374. Copy this exact
JS pattern to every template that can show a success flash:

```javascript
(function() {
    // Strip ?success=1 from URL without reload
    var url = (location.pathname + location.search).replace(/[?&]success=1/, '').replace(/\?$/, '');
    if (url !== location.pathname + location.search) {
        history.replaceState(null, '', url);
    }
})();
```

Template reads: `$_GET['success'] ?? null` and calls `render_flash('success', 'Gespeichert.')`.

### Pattern 5: Per-Template Violation Fix Checklist

Every template body migrated must pass all of these:
- [ ] No `style="..."` attributes (except `onerror="this.style.display='none'"` on `<img>`)
- [ ] No `py-5` → replace with `<div class="tm-empty">` via `render_empty()`
- [ ] No `mb-5` → replace with `mb-4`
- [ ] No `*-0`, `*-1` spacing utilities → remove or use CSS
- [ ] No `form-control-sm`, `form-select-sm` → remove `-sm` suffix (use plain `form-control`)
- [ ] No `input-group-sm` → remove `-sm` suffix
- [ ] No `shadow-sm` on cards → remove
- [ ] No `style="max-width:..."` on cards → remove (layout container constrains width)
- [ ] No `style="width:3em;height:1.75em;cursor:pointer;"` on switches → remove (app.css handles sizing)
- [ ] No `style="font-size:2rem;"` on icons → remove (`.tm-empty .bi` handles in app.css)
- [ ] No `fs-1` through `fs-6` Bootstrap classes → use h1–h3 or `.small`
- [ ] Empty states use `render_empty(icon, heading, body_text, action_html)`
- [ ] Visibility badges use `render_badge(type, label)`
- [ ] Grouped collections use `render_collection_group(label, items_body)`

### Anti-Patterns to Avoid

- **Importing components in individual template files:** `partials.php` is included once from
  `layout.php` and is available everywhere. Never add separate `require_once` calls for partials
  in individual templates.
- **Moving handler logic into templates:** Body callables remain pure output. No auth checks,
  no DB queries inside templates.
- **Mixing `render_page` and the old wrappers:** During migration, the old wrapper IS `render_page`
  (via D-03). After final cleanup plan, old wrappers are deleted. Never call both.
- **Deleting old layout functions before all templates are migrated:** Only delete in the final
  cleanup plan, after all roles are confirmed working.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Empty state display | Custom per-template `<div class="text-center py-5">` | `render_empty()` partial | 21 occurrences; inconsistent icon sizes, padding, copy |
| Status badges | Inline `<span class="badge bg-success">` strings | `render_badge(type, label)` | Badge colors tied to semantic tokens; inline strings drift |
| Danger zone card | Per-template copy of danger zone HTML | `render_danger_zone()` | 10+ occurrences; heading copy and styling diverge |
| Flash alerts | Per-template inline alert HTML | `render_flash(type, message)` | Inconsistent icon choice and role attribute |
| Matrix table | Custom table markup per template | `render_matrix_table()` | First-column sticky, `.tm-matrix` wrapper always required |
| Grouped list headers | Inline `<p class="tm-group-label">` | `render_collection_group()` | Consistent hairline + label style |
| Form field rows | `<div class="mb-3"><label>...<input>...</div>` | `render_form_field()` | Ensures no `-sm` suffix leaks in |
| Form section cards | `<div class="card shadow-sm">` | `render_form_section()` | Strips `shadow-sm`, enforces card token |
| Sticky action bar | Per-template `<div class="tm-actionbar">` | `render_action_bar()` | `body.has-actionbar` class injection always needed |

**Key insight:** The violation counts (21 empty states, 9 switch inline styles, 40+ inline styles)
show that ad-hoc implementations diverge rapidly. Partials are the enforcement mechanism.

---

## Common Pitfalls

### Pitfall 1: CSS Load Order — Remove Inline Block Before Adding to app.css

**What goes wrong:** Removing the `<style>` block from layout.php before the tokens land in
app.css causes every page to render with broken colours, sizing, and layout.

**Why it happens:** The inline block is the current source of all custom tokens. app.css currently
has 221 lines; it depends on Bootstrap being loaded first.

**How to avoid:** In Plan 1, write all new CSS to app.css FIRST, then remove the inline block
from layout.php as a single atomic commit. Never split across two tasks in the same plan.

**Warning signs:** Pages render with grey/unstyled elements; tabbar disappears; buttons lose colour.

### Pitfall 2: `exit` in Layout Functions

**What goes wrong:** Forgetting `exit` at the end of `render_page()` causes PHP to continue
executing after the layout returns, often double-rendering or executing side effects.

**Why it happens:** All three existing role layouts (`render_coach_page`, `render_member_page`,
`render_admin_page`) call `exit` after `render_layout_foot()`. `render_page()` must do the same.

**How to avoid:** The code example above includes `exit` after `render_layout_foot()`. The thin
wrappers do not need their own `exit` — they delegate to `render_page()` which exits.

### Pitfall 3: `credential_modal.php` is NOT a layout template

**What goes wrong:** Applying migration to `src/templates/admin/credential_modal.php` breaks the
`Cache-Control: no-store` response header that must be sent before any HTML.

**Why it happens:** credential_modal sends security headers at the PHP level before calling
template output. It is rendered by handlers via `include`, not via a layout function.

**How to avoid:** Do NOT migrate credential_modal.php to use `render_page()`. It is explicitly
outside the layout contract. It stays as a full-page include.

**Verification:** `grep -r "credential_modal"` — handler sets headers directly, then includes.

### Pitfall 4: `select_team.php` — Team Picker Before Session Is Set

**What goes wrong:** `coordinator/select_team.php` is rendered during login when `$_SESSION['team_name']`
is not yet set. `render_page` reads this session key for the topbar title.

**Why it happens:** The team picker appears after credential validation but before team selection
(Phase 8 multi-team login flow).

**How to avoid:** When migrating select_team.php, ensure `render_page` gracefully falls back when
`$_SESSION['team_name']` is missing (it already does: `$_SESSION['team_name'] ?? 'Team Manager'`).

### Pitfall 5: `render_page` Called from Public Templates Without Auth

**What goes wrong:** Public templates (`ticker_overview.php`, `ticker_detail.php`) call `render_layout_head()`
directly today and output their own full HTML. After migration, they must use `render_page(['role' => 'public', ...])`.

**Why it happens:** `render_page` reads `$_SESSION['team_name']`. Public pages have no session.

**How to avoid:** `render_page` already falls back to 'Team Manager' when session key is absent.
For `role: 'public'`, the topbar shows only the app title (no team name needed). Test explicitly
with no active session.

### Pitfall 6: `form-control-sm` in Table Action Columns Is Allowed

**What goes wrong:** Over-zealous removal strips `-sm` from table action inputs that are
legitimately small (per UI-SPEC exception).

**Why it happens:** The grep count (42 occurrences) includes both form-page violations (must remove)
and table/filter-bar uses (permitted).

**How to avoid:** Per UI-SPEC: `form-control-sm` / `form-select-sm` / `input-group-sm` are
banned on form pages. They are allowed in table action columns with an explicit comment.
Add `<!-- btn-sm: table action column, exception per UI-SPEC -->` comment when keeping.

### Pitfall 7: Missing `partials.php` require_once in render_layout_head

**What goes wrong:** Partial functions are undefined at runtime if the include is not in place
before any template body is called.

**Why it happens:** Templates call `render_empty()` etc. inside their body closures. The closures
execute inside `render_page()` which is inside `render_layout_head()`'s call stack. The require
must precede all template execution.

**How to avoid:** Add `require_once dirname(__FILE__) . '/components/partials.php';` at the top
of `src/templates/layout.php` (before any function definitions), not inside `render_layout_head()`.

---

## Code Examples

### 12 Partial Function Signatures (authoritative — from 09-UI-SPEC.md)

```php
// Source: .planning/phases/09-ui-vereinheitlichung/09-UI-SPEC.md

render_flash(string $type, string $message): void
// $type: 'success' | 'error'

render_page_header(string $title, ?string $back_url, ?string $action_html): void

render_empty(string $icon, string $heading, string $body_text, ?string $action_html): void
// $icon: Bootstrap Icons name without 'bi-' prefix, e.g. 'collection'

render_badge(string $type, string $label): void
// $type: 'ok' | 'warn' | 'bad' | 'dim' | 'info'

render_collection_group(string $label, callable $items_body): void

render_form_section(string $heading, callable $fields_body, ?string $footer_html): void

render_form_field(string $label, string $input_html, ?string $hint): void

render_matrix_table(array $columns, callable $rows_body, ?callable $footer_body): void

render_filter_pills(array $pills, string $base_url): void
// $pills: array of ['label' => string, 'url' => string, 'active' => bool]

render_danger_zone(string $action_label, string $description, string $form_html): void

render_action_bar(string $label, string $form_id): void
```

### Tracer Template Pattern (coordinator/lists.php after migration)

```php
// Handler calls (unchanged from today):
render_coach_page('Listen', 'lists', function() use ($items, ...) {
    // Template body — NOW using partials:
    if ($_GET['success'] ?? null) {
        render_flash('success', 'Gespeichert.');
    }
    if (empty($items)) {
        render_empty(
            'collection',
            'Noch keine Einträge',
            'Lege die erste Liste oder Datei an.'
        );
    } else {
        render_collection_group('Sichtbar', function() use ($items) { ... });
    }
});
```

### Visibility Badge Migration

```php
// BEFORE (inline, banned after Phase 9):
<span class="badge bg-success">Öffentlich</span>
<span class="badge bg-warning text-dark">Geschützt</span>
<span class="badge bg-secondary">Privat</span>

// AFTER (via partial):
<?php render_badge('ok', 'Öffentlich'); ?>
<?php render_badge('warn', 'Geschützt'); ?>
<?php render_badge('dim', 'Privat'); ?>
```

### Switch Input Migration

```php
// BEFORE (9 occurrences, banned after Phase 9):
<input ... style="width:3em;height:1.75em;cursor:pointer;">

// AFTER (app.css .form-switch .form-check-input handles sizing):
<div class="form-check form-switch">
  <input class="form-check-input" type="checkbox" role="switch" id="{id}" name="{name}" value="1" <?= $checked ? 'checked' : '' ?>>
  <label class="form-check-label" for="{id}">{label}</label>
</div>
```

---

## Migration Scope by Role

| Role | Template Files | Handler Call Sites | Notes |
|------|-----------|--------------------|-------|
| Coordinator | 28 | ~28 | Largest role; first after tracer; has calendar, ticker, matrix, stats |
| Admin | 20 | ~19 | credential_modal.php excluded from migration |
| Member | 11 | ~11 | Simplest templates; mostly list/stats views |
| Public | 2 | 2 (via `require`) | Self-contained full HTML today; use `role: 'public'` |
| **Total** | **61** | **~60** | credential_modal excluded; login.php stays on render_login_page |

**Handler call sites:** The 63 total handler call sites do NOT change (D-03 wrapper strategy).
The work is in:
1. Changing the implementation of 3 role layout files (thin wrappers)
2. Fixing violations inside 61 template body files

---

## Violation Counts (for task scoping)

Current counts from codebase inspection (2026-08-29):

| Violation | Count | Scope |
|-----------|-------|-------|
| `form-control-sm` / `form-select-sm` / `input-group-sm` in templates | 42 | src/templates/ |
| `py-5` in templates | 20 (incl. mb-5) | src/templates/ |
| Inline `style="..."` attributes (excluding `onerror`) | 81 | src/templates/ |
| `style="width:3em;height:1.75em;cursor:pointer;"` switches | ~9 | coordinator templates |
| `style="font-size:2rem;"` icons | ~2 | coordinator/lists.php, ticker |
| `shadow-sm` on cards | multiple | across roles |

Note: The UI-SPEC documents 248 `form-control-sm` occurrences — this may include full `src/`
scan including handlers. Template-only count is 42. Templates are the migration target.

---

## State of the Art

| Old Pattern | Phase 9 Pattern | File |
|-------------|-----------------|------|
| Per-role `render_coach_page()` | `render_page(['role' => 'coordinator', ...])` | layout.php |
| Inline `<style>` block (350 lines) | `public/css/app.css` (token source) | layout.php → app.css |
| `<div class="text-center py-5">` empty states | `render_empty()` partial | all templates |
| `style="width:3em;..."` switches | `.form-switch .form-check-input` (sized in app.css) | all templates |
| `badge bg-success/warning/secondary` inline | `render_badge('ok'/'warn'/'dim', label)` | all templates |
| `btn-primary` with `#4B5563` hardcoded | `btn-primary` with `var(--brand)` | app.css |
| Bootstrap Icons CDN without SRI | Bootstrap Icons CDN with SRI `sha384-EVSTQN3...` | layout.php |

---

## Open Questions

1. **`render_navbar()` function in layout.php (lines 451–463)**
   - What we know: It exists as a standalone function but appears unused in any template currently.
   - What's unclear: Whether any handler calls it directly.
   - Recommendation: `grep -rn "render_navbar()"` before Plan 1 — if unused, do not migrate it.

2. **Admin `dashboard.php` vs `teams.php`**
   - What we know: The admin layout shows `render_admin_page()` with active tab logic.
   - What's unclear: Whether `dashboard.php` or `teams.php` is the admin landing page.
   - Recommendation: Check `src/admin/` routing in `public/index.php` before admin migration plan.

3. **Public templates `render_page` topbar**
   - What we know: Public pages have no session; role is 'public'; tabbar is suppressed.
   - What's unclear: What the topbar should show (team name? App title? Nothing?).
   - Recommendation: For `role: 'public'`, topbar shows app title from settings (as is currently done in public templates via `$app_title`). Pass `$app_title` as title option.

---

## Sources

### Primary (HIGH confidence — direct codebase inspection)
- `src/templates/layout.php` — complete inline style block, render_layout_head/foot signatures
- `src/templates/coordinator/layout.php` — render_coach_page signature and tab key map
- `src/templates/member/layout.php` — render_member_page signature and tab key map
- `src/templates/admin/layout.php` — render_admin_page signature and tab key map
- `public/css/app.css` — current 221-line stylesheet, all existing token names and component classes
- `src/templates/coordinator/lists.php` — existing ?success=1 JS cleanup pattern (lines 363–374)
- `.planning/phases/09-ui-vereinheitlichung/09-UI-SPEC.md` — all 12 partial signatures with exact HTML
- `.planning/phases/09-ui-vereinheitlichung/09-CONTEXT.md` — locked decisions D-01 through D-04
- `.planning/UI-BASELINE.md` — spacing scale, banned utilities, archetype definitions
- `grep` output — violation counts in src/templates/, handler call sites in src/

---

## Metadata

**Confidence breakdown:**
- Migration scope (template counts): HIGH — verified by `find` on filesystem
- Partial signatures: HIGH — copied verbatim from 09-UI-SPEC.md
- render_page implementation: HIGH — derived from existing role layouts + UI-SPEC
- Violation counts: HIGH — verified by grep
- CSS token inventory: HIGH — inspected layout.php inline block and app.css directly

**Research date:** 2026-08-29
**Valid until:** Indefinite (internal codebase, no external dependency drift)
