# Phase 9: UI-Vereinheitlichung - Context

**Gathered:** 2026-08-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Pure refactoring: all 62 templates across 4 roles (admin, coordinator, member, public) unified
under a single `render_page(array $opts, callable $body)` layout function and a single
`public/css/app.css` stylesheet. Twelve standard component partials created in
`src/templates/components/`. No new features, no DB changes, no dark mode.

**Tracer:** Coordinator list overview (`/coordinator/lists`) is the first end-to-end proof
before mass migration begins.

</domain>

<decisions>
## Implementation Decisions

### Migration wave order
- **D-01:** Claude's discretion — suggested order: Infrastructure plan first (render_page,
  partials, app.css token migration), then tracer plan (coordinator lists), then coordinator
  role (remaining 27 templates), then member (11), then admin (20), then public (2).
  Coordinator first after tracer because it has the most templates and establishes patterns;
  admin last because it is less user-facing.

### ?success= flash message approach
- **D-02:** Claude's discretion — use `?success=1` GET param (consistent with existing `?error=`
  pattern). Each template that can show success maps `$_GET['success'] ?? null` to a
  context-specific string (e.g. "Gespeichert.", "Erfolgreich gesendet.") directly in the
  template's body callable. No URL-encoded messages, no session flash — keeps the same
  PRG model already used for `?error=`.

### Old layout function strategy
- **D-03:** Claude's discretion — keep `render_coach_page()`, `render_member_page()`,
  `render_admin_page()` as thin wrappers delegating to `render_page()` for the duration of
  Phase 9. Delete the old functions in the final cleanup plan after all templates are migrated.
  This avoids a big-bang refactor and lets each migration plan be independently testable.

### Dynamic `--brand` CSS variable
- **D-04:** Claude's discretion — after the inline `<style>` block moves to app.css, keep a
  minimal single-line inline style in `render_layout_head()` containing ONLY the dynamic
  `--brand` token: `<style>:root{--brand:<?= $safe_color ?>;}</style>`. Everything else
  (tokens, overrides, dark mode) moves to app.css. This satisfies the UI-SPEC requirement
  to remove the large inline block while preserving the DB-driven brand color.

### Claude's Discretion (all remaining details)
- Exact plan count and task breakdown within each wave
- Exact PHP helper signatures for any intermediate utilities
- Order of templates within each role's migration plan
- How to handle edge cases (templates with complex inline JS that references CSS classes)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### UI contract and baseline
- `.planning/UI-BASELINE.md` — Overarching UI rules for all roles: spacing tokens, component
  patterns, archetype definitions, language rules, banned utilities. MUST read before planning
  any migration task.
- `.planning/phases/09-ui-vereinheitlichung/09-UI-SPEC.md` — Full design contract for Phase 9:
  all 12 partial PHP signatures with exact HTML output, migration constraints table, spacing
  scale with banned utilities, tracer acceptance criteria, typography and color tokens.

### Existing layout files (migration sources)
- `src/templates/layout.php` — Shared `render_layout_head()` / `render_layout_foot()`;
  contains the large inline `<style>` block that moves to app.css in Phase 9.
- `src/templates/coordinator/layout.php` — `render_coach_page()` with coordinator nav map.
- `src/templates/member/layout.php` — `render_member_page()` with member nav map.
- `src/templates/admin/layout.php` — `render_admin_page()` with admin nav map.

### CSS token source
- `public/css/app.css` — Current token layer (220 lines). Phase 9 extends this with all
  tokens currently in the inline `<style>` block of layout.php.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `src/templates/layout.php`: `render_layout_head()` / `render_layout_foot()` — the new
  `render_page()` will call these internally; they remain as internal helpers.
- `public/css/app.css`: Already has `.tm-empty`, `.tm-matrix`, `.tm-actionbar`, `.tm-filters`,
  `.tm-danger-zone`, `.tm-group-label` — partials reference these existing classes.
- `src/templates/coordinator/lists.php` lines 360–374: Already implements `?success=1`
  JS URL cleanup with `history.replaceState` — copy this pattern to all templates.

### Established Patterns
- PRG error flow: handlers redirect to `?error=message`, templates read `$_GET['error']`
  and render alert-danger. The `?success=` flow mirrors this pattern.
- Three role layout functions (`render_coach_page`, `render_member_page`, `render_admin_page`)
  are nearly identical — only the tabbar nav items differ. `render_page($opts, $body)` reads
  `$opts['role']` to select the correct tabbar.
- All template files call exactly one layout function at the top; body content is a closure.

### Integration Points
- `public/index.php` routing: all routes dispatch to handler → handler calls template →
  template calls layout function. No changes to routing needed.
- `src/utils/helpers.php`: `e()`, `redirect()`, `require_coordinator()` etc. remain unchanged.
- Bootstrap CDN + BI CDN links stay in `render_layout_head()`. BI CDN gets SRI hash added
  per UI-SPEC (currently missing).

</code_context>

<specifics>
## Specific Ideas

- The UI-SPEC at `.planning/phases/09-ui-vereinheitlichung/09-UI-SPEC.md` is the authoritative
  source for all partial signatures. Planners and executors MUST follow it exactly — do not
  invent alternative signatures.
- Violation counts from UI-SPEC (for planning task scoping):
  - 248 occurrences of `form-control-sm` / `form-select-sm` to remove
  - 21 `py-5` empty-state patterns to replace with `render_empty()`
  - 9 inline `style="width:3em;height:1.75em;cursor:pointer;"` switch patterns to remove
  - 40+ inline `style=""` attributes to remove
  - `style="font-size:2rem;"` icon pattern (appears in coordinator/lists.php line 172) → remove
- Bootstrap Icons CDN is missing SRI hash — add during infrastructure plan per UI-SPEC.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 09-ui-vereinheitlichung*
*Context gathered: 2026-08-29*
