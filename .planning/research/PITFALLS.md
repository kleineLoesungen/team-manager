# Domain Pitfalls: PHP/PostgreSQL Team Management

**Domain:** Team management web app with role-based access, dynamic columns, no-email auth, mobile-first UI
**Researched:** 2026-04-28
**Confidence:** MEDIUM (domain knowledge + project constraints; field-tested in similar systems)

---

## Critical Pitfalls

### Pitfall 1: Authorization Leakage Through Session/Cookie Management

**What goes wrong:**
User switches between teams/roles (if implemented later), session still contains stale permissions. Trainer logs in, switches context, but their session cookie still has admin-level data cached. Coach can edit data outside their role boundaries because the authorization check only happens on page load, not per operation.

**Why it happens:**
- Single session object holds all role/team info; switching contexts doesn't invalidate it
- Authorization checks are page-level rather than operation-level
- No audit trail of who accessed what, so stale access goes unnoticed
- Team isolation logic in queries assumes `$_SESSION['team_id']` is always correct, but stale sessions bypass this

**Consequences:**
- A coach who was promoted to trainer can still access private lists from their old role
- Session fixation attacks: attacker keeps old session alive, data structures change, stale role perms still apply
- Cascading privilege escalation if a user was ever an admin in testing/dev

**Prevention:**
- Invalidate session on every role/team context change (explicit logout/login required)
- Query operations should re-verify `user.role` and `user.team_id` from database, not session, on every privileged action
- Implement per-operation authorization (not just page-level)
- Log all role-changing actions with timestamp, user, old role, new role
- Set session timeout aggressively (15-30 min for team/role changes)

**Detection warning signs:**
- Users report being able to see/edit lists after role change before refresh
- Multiple role entries in browser session storage/cookies
- Audit logs show access without corresponding session creation

**Phase mapping:**
- **Phase 2 (Authentication & Core RBAC):** Implement operation-level checks in all role-dependent queries
- **Phase 3 (List Management):** Audit all list access points for session statefulness
- **Phase 5 (Admin):** Build access audit logging

---

### Pitfall 2: Credential Display Security Theater Without Secure Handling

**What goes wrong:**
Password is displayed on screen in plaintext after generation. User reads it, but:
- Password is not properly cleared from memory/logs after display
- Admin copies/pastes password into unsafe channel (unencrypted email, Slack, SMS)
- Password is stored in browser history or autocomplete
- Multiple concurrent password resets create confusion about which one is valid
- No way to confirm password was actually written down / understood by recipient

**Why it happens:**
- Teams are small, mobile-first users expect quick/casual workflows
- Assumption that "admin on same network" = "secure enough"
- No time-limited confirmation that password was received
- No rate limiting on password reset, so attackers can flood with new passwords

**Consequences:**
- Leaked credentials in chat logs / shared device history
- User never writes down password, returns later, can't remember it, asks for reset again (support burden)
- Player account compromised, coach doesn't notice because password reset log is unclear
- If password is reset multiple times, which one is active? Leads to account lockout/confusion

**Prevention:**
- Implement time-limited display (password shown for 60 seconds, then hidden, user must confirm they wrote it down)
- Disable browser autocomplete on password display: `autocomplete="off"`
- Log password reset with IP, timestamp, reset-by user; do NOT log the password itself
- Implement rate limiting: max 1 password reset per user per hour (prevents flood attacks)
- Require explicit confirmation page: "I have written down the password" before dismissing
- Hash all passwords with bcrypt/argon2, even temporary ones
- Clear generated password from memory/server logs immediately after display
- Send audit email/notification to admin if password reset is unusual (e.g., multiple resets same day)

**Detection warning signs:**
- Player locked out, reports "I had the password but can't find it"
- Multiple password resets logged for same user within minutes
- Passwords found in browser history/autocomplete fields
- Auth fails with "old" password, suggesting concurrent password states

**Phase mapping:**
- **Phase 2 (Authentication):** Implement time-limited display, confirmation, rate limiting
- **Phase 3 (Audit Logging):** Track password resets and confirm receipts
- **Phase 6 (Security Hardening):** Add notification system for unusual reset patterns

---

### Pitfall 3: Dynamic Column Schema Without Schema Enforcement

**What goes wrong:**
Coaches define columns flexibly. Column A is supposed to be boolean (count of games), but one coach accidentally creates it as text. Data validation doesn't enforce the declared type on insert. Statistcs page tries to sum text values, crashes or returns NULL. A coach deletes a column that's mid-use, leaving orphaned data in rows.

**Why it happens:**
- Schema flexibility is a feature ("define any column type")
- No validation layer between column definition and data insertion
- Aggregation logic assumes type correctness without checking
- No migration/deprecation path for column deletions

**Consequences:**
- Statistics page silently breaks for specific team/column combos
- Coach spends 30 min debugging why totals are wrong
- Type mismatches spread as coaches copy/paste list templates
- Deleting a column leaves data in the table (data orphaning)
- Hard to audit: "which columns are still in use?"

**Prevention:**
- Store column type in schema definition; validate every INSERT/UPDATE against declared type
- Use PostgreSQL CHECK constraints: `CHECK (column_type IN ('boolean', 'number', 'text'))`
- On column creation, validate type is in allowed set (boolean, number, text)
- On INSERT/UPDATE to a row, validate each field matches its column's declared type
- For column deletion, require explicit confirmation if column has non-NULL values in any row
- Log column schema changes (creation, modification, deletion) with user and timestamp
- Statistics aggregation should validate column type before summing/counting; skip or error clearly if type is wrong
- Implement a "soft delete" for columns: mark as inactive instead of hard delete, keep data intact

**Detection warning signs:**
- Statistics page shows NULL or 0 for normally populated columns
- Coach reports "can't aggregate this column"
- Rows have text in a boolean column, or numbers in a text column
- Column counts mismatch between list definition and actual row data

**Phase mapping:**
- **Phase 3 (List & Column Management):** Enforce column type validation at INSERT/UPDATE
- **Phase 4 (Statistics):** Add type validation before aggregation; error handling for type mismatches
- **Phase 5 (Admin):** Build column usage audit and cleanup tools

---

### Pitfall 4: Visibility State Confusion (public/protected/private)

**What goes wrong:**
Three visibility states are defined (public/protected/private), but implementation is inconsistent:
- One view shows "protected" as "not public" (actually includes private)
- Another view renders "protected" as "read-only for players" but edit button is still visible
- Permissions are checked at page load, but if player's team membership changes mid-session, list visibility doesn't update
- Coach creates a private list, assumes it's not visible to players, but a player who was already viewing the public version still sees the old cached version

**Why it happens:**
- Three states are more complex than two (simple public/private)
- No single source of truth for visibility logic; checks scattered across controllers
- "Protected" is a transitional state, easy to misinterpret (read-only for who?)
- No client-side refresh trigger when permissions change

**Consequences:**
- Player edits data on a list they shouldn't have access to
- Coach creates "private" list thinking it's hidden, but data is visible in other views
- Public list is changed to private, but browser cache still shows old version
- Visibility rules contradict across different pages (list view vs. detail view)

**Prevention:**
- Define visibility logic centrally: single function `can_view_list($user_id, $list_id, $role)` and `can_edit_list($user_id, $list_id, $role)`
- Use PostgreSQL views or explicit permission tables to enforce visibility; don't rely on session-based checks
- Document visibility rules in code comments:
  - `public`: players can read and edit own row; coaches can edit all
  - `protected`: players can read only; coaches can read/edit all
  - `private`: only coaches can see and edit
- Implement client-side refresh on role/team changes; invalidate list view cache
- Add server-side check on every row edit: verify user's current permission for that list before allowing update
- Log all visibility state changes (public → private, etc.) with user and timestamp

**Detection warning signs:**
- Player reports seeing data they shouldn't, or vice versa
- Visibility state doesn't match user's perceived access
- List edits succeed when they should be blocked
- Coaches report confusion about which players can see which lists

**Phase mapping:**
- **Phase 3 (List Management):** Centralize visibility logic; unit test all permission combos
- **Phase 4 (Player Features):** Implement client-side refresh on permission changes
- **Phase 5 (Admin Audit):** Track visibility state changes

---

### Pitfall 5: Row-Level Authorization Without Explicit Ownership Check

**What goes wrong:**
Requirement: "Players can only edit their own row." Implementation checks: `role == 'player'` and `list.status == 'public'`, but doesn't verify `row.player_id == $_SESSION['user_id']`. A player crafts a request with another player's row ID, their role check passes, visibility check passes, and they edit another player's data.

**Why it happens:**
- Assumption that role + list visibility = sufficient authorization
- No explicit ownership check on row operations
- REST endpoints lack user ID validation: `/api/row/123` should verify user owns row 123
- Testing only covers happy path (player editing own row)

**Consequences:**
- Player modifies another player's statistics
- Scores/data corruption across team
- Coach realizes too late, no audit trail of who changed what
- League standings become unreliable

**Prevention:**
- On every row edit, check: `row.player_id == $_SESSION['user_id']` (if player) or `list.team_id == $_SESSION['team_id']` (if coach/admin)
- Use parameterized queries to enforce this: `SELECT * FROM rows WHERE id = $1 AND (player_id = $2 OR $3 = 'coach')`
- Add explicit ownership validation before UPDATE/DELETE
- Log all row edits with user, timestamp, old values, new values
- Use row-level security (RLS) in PostgreSQL to enforce ownership at database level, not application level

**Detection warning signs:**
- Multiple players' data changes in same row
- Edit timestamps show player editing after they left the team
- Audit log shows data changes without corresponding user edit request

**Phase mapping:**
- **Phase 3 (List Management):** Implement row ownership checks on all edit endpoints
- **Phase 4 (Player Features):** Add explicit user ID validation in row operations
- **Phase 5 (Admin Audit):** Implement database-level RLS

---

### Pitfall 6: Dynamic Column Storage Without Proper Indexing

**What goes wrong:**
Coaches define many columns (10+), each coach in a team has different sets. Storage is flat: one `rows` table with all column values in separate columns or JSONB. Queries for "show me rows where column_X > 5" require table scans because there's no index on a dynamic column. Aggregation queries (sum all column_X across all rows) become slow as team size grows.

**Why it happens:**
- Column flexibility means you can't pre-define columns, so standard indexes don't apply
- JSONB stores all flexible data in one column; no index on individual fields
- Query builder doesn't automatically index dynamic columns
- Testing with small data (10 players) hides the problem; real teams have 20+ players × multiple lists

**Consequences:**
- Statistics page takes 2-3 seconds to load (unacceptable on mobile)
- List views with filters (e.g., "show players with >5 goals") are slow
- Admin operations (bulk exports, reports) timeout
- Database CPU spikes when generating aggregate reports

**Prevention:**
- If using JSONB for flexible columns, create indexes on frequently-filtered fields: `CREATE INDEX ON rows USING GIN (data)`
- Or: use separate `column_values` table: `id, row_id, column_id, value` with index on `column_id` and `row_id`
- Profile queries early: measure aggregate query times with realistic data (20+ players, 5+ columns)
- Cache aggregation results; recompute only when list/row data changes (invalidate on UPDATE/INSERT)
- Use PostgreSQL `EXPLAIN` to identify slow queries during development
- Pre-compute frequently-used stats (e.g., "total goals per player") in a denormalized `player_stats` table, updated on row change

**Detection warning signs:**
- Statistics page load time increases noticeably with more players
- Database logs show full table scans on aggregate queries
- Coaches complain about slow list loading
- Admin operations timeout

**Phase mapping:**
- **Phase 3 (List Management):** Design column storage schema; choose JSONB vs. separate table
- **Phase 4 (Statistics):** Profile aggregate queries; add indexes/caching as needed
- **Phase 5 (Performance Tuning):** Optimize slow queries; consider denormalization

---

## Moderate Pitfalls

### Pitfall 7: Config-Based Admin with Plaintext Credentials

**What goes wrong:**
Admin credentials are stored in PHP config file (e.g., `$admin_username`, `$admin_password` in plaintext). Config file is backed up, committed to Git, or leaked in logs. Attacker gains access to admin account and can create/delete teams, reset all passwords, access all data.

**Why it happens:**
- Single admin simplifies auth design
- Config file seems "secure" if it's on the server
- Developer doesn't think about backup/version control implications
- No formal credential rotation policy

**Consequences:**
- If Git repo leaks, admin credentials are exposed forever
- Backups must be carefully managed (encrypted separately)
- No audit trail of admin actions
- Admin password can't be rotated without code change + deploy

**Prevention:**
- Store admin credentials in environment variables or encrypted config file, not plaintext PHP
- Use a secure credential store (e.g., `.env` with encrypted secrets, AWS Secrets Manager, HashiCorp Vault)
- Hash admin password with bcrypt, not plaintext comparison
- Implement admin-specific audit logging: all admin actions (team creation, password reset, list deletion)
- Implement IP-based access control for admin panel: only allow certain IPs
- Implement rate limiting on admin login attempts
- Rotate admin password regularly (quarterly)

**Detection warning signs:**
- Admin credentials visible in server logs or error messages
- Config file in Git history
- No audit trail of who performed admin actions
- Unauthorized team/password changes without corresponding admin login

**Phase mapping:**
- **Phase 1 (Setup):** Use environment variables for admin credentials
- **Phase 5 (Admin):** Implement admin audit logging and rotation policy

---

### Pitfall 8: Statistics Aggregation Race Conditions

**What goes wrong:**
Coach A is editing a row (adding a goal), Coach B is running a statistics report. Row data is partially updated; statistics query counts the row twice (old value + new value), or misses it. Final stats show "15 goals" when actual total is "14 goals". Coaches can't rely on statistics for team rankings.

**Why it happens:**
- No locking on row updates during statistics aggregation
- Aggregation query runs concurrently with row edits
- No transaction isolation between row edit and stats fetch
- Stats are computed on demand; no caching or scheduled refresh

**Consequences:**
- Stats reports show inconsistent/incorrect totals
- Coaches distrust the statistics page and maintain spreadsheets instead
- League standings are unreliable
- Discrepancies are hard to trace (which row edit caused it?)

**Prevention:**
- Use PostgreSQL transactions with READ COMMITTED or SERIALIZABLE isolation
- For aggregation queries, use `SELECT ... FROM rows WHERE list_id = $1 FOR SHARE` to lock rows against concurrent edits
- Or: pre-compute stats on a schedule (e.g., every 5 minutes) rather than on-demand
- Denormalize stats into a `player_stats` table; update atomically when row data changes
- Implement optimistic locking: row has `version` column; update fails if version doesn't match
- Log all aggregation queries; compare results over time to detect anomalies

**Detection warning signs:**
- Coaches report stats don't match when they calculate by hand
- Same report run twice yields different totals
- Rows edited during peak hours show inconsistencies more often

**Phase mapping:**
- **Phase 4 (Statistics):** Implement transaction isolation on aggregate queries
- **Phase 5 (Performance):** Consider pre-computed stats or scheduled refresh

---

### Pitfall 9: Mobile-First Navigation Without Offline Awareness

**What goes wrong:**
Mobile users expect fast, snappy navigation. Coaches are on a stadium/field with poor connectivity. App loads a list, user navigates to another list, then network drops. Player can't edit their data because the form is blank (never loaded completely), but app doesn't communicate that the connection is lost. Coach force-refreshes, loses data.

**Why it happens:**
- Mobile-first design assumes always-on connection
- No indicator of connection status
- Form submission doesn't gracefully handle network errors
- No local caching of data on mobile
- Assumption that re-connecting = data is fresh, but server state may have changed

**Consequences:**
- Data entry frustration on mobile (forms that don't load)
- Coaches resort to desktop browser or spreadsheet for real work
- Lost edits if network drops mid-form-submit
- Sync conflicts if user edits, goes offline, comes back online with stale data

**Prevention:**
- Implement connection status indicator: show "offline", "connecting", or "connected" in UI
- Use service workers / local storage to cache list data and forms
- Implement optimistic updates: show form submission successful immediately, sync in background
- Queue failed requests and retry when connection restored
- Implement conflict detection: if server data changed while offline, warn user before overwriting
- Use `navigator.onLine` or WebSocket heartbeat to detect connection loss
- For critical edits (scores, statistics), require explicit save confirmation before sync
- Test on slow/unreliable networks (throttle to 3G speed)

**Detection warning signs:**
- Coaches complain about slow forms on mobile
- Edit data appears to submit but doesn't sync
- Network errors aren't communicated to user
- Coaches avoid mobile for data entry

**Phase mapping:**
- **Phase 2 (Auth & Forms):** Implement connection status indicator
- **Phase 3 (List Management):** Add local caching and optimistic updates
- **Phase 4 (Stability):** Implement retry/queue system and conflict detection

---

### Pitfall 10: Team Isolation Without Database-Level Enforcement

**What goes wrong:**
Requirement: "Each user belongs to exactly one team, can't see other teams." Implementation: `WHERE team_id = $_SESSION['team_id']` on all queries. But if session is tampered with (or cache is poisoned), user sees another team's data. A query forgets the `WHERE` clause, all teams' data is exposed.

**Why it happens:**
- Team isolation seems simple in application code
- Assumption that application layer is sufficient
- No database-level constraint to prevent data leakage
- Queries are written by multiple developers; one forgets the filter

**Consequences:**
- Accidental exposure of one team's data to another team
- Coach A sees Coach B's private lists / player data
- Statistics are calculated across all teams (not per-team)
- Sensitive data (player names, scores) leaks between teams

**Prevention:**
- Implement PostgreSQL RLS (Row-Level Security) at database level: `CREATE POLICY team_isolation ON lists USING (team_id = current_user_id)`
- RLS ensures that even if application layer forgets filter, database enforces it
- Store team_id on every table that has team-specific data (lists, rows, global columns)
- Every query must join or filter by team_id; use views to enforce this
- Audit logs should include team_id; review for cross-team queries
- Test with users from different teams; verify one team's data is never visible to another

**Detection warning signs:**
- Coach reports seeing another team's lists
- Query results include rows from multiple teams
- Aggregation queries don't respect team boundaries

**Phase mapping:**
- **Phase 2 (Database Setup):** Implement RLS on core tables
- **Phase 3 (Multi-Team Support):** Audit all queries for team_id filter
- **Phase 5 (Security):** Add automated tests for team isolation

---

### Pitfall 11: Insufficient Audit Logging for Coach Accountability

**What goes wrong:**
Coach A adds a player, Coach B edits that player's data, Coach A deletes the player. No audit trail of who did what, when. Team can't trace the history of changes. A player's score disappears; coach claims they never changed it.

**Why it happens:**
- Audit logging feels like "nice to have" feature
- No logging requirement in MVP
- Assumption that "recent activity" in UI is sufficient
- Coaches are trusted, so detailed tracking seems unnecessary

**Consequences:**
- No accountability for data changes
- Can't reconstruct state at a point in time
- Disputes about who changed what
- Can't detect malicious coach actions (data tampering, score inflation)

**Prevention:**
- Log all mutations: user, timestamp, table, action (INSERT/UPDATE/DELETE), old values, new values
- Use database triggers or application-level logging
- Implement audit table: `audit_log(id, user_id, table_name, action, old_data, new_data, created_at)`
- Coaches should be able to view audit logs for their team
- Implement retention policy: keep audit logs for 1+ years
- Flag unusual patterns (e.g., 50 edits by one coach in 1 minute)

**Detection warning signs:**
- Coaches dispute data changes
- Player data unexpectedly changes
- Can't reconstruct history of a player's scores

**Phase mapping:**
- **Phase 3 (Audit):** Implement basic audit logging for all mutations
- **Phase 5 (Accountability):** Add audit UI for coaches to review

---

## Minor Pitfalls

### Pitfall 12: Missing CSRF Protection on State-Changing Operations

**What goes wrong:**
Coach visits a malicious website while logged into Team Manager. That site silently submits a form that deletes a list or resets a player's password. No CSRF token on the form, browser includes coach's session cookie, request is valid from server's perspective.

**Why it happens:**
- Simple forms without CSRF tokens
- Assumption that "only coaches use this" = "doesn't need CSRF"
- GET requests for state changes (e.g., `?action=delete`) are even worse

**Prevention:**
- Implement CSRF tokens on all forms: `<input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">`
- Verify token on every POST/PUT/DELETE
- Use SameSite cookie attribute: `Set-Cookie: session=...; SameSite=Strict`
- Avoid GET for state changes; use POST/PUT/DELETE

**Detection warning signs:**
- Unexpected list/player deletions
- Coaches report data changes they didn't make
- Forms submitted without user interaction

**Phase mapping:**
- **Phase 2 (Auth):** Implement CSRF tokens on all forms

---

### Pitfall 13: SQL Injection Through Dynamic Column Filtering

**What goes wrong:**
Coach applies filter "show rows where column_X > 5". If column name comes from user input, attacker injects SQL: `column_X = 1 OR 1=1; DROP TABLE rows;`

**Why it happens:**
- Dynamic column names from user input
- Assumption that numeric filtering is safe
- String concatenation in SQL instead of parameterized queries

**Prevention:**
- Column names must be validated against whitelist; never interpolate user input directly into SQL
- Use parameterized queries for column values, but column identifiers must be validated/quoted
- Use PostgreSQL identifier quoting: `$1` for values, `IDENTIFIER` for column names (or dynamically build SQL safely)
- Test with malicious input: `column_X = 1 OR 1=1`, `column_X; DELETE`, etc.

**Detection warning signs:**
- Unexpected database changes after user applies filters
- Error messages reveal SQL queries
- Filters with special characters succeed (should fail validation)

**Phase mapping:**
- **Phase 3 (List Management):** Validate column names; use parameterized queries for values

---

### Pitfall 14: Unencrypted Password Storage or Weak Hashing

**What goes wrong:**
Passwords stored as plaintext or with weak hashing (MD5, SHA1). If database is leaked, all passwords are compromised immediately.

**Why it happens:**
- Quick implementation without security consideration
- Assumption that single admin = "doesn't need strong hashing"

**Prevention:**
- Use bcrypt with cost factor 12+, or argon2
- Never store plaintext passwords
- Hash before storing, even for temporary/auto-generated passwords

**Detection warning signs:**
- Passwords visible in database dumps
- Same password hash for different passwords (weak hash)

**Phase mapping:**
- **Phase 1 (Setup):** Use bcrypt/argon2 from day one

---

### Pitfall 15: Hardcoded Dates, Timezones, or Locales

**What goes wrong:**
App assumes all users are in Europe/Berlin timezone. A coach in London sees "16:00" but means "15:00". Aggregation stats are grouped by date, but "today" is different in each timezone.

**Why it happens:**
- German-only app assumes German users
- Timezone handling seems like a "later" problem
- PHP's `date()` defaults to server timezone

**Prevention:**
- Store all timestamps in UTC in database
- Convert to user's timezone only for display
- Use DateTime/DateTimeImmutable for timezone-aware handling
- Allow user to set/override timezone preference
- Test with multiple timezones

**Detection warning signs:**
- Coaches report off-by-one day in stats
- Timestamps shown in wrong timezone
- "Today's" stats don't match server's "today"

**Phase mapping:**
- **Phase 2 (Auth):** Store timezone preference with user
- **Phase 4 (Statistics):** Use UTC for all storage, convert on display

---

## Phase-Specific Warning Flags

| Phase Topic | Likely Pitfall | Mitigation Strategy |
|-------------|---|---|
| **Phase 1: Database Schema** | Schema doesn't support team isolation, column types not defined | Implement RLS, define column type constraints upfront |
| **Phase 2: Auth & RBAC** | Session statefulness, authorization checks scattered | Centralize permission logic, operation-level checks, rate limiting on resets |
| **Phase 3: List & Column Management** | Dynamic columns without validation, visibility state confusion | Enforce column types, centralize visibility logic, row-level ownership checks |
| **Phase 4: Statistics** | Race conditions, incorrect aggregation, no caching | Use transactions, pre-compute stats, implement locking |
| **Phase 5: Admin Tools** | Credential leakage, insufficient audit trail | Use env vars for secrets, implement comprehensive audit logging |
| **Mobile Responsiveness** | Offline awareness, slow forms on poor connectivity | Implement connection status, local caching, optimistic updates |
| **Security Hardening** | SQL injection in dynamic filters, CSRF, weak passwords | Whitelist column names, CSRF tokens, bcrypt/argon2 |

---

## Sources & Confidence

**Confidence: MEDIUM**

This research is based on:
1. Common pitfalls in PHP/PostgreSQL applications (from domain knowledge)
2. Team management systems specifically (role-based access, data sharing, multiple users)
3. Mobile-first constraints and no-email credential challenges
4. The specific project requirements (dynamic columns, three visibility states, single admin)

**Not verified against current PHP frameworks/standards** (no Context7 lookup available, no official PHP/PostgreSQL docs in this context). Recommendations follow established security practices (bcrypt, transaction isolation, RLS) rather than framework-specific guidance.

**Recommend validation in Phase-specific research:**
- PostgreSQL RLS implementation details (Phase 2)
- PHP session management best practices (Phase 2)
- Mobile-first form optimization libraries (Phase 3)
- Audit logging framework selection (Phase 5)
