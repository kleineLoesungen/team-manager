# Roadmap: Team Manager

**Erstellt:** 2026-04-28  
**Granularität:** Coarse (4 Phasen)  
**Anforderungsabdeckung:** 20/20 v1 Anforderungen gemappt

## Phases

- [ ] **Phase 1: Foundation** - Authentication, Database Schema, Admin Setup
- [ ] **Phase 2: Team & Player Management** - Player CRUD, Password Reset, Credentials
- [ ] **Phase 3: Lists, Columns & Cells** - Spreadsheet Core, Visibility States, Cell Editing
- [ ] **Phase 4: Statistics & Aggregation** - Per-Player Stats, Filtering, Leaderboards

## Phase Details

### Phase 1: Foundation
**Goal**: Establish secure authentication, database schema with Row-Level Security, and admin capabilities for team/coach management

**Depends on**: Nothing (first phase)

**Requirements**: AUTH-01, AUTH-02, AUTH-03, AUTH-04, TEAM-01, TEAM-02, TEAM-03

**Success Criteria** (what must be TRUE):
1. User can log in with username/password and access the application
2. Session automatically expires after inactivity and user is redirected to login
3. Admin (via config file) can create teams and assign coaches to them
4. Database schema is in place with teams, users, sessions, team isolation via team_id columns, and PostgreSQL Row-Level Security enforced
5. Credentials (usernames/passwords) are securely hashed and never displayed in logs

**Plans:** 2/3 plans executed

Plans:
- [x] 01-01-PLAN.md — Project bootstrap: directory structure, DB schema, PDO connection, CSRF utils, front controller router
- [x] 01-02-PLAN.md — Authentication: login/logout handlers, shared Bootstrap 5 layout, login page template
- [x] 01-03-PLAN.md — Admin panel: team CRUD, coach CRUD, password reset with 60s credential modal

---

### Phase 2: Team & Player Management
**Goal**: Coaches can manage their players and reset passwords; admin can reset coach passwords; automatic credential generation for new users

**Depends on**: Phase 1

**Requirements**: TEAM-04

**Success Criteria** (what must be TRUE):
1. Coach can create a new player with auto-generated username and random password displayed once on-screen
2. Coach can deactivate a player (soft delete)
3. Coach can reset a player's password and see the new random password displayed on-screen
4. Admin can reset a coach's password and see the new random password displayed on-screen
5. Credentials are never shown in browser history or logs; display is time-limited (cleared after 60 seconds)

**Plans**: TBD

---

### Phase 3: Lists, Columns & Cells
**Goal**: Coaches create lists with configurable columns (global and local), players and coaches edit cells according to visibility rules and ownership

**Depends on**: Phase 2

**Requirements**: LIST-01, LIST-02, LIST-03, LIST-04, LIST-05, CELL-01, CELL-02, CELL-03, CELL-04

**Success Criteria** (what must be TRUE):
1. Coach can create a list with a name and assign it a visibility state (public, protected, or private)
2. Coach can define global columns at team level (boolean or number type) that appear in all lists of that team
3. Coach can define local columns per list (boolean, number, or text type) visible only in that list
4. Coach can change a list's visibility state at any time
5. Player sees only their own row in public lists and can edit only their own cells
6. Coach sees all rows in public/protected lists and can edit all cells
7. Private lists are invisible to players; coaches have full read/write access
8. All visibility rules are enforced: players cannot see data they shouldn't and cannot edit cells outside their row; coaches cannot edit/see private lists by accident

**Plans**: TBD

---

### Phase 4: Statistics & Aggregation
**Goal**: Generate per-player statistics aggregating global column values across all lists, with filtering and ranking capabilities

**Depends on**: Phase 3

**Requirements**: STAT-01, STAT-02, STAT-03

**Success Criteria** (what must be TRUE):
1. Statistics page shows each player with their aggregated metrics: sum of number-type global columns, count of true values for boolean-type global columns, computed across all lists
2. Coach can filter statistics by specific lists or date range
3. Coach can view a team-wide leaderboard sorted by any global column
4. Statistics are only computed from public and protected lists (private lists excluded)
5. Each player sees only their own statistics

**Plans**: TBD

---

## Progress Table

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation | 2/3 | In Progress|  |
| 2. Team & Player Management | 0/2 | Not started | — |
| 3. Lists, Columns & Cells | 0/4 | Not started | — |
| 4. Statistics & Aggregation | 0/3 | Not started | — |

---

**Notes:**
- All phases enforce team isolation via team_id in SQL queries
- Authorization leakage is mitigated by centralizing visibility logic and enforcing PostgreSQL RLS from Phase 1
- EAV (Entity-Attribute-Value) pattern used for dynamic columns to avoid schema migrations
- Credential display is time-limited and never logged
