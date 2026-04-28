# Project Research Summary

**Project:** Team Manager
**Domain:** Sports team management web app (PHP/PostgreSQL, German UI, mobile-first)
**Researched:** 2026-04-28
**Confidence:** HIGH (Stack, Architecture) / MEDIUM-HIGH (Features, Pitfalls)

## Executive Summary

Team Manager is a security-critical roster application built around a spreadsheet-like list metaphor. Coaches define columns, players occupy rows, and the system automatically aggregates statistics per player across all lists. The product's core differentiator is the global/local column distinction: global columns (boolean or number) appear in every list and feed into statistics; local columns (any type) are per-list notes that aren't aggregated.

The recommended approach is vanilla PHP 8.3+ with PostgreSQL 14+ and Bootstrap 5 via CDN — no frameworks, no build pipeline. This matches the explicit project constraints and keeps the stack comprehensible for a solo developer. The database uses an EAV (Entity-Attribute-Value) pattern: a `columns` table defines structure per team, a `values` table stores all cell data. This avoids schema migrations every time a coach adds a column.

The dominant risk is **authorization leakage**: three visibility states, dynamic column types, and role-based row editing create multiple security checkpoints that must all agree. Prevention requires centralizing visibility logic, enforcing team isolation in every SQL query, and implementing PostgreSQL Row-Level Security from day one (retrofitting is painful). Secondary risk: credentials shown on-screen must never reach logs or browser history.

## Key Findings

### Recommended Stack

Vanilla PHP 8.3+ with native sessions, `password_hash()` (bcrypt, cost 13), PDO + PDO_PGSQL for all database access, and Bootstrap 5 via CDN for mobile-first CSS. No Composer required for v1. The PHP Filter extension (`filter_var`, `filter_input`) handles all validation. CSRF tokens via `random_bytes()` + `hash_equals()`.

**Core technologies:**
- PHP 8.3+: Authentication, sessions, routing, templating — all built-in, no framework
- PostgreSQL 14+: ACID guarantees, Row-Level Security, JSONB for future flexibility
- PDO/PDO_PGSQL: Prepared statements, SQL injection prevention, ships with PHP
- Bootstrap 5 (CDN): Mobile-first grid, responsive components, no build step
- Native sessions (hardened): `cookie_secure`, `cookie_httponly`, `cookie_samesite=Strict`

**Avoid:** Laravel/Symfony (over-engineered), Tailwind (requires Node.js build), Doctrine ORM (unnecessary abstraction for this schema).

### Expected Features

**Must have (table stakes):**
- Role-based auth (admin/coach/player) with team isolation
- Player/coach CRUD with on-screen password display
- Lists with configurable columns (global + local)
- Visibility states: public / protected / private
- Players edit only their own row
- Statistics per player (sum/count of global columns across all lists)
- Mobile-first responsive layout in German

**Should have (differentiator):**
- Global columns as team-level templates reused across all lists
- Per-player statistics page with boolean counts + number sums
- Flexible list purpose (not tied to specific sport/event type)

**Defer to v2+:**
- Email notifications, real-time collaboration, seasons/tournaments
- PDF/CSV export, equipment tracking, fitness profiles
- Multi-team user accounts, public league rankings

### Architecture Approach

The app has 9 components organized around a central request router that enforces RBAC before delegating to business logic. Team isolation is a cross-cutting concern: every SQL query filters by `team_id`. The EAV schema (`columns` + `values` tables) avoids ALTER TABLE on column changes. Statistics are computed via SQL aggregation (SUM/COUNT), not PHP loops.

**Major components:**
1. **Auth & Session** — Login, credential generation, password reset (on-screen display)
2. **Request Router** — RBAC enforcement; all routes check role before delegating
3. **Team Management** — Admin-only: create teams, assign coaches
4. **Player Management** — Coach-scoped: add/deactivate players, reset passwords
5. **List & Column Management** — Create lists, define global/local columns, set visibility
6. **Row Operations** — Cell read/write with visibility + ownership enforcement
7. **Statistics & Aggregation** — SQL SUM/COUNT of global columns across lists per player
8. **Visibility & Access Control** — Centralized function, enforced at query layer

### Critical Pitfalls

1. **Authorization Leakage Through Stale Sessions** — Session contains stale role/team; mid-session changes bypass checks. Re-verify `user.role` and `user.team_id` from DB on every privileged action.
2. **Credential Display Leaking to Logs** — Time-limit password display (hide after 60s), disable autocomplete, rate-limit resets (1/hour), never log plaintext passwords.
3. **Dynamic Column Schema Without Type Enforcement** — Statistics break when text enters a number column. Validate every write against `column.data_type`; add PostgreSQL CHECK constraints.
4. **Visibility State Confusion** — Three states implemented inconsistently. Centralize all visibility logic in a single function; test all permission combinations explicitly.
5. **Row-Level Ownership Not Checked** — Role + visibility check is insufficient; a player can craft a request targeting another player's row. Add explicit `player_id` ownership check on every row edit.

## Implications for Roadmap

### Phase 1: Foundation — Auth, Database Schema, Admin
**Rationale:** Everything depends on who the user is and their team. RLS and team isolation must be correct from the start — impossible to retrofit safely.
**Delivers:** Working login for all three roles, database schema (teams/coaches/players/lists/columns/values), admin config-file auth, team creation and coach assignment.
**Avoids:** Authorization leakage (RLS from day one), admin credentials in Git (env vars).

### Phase 2: Team & Player Management
**Rationale:** Coaches can't create lists without players. Player CRUD and password reset flow needed before the core feature.
**Delivers:** Coach can add/deactivate players, reset player passwords (on-screen display), admin can reset coach passwords.
**Implements:** Player Management component, credential generation utility.

### Phase 3: Lists, Columns & Cell Operations (Core Product)
**Rationale:** The spreadsheet is the core product. Visibility rules must be centralized here before statistics rely on them.
**Delivers:** Coaches create lists, define global/local columns, set visibility states. Players and coaches edit cells (own-row enforcement for players).
**Avoids:** Visibility state confusion (centralized logic), row-level ownership bypass (explicit checks).

### Phase 4: Statistics & Aggregation (The Differentiator)
**Rationale:** Only build after visibility is proven — statistics query values through the same access layer.
**Delivers:** Per-player statistics page: boolean global columns → count of true values; number global columns → sum total; filtered to current team across all lists.
**Uses:** SQL aggregation (SUM/COUNT with GROUP BY player), not PHP loops.

### Phase Ordering Rationale

- Phase 1 before everything: auth and schema are hard dependencies for all other components
- Phase 2 before Phase 3: players must exist before list rows
- Phase 3 before Phase 4: visibility rules must be correct before statistics trust them
- Coarse granularity: 4 phases is appropriate for this scope; no need for additional splits

### Research Flags

Phases that may benefit from deeper planning research:
- **Phase 3:** EAV query patterns for visibility-filtered cell reads; CSRF protection in inline form edits
- **Phase 4:** Aggregation query performance profiling; pre-computed stats table vs. on-demand

Standard patterns (skip deep research):
- **Phase 1:** PHP session hardening and bcrypt are OWASP-documented; follow directly
- **Phase 2:** Credential generation with `random_bytes()` is well-established

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies verified against official docs; no novel choices |
| Features | MEDIUM-HIGH | Table stakes consistent across sports management tools; differentiators confirmed by project description |
| Architecture | HIGH | EAV pattern, RLS, visibility-aware queries are established PostgreSQL/PHP patterns |
| Pitfalls | MEDIUM | Sourced from field experience in similar systems; not validated against this specific codebase yet |

**Overall confidence:** HIGH for technical execution, MEDIUM-HIGH for feature prioritization.

### Gaps to Address

- **Timezone handling:** Not specified. Assume Europe/Berlin for German users; define in Phase 1 schema.
- **Statistics decimal precision:** Number columns — integer or decimal? Define data type in Phase 1 column schema.
- **List naming:** Lists need names/labels; date field optional but useful. Confirm in Phase 3 design.

## Sources

### Primary (HIGH confidence)
- Official PHP documentation — sessions, password_hash, Filter extension, random_bytes
- Official PostgreSQL documentation — RLS, JSONB, prepared statements, CHECK constraints
- Bootstrap 5 official docs — mobile-first grid, CDN deployment

### Secondary (MEDIUM confidence)
- OWASP guidelines — session security, CSRF prevention, credential handling
- PHP community patterns — EAV schema, RBAC implementation without ORM

---
*Research completed: 2026-04-28*
*Ready for roadmap: yes*
