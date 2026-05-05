---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: milestone
status: unknown
last_updated: "2026-04-30T12:50:53.815Z"
progress:
  total_phases: 9
  completed_phases: 4
  total_plans: 14
  completed_plans: 14
---

# Project State: Team Manager

**Last Updated:** 2026-05-05 - Completed quick task 260505-rpx: restore scroll position after filter change on coordinator stats page  
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

Phase: 999.1
Plan: Not started

## Phase Summary

| Phase | Name | Requirements | Status |
|-------|------|--------------|--------|
| 1 | Foundation | 7 (AUTH×4, TEAM×3) | **COMPLETE** |
| 2 | Team & Player Mgmt | 1 (TEAM×1) | **COMPLETE** |
| 3 | Lists, Columns & Cells | 9 (LIST×5, CELL×4) | **COMPLETE** |
| 4 | Statistics & Aggregation | 3 (STAT×3) | **COMPLETE** |

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
| CROSS JOIN on global columns subquery for aggregation | Guarantees all-player × all-column matrix regardless of cell existence; COALESCE converts NULLs to 0 | 04-02 |
| Leaderboard carries filter state via hidden inputs | Ensures list_id and date range filters apply consistently to both stats table and leaderboard ranking | 04-02 |
| Player stats visibility filter on LEFT JOIN condition (not WHERE) | Ensures players with zero cells still appear with 0 values via COALESCE; WHERE would exclude them | 04-03 |
| No player name column in player stats table | Player always views own row — name label is redundant and consumes mobile screen width | 04-03 |

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

### Quick Tasks Completed

| # | Description | Date | Commit | Directory |
|---|-------------|------|--------|-----------|
| 260505-rpx | restore scroll position after filter change on coordinator stats page — sessionStorage save/restore on GET form submit | 2026-05-05 | 8a941f0 | [260505-rpx-restore-scroll-position-after-filter-cha](.planning/quick/260505-rpx-restore-scroll-position-after-filter-cha/) |
| 260505-r6i | replace all plain checkboxes with form-switch toggles (3em×1.75em) across 5 templates | 2026-05-05 | 64b93cd | [260505-r6i-replace-all-checkboxes-with-bigger-toggl](.planning/quick/260505-r6i-replace-all-checkboxes-with-bigger-toggl/) |
| 260505-9eh | rename moderator→coordinator, coach→coordinator, player→member — folder renames, routes, DB role, RLS, German UI, Migration 008, pgAdmin SQL | 2026-05-05 | e358217 | [260505-9eh-replace-moderator-with-coordinator-renam](.planning/quick/260505-9eh-replace-moderator-with-coordinator-renam/) |
| 260505-93l | member-centric stats per-list breakdown — Listenübersicht table on /coordinator/stats + member selector on /coordinator/stats | 2026-05-05 | a03622d | [260505-93l-member-centric-stats-per-list-breakdown-](.planning/quick/260505-93l-member-centric-stats-per-list-breakdown-/) |
| 260505-8ud | unbind global columns from list in list settings — Globale Spalten card + two-step no-JS confirm | 2026-05-05 | 19eba61 | [260505-8ud-unbind-global-columns-from-list-in-list-](.planning/quick/260505-8ud-unbind-global-columns-from-list-in-list-/) |
| 260505-8ph | fix missing columns_delete RLS policy — canonical SQL, db_init_rls, Migration 007 | 2026-05-05 | 92f196f | [260505-8ph-fix-missing-columns-delete-rls-policy-ca](.planning/quick/260505-8ph-fix-missing-columns-delete-rls-policy-ca/) |
| 260504-sn6 | fix free list nested form bug and replace checkboxes with toggles | 2026-05-04 | 2090acf | [260504-sn6-fix-free-list-nested-form-bug-and-replac](.planning/quick/260504-sn6-fix-free-list-nested-form-bug-and-replac/) |
| 260504-s22 | free list type (custom rows, moderator-managed), delete local columns, bigger boolean toggles | 2026-05-04 | 3cd8cda | [260504-s22-free-list-type-with-custom-rows-delete-l](.planning/quick/260504-s22-free-list-type-with-custom-rows-delete-l/) |
| 260504-rg6 | delete applied migration scripts migrate_004 and migrate_005 from repository | 2026-05-04 | 55b679d | [260504-rg6-delete-applied-migration-scripts-migrate](.planning/quick/260504-rg6-delete-applied-migration-scripts-migrate/) |
| 260504-i94 | rename DB role values coach→moderator, player→mitglied: migrate DB + update all PHP references | 2026-05-04 | 94372d2 | [260504-i94-rename-db-role-values-coach-moderator-pl](.planning/quick/260504-i94-rename-db-role-values-coach-moderator-pl/) |
| 260504-hx2 | stats: fix name format (first+last), add percentage to ranking, fix PDO bind param error on filter | 2026-05-04 | fb095e9 | [260504-hx2-stats-fix-name-format-first-last-add-per](.planning/quick/260504-hx2-stats-fix-name-format-first-last-add-per/) |
| 260504-ajx | Language: Du-speech, sort by first name, Trainer→Moderator, Spieler→Mitglied | 2026-05-04 | 50105b2 | [260504-ajx-task-change-language-use-german-du-speec](.planning/quick/260504-ajx-task-change-language-use-german-du-speec/) |
| 260504-8s1 | Styling overhaul — modern design, toggle switches for boolean, admin-configurable brand color | 2026-05-04 | 66990e0 | [260504-8s1-styling-overhaul-modern-design-toggle-sw](./quick/260504-8s1-styling-overhaul-modern-design-toggle-sw/) |
| 260503-06c | Coach delete list — two-step confirm (Gefahrenzone + confirmation page, no JS) | 2026-05-03 | 543d2f7 | [260503-06c-add-delete-list-for-coaches-before-actio](./quick/260503-06c-add-delete-list-for-coaches-before-actio/) |
| 260502-wfw | List totals row (Gesamt-Zeile) + coach stats column-filter dropdown | 2026-05-02 | 3448187 | [260502-wfw-list-view-totals-row-stats-ranking-colum](./quick/260502-wfw-list-view-totals-row-stats-ranking-colum/) |
| 260501-txm | add list optional date and description; use date in stats filter with toggle | 2026-05-01 | 66512b9 | [260501-txm-add-list-optional-date-and-description-u](./quick/260501-txm-add-list-optional-date-and-description-u/) |
| 260502-w8p | Coach-only local columns — 'Nur für Trainer' flag; player view silently omits them via PHP filter + RLS | 2026-05-02 | 6059ad2 | [260502-w8p-add-feature-at-list-to-add-local-columns](./quick/260502-w8p-add-feature-at-list-to-add-local-columns/) |
| 260502-d5d | Coach + player stats — time-window ranking (Gesamt / Letzte 4 Wo. / 4–8 Wo. / 8–12 Wo.) with sortable headers | 2026-05-02 | b57f8ba | [260502-d5d-enhance-statistics-for-coaches-and-playe](./quick/260502-d5d-enhance-statistics-for-coaches-and-playe/) |
| 260501-ahg | Hetzner Shared Hosting — ROOT_PATH auto-detect, DB self-init, FORCE RLS, FTP deploy | 2026-05-01 | 5945bf7 | [260501-ahg-restructure-for-hetzner-shared-hosting-p](./quick/260501-ahg-restructure-for-hetzner-shared-hosting-p/) |
| 260501-9rx | Production Deployment Script — deploy.sh rsync script + public/.htaccess | 2026-05-01 | 5c42c27 | [260501-9rx-production-deployment-should-be-just-cop](./quick/260501-9rx-production-deployment-should-be-just-cop/) |
| 260430-rqd | App Title Branding — admin sets app title, navbar shows title + team | 2026-05-01 | 3c21e8a | [260430-rqd-admin-could-set-title-of-app-in-admin-pa](./quick/260430-rqd-admin-could-set-title-of-app-in-admin-pa/) |
| 260430-rhh | Admin UI active/inactive grouping for teams and coaches | 2026-04-30 | b15631c | [260430-rhh-admin-ui-active-inactive-grouping-for-te](./quick/260430-rhh-admin-ui-active-inactive-grouping-for-te/) |
| 260430-rbt | Coach list detail — bulk inline editing | 2026-04-30 | 6e984a1 | [260430-rbt-coach-list-detail-bulk-inline-editing](./quick/260430-rbt-coach-list-detail-bulk-inline-editing/) |
