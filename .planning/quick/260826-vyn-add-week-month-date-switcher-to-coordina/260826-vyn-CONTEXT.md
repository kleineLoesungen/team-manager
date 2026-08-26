# Quick Task 260826-vyn: Add week/month date switcher to coordinator and member list overview pages - Context

**Gathered:** 2026-08-26
**Status:** Ready for planning

<domain>
## Task Boundary

Replace the existing Bootstrap `btn-group` Woche/Monat switcher and `nav-tabs` Kalender/Liste tabs on the coordinator and member list overview pages with mobile-friendly, iOS-style segmented controls matching the design from `landing/index.html` (`.ap-seg` pattern).

Files in scope:
- `src/templates/layout.php` — add shared `.seg-ctrl` CSS component
- `src/templates/coordinator/lists.php` — replace btn-group + nav-tabs + period nav
- `src/templates/member/lists.php` — same changes as coordinator

</domain>

<decisions>
## Implementation Decisions

### Kalender/Liste tab bar
- Replace Bootstrap `nav-tabs` with a full-width iOS segmented control (same `.seg-ctrl` component as Woche/Monat)
- User confirmed: "toggle button over the whole width is the way to use it with a smartphone"

### Woche/Monat switcher
- Replace `btn-group btn-group-sm` with full-width `.seg-ctrl` segmented control
- Must span the full container width (not centered/inline)

### Period navigation
- Replace large text buttons ("Vorherige Woche" / "Nächste Woche") with compact arrow nav
- Pattern: `‹  September 2026  ›` — icon-only left/right, label centered
- Still uses `<a>` links for navigation (no JS)

### CSS placement
- Shared component `.seg-ctrl` goes in `src/templates/layout.php` `<style>` block
- Must respect `--surface`, `--surface-2`, `--t1`, `--t3`, `--line` design tokens
- Must work in both light and dark themes (no hardcoded colors)

### Claude's Discretion
- Exact border-radius, padding, font-size of the segmented control (match landing mock proportions scaled for app context)
- Whether to add a CSS variable for the period nav label style or write it inline
- Compact period nav arrow size (should be min-touch 44px tap target)

</decisions>

<specifics>
## Specific References

Landing page source CSS for the component (to be translated to token-based CSS):
```css
.ap-seg { background:rgba(120,120,128,.12); margin:8px; border-radius:9px; display:flex; padding:2px; }
.ap-seg-b { flex:1; text-align:center; padding:5px; font-size:12px; color:#8E8E93; border-radius:7px; }
.ap-seg-b.on { background:#fff; color:#1C1C1E; font-weight:600; box-shadow:0 1px 4px rgba(0,0,0,.12); }
```

Token equivalents:
- `rgba(120,120,128,.12)` → `var(--surface-2)` (or a slightly muted variant)
- `#8E8E93` → `var(--t3)`
- `#fff` / `#1C1C1E` → `var(--surface)` / `var(--t1)`

Period nav mock pattern (from `landing/index.html` line 475-478):
```html
<span class="ap-mprev">‹ Vorheriger</span>
<span class="ap-mcur">September 2026</span>
<span class="ap-mnext">Nächster ›</span>
```
In the app, these must be `<a>` tags not `<span>` (navigation links).

Both coordinator (`$base_url = '/coordinator/lists'`) and member (`$base_url = '/member/lists'`) use identical `$cal_url()` helper patterns — the HTML structure change is the same for both templates.

</specifics>

<canonical_refs>
## Canonical References

- Landing page mock: `landing/index.html` — `.ap-seg`, `.ap-seg-b`, `.ap-mnav`, `.ap-mprev`, `.ap-mcur`, `.ap-mnext` CSS
- App design token system: `src/templates/layout.php` lines 44-88 (CSS variables)
- Coordinator lists template: `src/templates/coordinator/lists.php` lines 33-110
- Member lists template: `src/templates/member/lists.php` lines 31-77

</canonical_refs>
