---
phase: 6
slug: calendar-lists-with-date-location-ics-export
status: draft
shadcn_initialized: false
preset: none
created: 2026-07-14
---

# Phase 6 — UI Design Contract

> Calendar view with timeline, location field, and ICS export. Visual and interaction contract for frontend implementation.

---

## Design System

| Property | Value |
|----------|-------|
| Tool | Bootstrap 5.3 via CDN |
| Preset | not applicable |
| Component library | Bootstrap (no shadcn) |
| Icon library | Bootstrap Icons 1.11.0 (CDN) |
| Font | System default (inherited from Bootstrap 5.3) |

**Notes:**
- No build step; all CSS/icons delivered via CDN with SRI integrity hashes
- Mobile-first responsive: `row-cols-1 row-cols-md-2 row-cols-lg-3` pattern
- German UI throughout (Phase 5 convention: Du-speech, full German localization)
- Brand color configurable by admin via settings table (`app_color`, CSS var `--brand`, default #2563eb)

---

## Spacing Scale

Declared values (all multiples of 4px, following Bootstrap default scale):

| Token | Value | Usage | Bootstrap Class |
|-------|-------|-------|-----------------|
| xs | 4px | Icon gaps, inline elements | `gap-1`, `me-1`, `mb-1` |
| sm | 8px | Compact spacing | `gap-2`, `mb-2`, `p-2` |
| md | 16px | Default element spacing, form sections | `gap-3`, `mb-4`, `p-3` |
| lg | 24px | Section padding | `p-4`, `mb-5` |
| xl | 32px | Layout gaps | `g-4`, `gap-5` |
| 2xl | 48px | Major section breaks | `mb-5` |
| 3xl | 64px | Page-level spacing | Not commonly used |

**Exceptions:**
- `min-touch: 44px` — Minimum height for touch targets (buttons, nav items) for mobile accessibility

**Pre-populated source:** Phases 1–5 established Bootstrap default scale; no changes for Phase 6.

---

## Typography

| Role | Size | Weight | Line Height | Bootstrap Class |
|------|------|--------|-------------|-----------------|
| Body | 16px (1rem) | 400 (regular) | 1.5 | body text, default |
| Form Label | 14–16px | 600 (semibold) | 1.5 | `.form-label.fw-semibold` |
| Heading 1 | 2.5rem | 600 | 1.2 | `<h1>` / `.h1` |
| Heading 6 | 1rem | 600 | 1.2 | `<h6>` — day headers in calendar |
| Small/Muted | 12–14px | 400 | 1.5 | `.small`, `.text-muted` |
| Code/Monospace | 14px | 400 | 1.5 | Consolas, monospace (credential display) |

**Notes:**
- Calendar day headers use `<h6>` with `.text-muted` and `.border-bottom pb-2`
- Form labels use `.form-label.fw-semibold` for consistency with existing forms (list_form.php pattern)
- "Optional" indicators: `<span class="text-muted fw-normal">(optional)</span>`

**Pre-populated source:** Phases 1–5 established Bootstrap typography defaults; no changes for Phase 6.

---

## Color

| Role | Value | Usage | Bootstrap Class |
|------|-------|-------|-----------------|
| Dominant (60%) | #f8f9fa | Page background, body | `bg-light` (body background) |
| Secondary (30%) | #ffffff | Card backgrounds, default surfaces | `.card`, card default bg |
| Accent (10%) | var(--brand) = default #2563eb | Primary buttons, navbar, active states, primary badges | `.btn-primary`, `.bg-primary`, `.border-primary` |
| Semantic: Public | #198754 (Bootstrap success) | Visibility badge for public lists | `.badge.bg-success` |
| Semantic: Protected | #ffc107 (Bootstrap warning) | Visibility badge for protected lists | `.badge.bg-warning.text-dark` |
| Semantic: Private | #6c757d (Bootstrap secondary) | Visibility badge for private lists | `.badge.bg-secondary` |
| Semantic: Destructive | #dc3545 (Bootstrap danger) | Delete/danger actions, error text | `.alert-danger`, `.btn-danger`, `.text-danger` |

**Accent reserved for (10% constraint):**
- Primary button backgrounds (`.btn-primary`)
- Navbar background (`nav.navbar`)
- Active nav/tab links (`.nav-link.active`)
- Primary badges/highlights
- Form focus states
- Brand accent (primary badge for list visibility)

**Notes:**
- Visibility badges appear on all list/calendar entries: success (public), warning (protected), secondary (private)
- Location icon: `<i class="bi bi-geo-alt"></i>` in `.text-muted` or inherit text color
- Calendar icon: `<i class="bi bi-calendar3"></i>` for date display
- Brand color is CSS custom property `var(--brand)`, configurable per-team via admin settings

**Pre-populated source:** Layout.php establishes CSS custom properties; Phases 1–5 established visibility badge colors. Phase 6 adds no new color values.

---

## Copywriting Contract

### Navigation & Tabs

| Element | Copy | Context |
|---------|------|---------|
| Calendar tab (active by default) | "Kalender" | Tab-switcher on `/coordinator/lists` and `/member/lists` |
| List tab | "Liste" | Tab-switcher alternative view |
| Tab icon (calendar) | Bootstrap icon `bi-calendar3` | Precedes label |
| Tab icon (list) | Bootstrap icon `bi-list-ul` | Precedes label |

### Calendar View Headers & Navigation

| Element | Format | Context |
|---------|--------|---------|
| Week label | "14.–20. Juli 2026" | Displayed above dated entries (Monday–Sunday ISO week) |
| Month label | "Juli 2026" | Displayed above dated entries (month view) |
| Day header | "Montag, 14.07.2026" | Grouped by day within week/month view (format: `l, d.m.Y`) |
| Undated section heading | "Ohne Datum" | Section for entries with `date IS NULL` |
| Week/Month toggle | "Woche" | "Monat" | Two-button toggle near navigation arrows |
| Previous period button | "◀ Vorherige Woche" (week mode) or "◀ Vorheriger Monat" (month mode) | Navigation control |
| Next period button | "Nächste Woche ▶" (week mode) or "Nächster Monat ▶" (month mode) | Navigation control |

### Calendar Entry Cards

| Element | Format | Context |
|---------|--------|---------|
| Entry name | Plain text (clickable link) | List or file name, bold |
| Entry location (if present) | "📍 Sportplatz Mitte" (icon + text) | Optional location field, `.small.text-muted` |
| Visibility badge | "Öffentlich" / "Geschützt" / "Privat" | Colored badge (success/warning/secondary) |
| Empty dated section | "Noch keine Einträge mit Datum" | Centered, muted text if `$datedItems` is empty |
| Empty undated section | (section omitted) | If no undated entries exist, section not rendered |

### ICS Export Link

| Element | Copy | Context | Location |
|---------|------|---------|----------|
| ICS info box | "In Kalender-App abonnieren" | URL: `/ics/{team_id}.ics` | Above calendar view, Bootstrap `alert alert-info` (small, not prominent) |
| ICS info box body | "Kopiere den Link um die Termine in deiner Kalender-App zu abonnieren" | Explains usage to coordinator/member |

### Form: Location Field

| Element | Label | Helper Text |
|---------|-------|-------------|
| Location input | "Ort" + `<span class="text-muted fw-normal">(optional)</span>` | "z. B. Sportplatz Mitte, Turnhalle" |
| Input type | `<input type="text" maxlength="255">` | Single-line text, same `.form-control` styling as other form inputs |
| Placement | After "Datum" field in list create/edit forms | Same pattern as existing list_form.php (`mb-4` spacing) |

### Empty States & Errors

| Scenario | Copy | Style |
|----------|------|-------|
| No entries at all (list view) | "Noch keine Einträge\nLege die erste Liste oder Datei an." | Centered, `.h5.text-muted` (existing pattern from phase 5) |
| No dated entries in calendar view | "Noch keine Einträge mit Datum" | Centered, muted, body size |
| No location for entry | (field simply omitted from card) | Location row only renders if `!empty($item['location'])` |

### Destructive Actions

| Action | Existing | Changed? |
|--------|----------|----------|
| Delete list | Two-step confirm (Gefahrenzone card → confirmation page, no JS) | No change — Phase 3 pattern continues |

**Pre-populated source:** Phases 1–5 established German UI conventions (Du-speech, visibility labels, form patterns). CONTEXT.md D-01 to D-17 specified calendar copy (tab labels, period labels, ICS copy). RESEARCH.md Pattern 4 & 5 showed expected HTML with implicit copy patterns.

---

## Component Inventory

### New for Phase 6

| Component | HTML Structure | Usage | Notes |
|-----------|----------------|-------|-------|
| Tab-Switcher | `.nav-tabs` with `.nav-item` + `.nav-link` (active when `$currentView === 'calendar'`) | Top of `/coordinator/lists` and `/member/lists` | GET params: `?view=calendar\|list&offset=0` |
| Calendar Timeline | `.timeline` div with grouped `.mb-3` sections (one per day) | Main calendar view | Sorted by date; separated from undated entries |
| Day Header | `<h6 class="text-muted border-bottom pb-2">` | Groups entries by calendar day | Format: `l, d.m.Y` (e.g., "Montag, 14.07.2026") |
| Calendar Entry Card | `.card.card-sm.mb-2.shadow-sm` with `.card-body` | Individual entry in calendar | Same pattern as existing list cards |
| Calendar Entry Name | `<a href="...">` inside card-body, bold | Link to detail page | Clickable entry title |
| Calendar Entry Location | `.small.text-muted` with `bi-geo-alt` icon | Optional location display | Only rendered if location exists |
| Calendar Entry Badge | `.badge.bg-{success\|warning\|secondary}` | Visibility indicator | Positioned top-right of card |
| Period Navigation | `<button>` with `◀` and `▶` symbols | Week/month navigation | GET params modify `&offset=±1` |
| Period Toggle | Two-button group `.btn-group` ("Woche" / "Monat") | Switch between week and month view | GET param: `&view=week\|month` |
| Period Label | `.small.text-muted` (above entries) | Displays current week/month | Format varies: "Woche: 14.–20. Juli 2026" vs. "Juli 2026" |
| Undated Section Heading | `<h6 class="text-muted border-bottom pb-2">` | Section divider for undated entries | Content: "Ohne Datum" |
| ICS Info Box | Bootstrap `.alert.alert-info` (small, not prominent) | Above calendar view | Contains: link to `/ics/{team_id}.ics` + usage text |
| Location Form Field | Standard form-control pattern (like date field) | In list create/edit forms | `<input type="text" maxlength="255">` |

### Reused from Phases 1–5

| Component | Reuse Notes |
|-----------|------------|
| `.card.shadow-sm` | Existing pattern for entry cards; Phase 6 reduces to `.card.card-sm.mb-2.shadow-sm` for compact timeline |
| `.badge.bg-{success\|warning\|secondary}` | Visibility badges continue same style (public=success, protected=warning, private=secondary) |
| Bootstrap Icons (`bi bi-calendar3`, `bi bi-geo-alt`, `bi bi-chevron-left`, `bi bi-chevron-right`) | CDN icons, no new icon set needed |
| `.btn.btn-primary`, `.btn.btn-outline-secondary` | Navigation buttons use existing styles |
| Form pattern (`.form-label.fw-semibold`, `.form-control`, `.form-text`) | Location field uses identical pattern to date field |
| Layout helpers (`.d-flex`, `.justify-content-between`, `.gap-2`, `.mb-4`) | Bootstrap utilities, no new classes |

---

## Mobile-First Breakpoints

| Breakpoint | Width | Layout Adjustments |
|------------|-------|-------------------|
| xs (mobile) | <576px | Calendar timeline full-width, single column, `.card-sm` for compact cards |
| sm | 576px+ | No change to calendar (already single column by design) |
| md | 768px+ | Tab navigation may flex; no major layout shift for calendar |
| lg | 992px+ | Navigation controls and period label side-by-side if desired |

**Notes:** Calendar is intentionally single-column (vertical timeline) across all breakpoints. No responsive grid collapse needed. Tab-switcher uses Bootstrap `.nav-tabs` which is mobile-responsive by default.

---

## Registry Safety

| Registry | Tool | Blocks Used | Safety Gate |
|----------|------|------------|-------------|
| Bootstrap 5.3 | Official CDN | All components (card, badge, nav-tabs, form-control, btn, alert) | Not required; official Bootstrap, verified in Phases 1–5 |
| Bootstrap Icons 1.11.0 | Official CDN | `bi-calendar3`, `bi-geo-alt`, `bi-chevron-left`, `bi-chevron-right`, `bi-list-ul` | Not required; official Bootstrap Icons, same CDN as Phases 1–5 |

**No third-party registries declared.** Phase 6 uses only official Bootstrap components and icons delivered via CDN.

---

## Database Schema Changes

| Table | Column | Type | Constraint | Purpose |
|-------|--------|------|-----------|---------|
| lists | location | VARCHAR(255) | NULL | Optional location field (D-15) |

**Migration pattern:** Idempotent ALTER TABLE in schema.sql (existing convention):
```sql
ALTER TABLE lists ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL;
```

---

## Checker Sign-Off

- [ ] Dimension 1 Copywriting: Copy contract complete, all elements specified, German UI consistent
- [ ] Dimension 2 Visuals: Component inventory complete, all new elements tied to existing Bootstrap patterns
- [ ] Dimension 3 Color: Existing color scheme applies; no new values; visibility badges consistent
- [ ] Dimension 4 Typography: Existing typography scale applies; day headers use `<h6>`
- [ ] Dimension 5 Spacing: Existing 8px scale applies; `mb-4` for form sections, `mb-2` for compact cards
- [ ] Dimension 6 Registry Safety: Only official Bootstrap CDN and Bootstrap Icons; no third-party blocks

**Approval:** pending

---

## Upstream References

| Source | Sections Used | Decisions Locked |
|--------|---------------|-----------------|
| CONTEXT.md | D-01 to D-17 | Tab-switcher, timeline view, ICS export URL schema, location field, undated section label |
| RESEARCH.md | Patterns 1–5, Standard Stack, Architecture, Pitfalls | Bootstrap nav-tabs, DateTime calculations, RFC 5545 ICS generation, SQL NULL handling, calendar entry card structure |
| CLAUDE.md | Design System, Conventions | Bootstrap 5.3 CDN, German UI, mobile-first, form patterns, visibility badges |
| Phases 1–5 artifacts | Typography, Color, Spacing | Reusable Bootstrap defaults, existing badge colors, form-control pattern, min-touch 44px |

---

*UI-SPEC generated: 2026-07-14 by gsd-ui-researcher*
*Phase 06: Calendar — Lists with Date, Location & ICS Export*
