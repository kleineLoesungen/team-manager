---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: unknown
last_updated: "2026-04-30T11:18:04.363Z"
progress:
  total_phases: 7
  completed_phases: 2
  total_plans: 11
  completed_plans: 10
---

# Project State: Team Manager

**Last Updated:** 2026-04-30 (Plan 03-04 complete — coach list detail table view and row cell editing with UPSERT)  
**Model Profile:** Budget (Claude Haiku)  
**Workflow:** Research → Plan → Build → Verify → Transition

## Project Reference

**Core Value:**  
Trainer erfassen den Spielereinsatz und Kennzahlen über alle Listen hinweg — Statistik pro Spieler auf einen Blick.

**Language:** German (UI), English/German (Planning)  
**Stack:** PHP 8.3+ / PostgreSQL 14+ / Bootstrap 5 CDN / No framework  
**Key Constraint:** Mobile-first, no email infrastructure, single admin in config

**Milestone:** v1 (Roadmap phase)

---

## Current Position

Phase: 03 (lists-columns-cells) — EXECUTING
Plan: 5 of 5

## Phase Summary

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 1 | Foundation | 7 (AUTH×4, TEAM×3) | **COMPLETE** |
| 2 | Team & Player Mgmt | 1 (TEAM×1) | **COMPLETE** |
| 3 | Lists, Columns & Cells | 9 (LIST×5, CELL×4) | Pending |
| 4 | Statistics & Aggregation | 3 (STAT×3) | Pending |

---

## Key Decisions

| Decision | Rationale | Status |
|----------|-----------|--------|
| 4-phase structure (coarse granularity) | Foundation → Management → Core → Stats aligns with research; natural dependency chain | Approved in research |
| PostgreSQL RLS from Phase 1 | Authorization leakage is retrofitted with pain; must be correct from day one | Design principle |
| EAV pattern for dynamic columns | Avoids schema migrations when coaches add columns; proven PostgreSQL pattern | Design principle |
| Credential display time-limit (60s) | Prevents credentials reaching logs or browser history; matches offline-only password-reset constraint | Design principle |
| Admin credentials in config.php/env (not users table) | Eliminates auth bootstrapping problem; single admin per D-02 | 01-01 |
| PDO ATTR_EMULATE_PREPARES=false | Mandatory for PostgreSQL — native prepared statements with real type safety | 01-01 |
| SESSION_TIMEOUT = 8h sliding window with cookie_samesite=Strict | Balance coach usability vs security; Strict prevents all cross-site CSRF | 01-01 |
| render_login_page() in layout.php (not separate controller) | Keeps layout contract centralized; single location for page assembly | 01-02 |
| Bootstrap 5.3 via CDN with SRI integrity hashes | No build step needed; tamper-resistant delivery | 01-02 |
| Vague error messages for all credential failures | No per-field information disclosure; prevents username enumeration | 01-02 |
| render_admin_page() takes callable $body | Admin pages pass closures, keeping template logic co-located with data; avoids scattered variable injection | 01-03 |
| Credential modal as full-page include (not Bootstrap .modal) | Cache-Control: no-store header set before HTML, no Bootstrap JS dependency for security display | 01-03 |
| POST-redirect-GET pattern for admin errors via ?error= | Prevents form resubmission on browser refresh | 01-03 |
| Separate coach layout from admin layout | No cross-role template sharing; each role owns its layout | 02-01 |
| Phase 2 coach nav has only Spieler | No placeholder items for unimplemented routes | 02-01 |
| Already-authenticated redirect also updated in login_handler | Covers both post-login and revisiting /login while logged in | 02-01 |
| RLS-only player listing: require_coach() sets team context; no explicit team_id WHERE clause needed | RLS enforces isolation at DB level; query remains readable | 02-02 |
| Reuse admin credential_modal.php for player creation | Consistent UX, no template duplication across roles | 02-02 |
| $_SESSION['team_id'] used for INSERT team_id on player create | Coach session carries validated team_id set at login; no DB re-query | 02-02 |
| Triple-constraint ownership check (id + team_id + role='player') on all player actions | Prevents cross-team access even if RLS is bypassed; defense-in-depth in UPDATE statements too | 02-03 |
| Reuse admin credential_modal.php for player password reset in coach context | Consistent UX across roles, no template duplication | 02-03 |
| EAV global columns use list_id IS NULL as flag (no separate is_global boolean) | Single-column sentinel avoids redundant boolean; aligns with SQL pattern for optional FK | 03-01 |
| set_team_context() extended with role and user_id params | Phase 3 RLS visibility policies require app.current_role and app.current_user_id; set at session establishment | 03-01 |
| can_edit_cell() returns true for coaches regardless of visibility (CELL-03); players restricted to public + own row (CELL-01) | Single authoritative PHP check; RLS is defense-in-depth | 03-01 |

---

## Accumulated Context

### Architecture Principles

- **Team Isolation**: Every SQL query filters by `team_id`; enforced in PDO layer
- **Visibility Centralization**: Single authorization function for public/protected/private states
- **Ownership Checks**: Player can only edit own row; explicit `user_id` == `player_id` on every cell write
- **Security at Entry**: CSRF tokens, session hardening, rate-limited resets
- **EAV Schema**: `columns` table (structure), `values` table (data) — enables flexible column types per team

### Critical Pitfalls to Avoid

1. Stale session state → Re-verify role + team_id from DB on privileged actions
2. Credentials in logs → Never log plaintext; time-limit display
3. Type confusion in dynamic columns → Validate every write against column.data_type
4. Visibility state inconsistency → Centralize all permission logic
5. Row ownership bypass → Explicit ownership check on every row edit

### Next Actions (After Roadmap Approval)

1. `/gsd:plan-phase 1` — Decompose Foundation into executable plans
2. Initialize project directory structure + composer.json (if needed) or vendor isolation
3. Create database connection test + schema design artifact
4. Implement Session + Auth handlers
5. Stub Admin configuration layer

---

## Session Continuity

**Knowledge Retained:**

- Project uses vanilla PHP (no Laravel/Symfony)
- Security is the dominant risk surface (auth leakage, credential exposure)
- Research has vetted stack + architecture (HIGH confidence for technical execution)
- 4-phase structure is stable (unlikely to change with new information)

**Assumptions Made:**

- Timezone: Europe/Berlin (German users)
- Number columns: Integers (can be refined in Phase 1 design)
- Statistics granularity: Computed on-demand (can be optimized in Phase 4)

**Open Questions (Research Flag):**

- Phase 3: EAV query patterns for visibility-filtered cell reads; confirm CSRF strategy for inline forms
- Phase 4: Statistics query performance — may need caching or materialized views

---

## Performance Metrics

| Metric | Target | Status |
|--------|--------|--------|
| Requirement coverage | 100% | 20/20 ✓ |
| Phase dependency chain | Linear (1→2→3→4) | Validated ✓ |
| Success criteria per phase | 2-5 observable behaviors | Phase 1: 5, Phase 2: 5, Phase 3: 8, Phase 4: 5 ✓ |
| Roadmap stability | Minimal churn after approval | Research informs structure |

---

**Roadmap Status:** Awaiting user review and approval before planning commences.
| Phase 01-foundation P01-01 | ~25min | 3 tasks | 9 files |
| Phase 01-foundation P02 | 2min | 2 tasks | 4 files |
| Phase 01-foundation P03 | 6 | 2 tasks | 11 files |
| Phase 02-team-player-mgmt P01 | 5 | 2 tasks | 4 files |
| Phase 02-team-player-mgmt P02 | 5 | 2 tasks | 4 files |
| Phase 02-team-player-mgmt P03 | 5min | 1 tasks | 1 files |
| Phase 03-lists-columns-cells P01 | 10 | 3 tasks | 5 files |
| Phase 03-lists-columns-cells P02 | 2 | 2 tasks | 4 files |
| Phase 03 P03 | 8 | 2 tasks | 10 files |
| Phase 03-lists-columns-cells P04 | 2 | 2 tasks | 4 files |
