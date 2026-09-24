---
phase: quick-260924-uyq
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - database/schema.sql
  - database/migrations/20260924_team_scoped_calendar_tokens.sql
  - src/db/connection.php
  - src/utils/helpers.php
  - src/ics_token_handler.php
  - src/ics_handler.php
  - src/coordinator/profile_handler.php
  - src/coordinator/calendar_reset_handler.php
  - src/templates/coordinator/profile.php
  - src/coordinator/lists_handler.php
  - src/templates/coordinator/lists.php
  - src/member/profile_handler.php
  - src/member/calendar_reset_handler.php
  - src/templates/member/profile.php
  - src/member/lists_handler.php
  - src/templates/member/lists.php
  - public/index.php
  - src/templates/admin/attributes.php
  - src/admin/attribute_edit_handler.php
  - src/templates/admin/attribute_edit.php
  - public/css/app.css
  - src/admin/team_create_handler.php
  - src/coordinator/list_column_create_handler.php
  - src/coordinator/list_create_handler.php
  - src/coordinator/list_detail_handler.php
  - src/coordinator/list_settings_handler.php
  - src/member/list_detail_handler.php
  - src/templates/coordinator/list_form.php
autonomous: true
requirements: [QUICK-260924-uyq]

must_haves:
  truths:
    - "GET /ics/{team_id}.ics (old public, unauthenticated route) returns 404 - the visibility-leak security hole is closed"
    - "A coordinator's profile page shows two calendar subscription links (coordinator feed + member feed), each with its own independent regenerate control"
    - "A member's profile page shows exactly one calendar subscription link (member feed), with no regenerate control"
    - "Visiting a valid /ics/{64-hex-token}.ics URL returns a calendar scoped correctly: coordinator token -> public+protected+private lists, protected+private events; member token -> public+protected lists, protected events only"
    - "Regenerating one team token immediately 404s the old link for that token, without affecting the other token"
    - "The app boots and serves every page with zero runtime migration overhead: get_db() no longer calls maybe_migrate_db(), and no DB_HAS_ constant is referenced anywhere in the codebase"
    - "A DBA can run one idempotent SQL script, once, against the production manager schema to add the new team token columns, backfill tokens for all existing teams, and drop the obsolete users.calendar_token column"
    - "All prior uncommitted work (per-user calendar-token feature + admin attribute-UI redesign) is preserved in git history as coherent, reviewable commits before the redesign begins"
  artifacts:
    - path: "src/ics_token_handler.php"
      provides: "Team-scoped ICS feed - looks up the token against both teams.calendar_token_* columns, serves lists/events scoped to whichever role matched"
      contains: "calendar_token_coordinator"
    - path: "src/coordinator/calendar_reset_handler.php"
      provides: "Regenerates either team token (token_type=coordinator|member) - coordinator-only, per locked decision"
      contains: "token_type"
    - path: "src/db/connection.php"
      provides: "db_init_schema() creates teams.calendar_token_coordinator/member and drops users.calendar_token from the inline DDL; maybe_migrate_db() and its call site are gone"
    - path: "database/schema.sql"
      provides: "Fresh-install schema (Docker/dev) matching connection.php's inline DDL - same two new team columns, no users.calendar_token"
    - path: "database/migrations/20260924_team_scoped_calendar_tokens.sql"
      provides: "One-time production migration - DDL + backfill + verification SELECT, run manually by the DBA before deploying"
      contains: "SET app.is_admin = true"
    - path: "src/templates/coordinator/profile.php"
      provides: "Two-token calendar card (coordinator + member feed), each independently regenerable"
    - path: "src/templates/member/profile.php"
      provides: "One-token calendar card (member feed only), read-only subscribe link, no regenerate control"
  key_links:
    - from: "src/ics_token_handler.php"
      to: "teams.calendar_token_coordinator / teams.calendar_token_member"
      via: "UNION lookup against both team token columns"
      pattern: "calendar_token_coordinator"
    - from: "public/index.php"
      to: "src/ics_token_handler.php"
      via: "GET /ics/([0-9a-f]{64}).ics route (unchanged pattern, re-keyed handler)"
      pattern: "ics_token_handler"
    - from: "src/coordinator/calendar_reset_handler.php"
      to: "teams table"
      via: "UPDATE teams SET (token_type-selected column) = ? WHERE id = ?"
      pattern: "UPDATE teams SET"
    - from: "src/admin/team_create_handler.php"
      to: "teams table"
      via: "INSERT includes freshly generated calendar_token_coordinator + calendar_token_member"
      pattern: "generate_calendar_token"
---

<objective>
Close the public ICS visibility leak, re-key the calendar-subscription feature from one token per user to two tokens per team (coordinator feed + member feed, coordinator-rotatable), and retire the runtime DB-migration machinery in favor of a single one-time production SQL script - after first committing the ~19 files of already-completed, uncommitted work sitting in the working tree.

Purpose: `src/ics_handler.php` is a live, unauthenticated, publicly routed endpoint that trusts a `role` query parameter to decide whether to return PRIVATE team events - trivially exploitable since team IDs are sequential integers (a request to `/ics/1.ics?role=coordinator` leaks private data to anyone). Separately, `maybe_migrate_db()` runs an information_schema lookup plus a full-table scan on every single request, forever, with no way to retire itself. Both get fixed here. The token redesign (1 token/user to 2 tokens/team) is a deliberate simplification: coordinators manage rotation for the whole team instead of per-member tokens that would otherwise multiply indefinitely as membership churns.

Known accepted tradeoff (per user decision, not a bug to fix later): because tokens are now shared per team rather than per user, revoking calendar access for one departing member requires the coordinator to rotate the whole team's token, which invalidates it for everyone else too - they must all re-subscribe. This is intentional and matches the user's own framing: "coordinators could update both tokens if they are public."

Output: Public ICS route removed. Two team-scoped ICS tokens (coordinator feed, member feed) wired end-to-end - schema, generation on team creation, lookup, regeneration, profile UI. maybe_migrate_db() deleted along with every DB_HAS_ guard it powered. A standalone, idempotent production migration script under database/migrations/. All prior uncommitted work committed first, in two coherent feature commits.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/UI-BASELINE.md
@src/db/connection.php
@src/ics_token_handler.php
@src/coordinator/profile_handler.php
@src/templates/coordinator/profile.php
@src/member/profile_handler.php
@src/templates/member/profile.php

<interfaces>
Current (pre-task) shape of src/db/connection.php's teams/users DDL inside db_init_schema() -
the exact block Task 3 edits. Only fresh installs (schema entirely absent) ever run this
function; an already-initialized Docker/dev DB is untouched by this change, consistent with
the existing project convention that schema is idempotent via IF NOT EXISTS and live DBs get
patched per-task.

  CREATE TABLE IF NOT EXISTS {$s}.teams (
      id         SERIAL PRIMARY KEY,
      name       VARCHAR(100) NOT NULL,
      is_active  BOOLEAN NOT NULL DEFAULT TRUE,
      sort_order INTEGER NOT NULL DEFAULT 0,
      logo_path  VARCHAR(500) NULL,
      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
  )

  CREATE TABLE IF NOT EXISTS {$s}.users (
      id             SERIAL PRIMARY KEY,
      ...
      calendar_token VARCHAR(64)  UNIQUE NULL,   -- DROP this line (Task 3)
      created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
  )

generate_calendar_token() already exists in src/utils/helpers.php - reuse as-is, it is
role-agnostic (just bin2hex(random_bytes(32))). No change needed to this function itself:

  function generate_calendar_token(): string {
      return bin2hex(random_bytes(32));
  }

set_team_context() signature (src/db/connection.php) - the user_id param is optional and
already defaults to null, which is exactly what a team-wide (not per-user) token needs:

  function set_team_context(PDO $pdo, int $team_id, ?string $role = null, ?int $user_id = null): void

database/schema.sql's users table currently has the same calendar_token column, and its
teams table currently has no calendar_token_* columns - Task 3 mirrors the same two edits
made to connection.php's inline DDL so Docker/dev and the PHP self-init stay identical.
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Commit all existing uncommitted work as two coherent feature commits</name>
  <files>database/schema.sql, src/db/connection.php, src/utils/helpers.php, src/ics_token_handler.php, src/coordinator/profile_handler.php, src/coordinator/calendar_reset_handler.php, src/templates/coordinator/profile.php, src/coordinator/lists_handler.php, src/templates/coordinator/lists.php, src/member/profile_handler.php, src/member/calendar_reset_handler.php, src/templates/member/profile.php, src/member/lists_handler.php, src/templates/member/lists.php, public/index.php, src/templates/admin/attributes.php, src/admin/attribute_edit_handler.php, src/templates/admin/attribute_edit.php, public/css/app.css</files>
  <action>
Before any redesign work, commit the working tree's existing state so it becomes a clean revert point and the subsequent redesign diff stays legible. Run `git status --porcelain` first to confirm the file set matches what is listed here: 14 modified, 5 untracked, 19 files total. Do NOT use `git add -A` or `git add .` - stage explicit paths only, in two commits, split by feature.

Commit 1 - per-user calendar-token feature (about to be re-keyed in later tasks, but commit the completed prior work first):

Stage exactly these paths: database/schema.sql, src/db/connection.php, src/utils/helpers.php, src/ics_token_handler.php, src/coordinator/profile_handler.php, src/coordinator/calendar_reset_handler.php, src/templates/coordinator/profile.php, src/coordinator/lists_handler.php, src/templates/coordinator/lists.php, src/member/profile_handler.php, src/member/calendar_reset_handler.php, src/templates/member/profile.php, src/member/lists_handler.php, src/templates/member/lists.php, public/index.php

Commit message (via heredoc):
"feat(calendar): per-user ICS token feed with profile subscribe/reset UI" as the summary line, followed by a blank line and: "Adds a token-authenticated calendar feed (/ics/{64-hex-token}.ics) as an alternative to the old public per-team route, plus subscribe/regenerate UI on both coordinator and member profile pages." then a blank line and the standard Co-Authored-By trailer for this session.

Note: public/index.php's current diff includes both the new calendar routes AND the new /admin/attributes/{group}/attributes/{id}/edit dedicated-edit-page route - a single file, two unrelated hunks. Since this whole file is staged in Commit 1, do not re-stage or re-diff it in Commit 2 - its attribute-edit routing change rides along with this commit. This is a deliberate, acceptable grouping tradeoff (whole-file granularity, not worth interactive hunk-splitting).

Commit 2 - admin member-attribute editor redesign:

Stage exactly these paths: src/templates/admin/attributes.php, src/admin/attribute_edit_handler.php, src/templates/admin/attribute_edit.php, public/css/app.css

Commit message summary line: "feat(admin): redesign member-attribute editor as expandable rows with dedicated edit page", then a blank line and: "Replaces the dense inline-edit table with collapsible list rows and a dedicated full-page editor per attribute, matching the app's card-based form pattern elsewhere. Includes a minor sticky-actionbar centering fix." then the same Co-Authored-By trailer.
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && test -z "$(git status --porcelain)" && [ "$(git log --oneline -2 | wc -l)" -eq 2 ] && echo CLEAN_AND_COMMITTED</automated>
  </verify>
  <done>git status --porcelain is empty; git log shows the two new commits on top of the prior HEAD; no git add -A or git add . was used.</done>
</task>

<task type="auto">
  <name>Task 2: Remove the old public, unauthenticated ICS route (security fix)</name>
  <files>src/ics_handler.php, public/index.php</files>
  <action>
Delete src/ics_handler.php entirely.

In public/index.php, remove the route block that dispatches bare numeric team IDs to it (currently directly above the token-authenticated route, roughly line 544-549):

  (bool)preg_match('#^/ics/(\d+)\.ics$#', $path, $matches)
      => (function() use ($matches): void {
          $_REQUEST['team_id'] = (int)$matches[1];
          require ROOT_PATH . '/src/ics_handler.php';
      })(),

Remove its preceding comment lines too (the "Public: ICS calendar export - No auth required" header). Leave the token route (matching 64 lowercase-hex-char tokens) and everything else in the file untouched - it stays at the same URL pattern and gets re-keyed in Task 4, not removed here.
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && test ! -f src/ics_handler.php && ! grep -q "ics_handler.php" public/index.php && ! grep -Eq "preg_match\('#\^/ics/\(\\\\d\+\)" public/index.php && php -l public/index.php && echo OLD_ROUTE_GONE</automated>
  </verify>
  <done>src/ics_handler.php no longer exists; public/index.php has no reference to it and no route matching bare numeric team IDs; php -l passes.</done>
</task>

<task type="auto">
  <name>Task 3: Team-scoped token schema and generation on team creation</name>
  <files>src/db/connection.php, database/schema.sql, src/admin/team_create_handler.php</files>
  <action>
1. In src/db/connection.php's db_init_schema(), the teams table CREATE statement: add two new UNIQUE-nullable columns directly after logo_path and before created_at:

  logo_path                  VARCHAR(500) NULL,
  calendar_token_coordinator VARCHAR(64)  UNIQUE NULL,
  calendar_token_member      VARCHAR(64)  UNIQUE NULL,
  created_at                 TIMESTAMPTZ NOT NULL DEFAULT NOW()

2. In the same function, the users table CREATE statement: remove the `calendar_token VARCHAR(64)  UNIQUE NULL,` line entirely (the per-user token column is being replaced by the two team-scoped columns above).

3. Still in db_init_schema(), remove this whole block (it lives after the member_id/club_id ALTER statements, near the files table): the `ALTER TABLE {$s}.users ADD COLUMN IF NOT EXISTS calendar_token VARCHAR(64) UNIQUE NULL` statement plus its immediately-following backfill loop (`SELECT id FROM {$s}.users WHERE calendar_token IS NULL` / foreach / UPDATE). Leave the member_id and club_id ALTER statements immediately above it untouched.

4. In database/schema.sql, make the mirror edits: add the same two columns to the teams table CREATE (same position, same types), and remove the `calendar_token VARCHAR(64) UNIQUE   NULL,` line from the users table CREATE.

5. In src/admin/team_create_handler.php, generate both tokens at team-creation time so every newly created team has working feeds immediately. Existing teams get backfilled by the production migration script in Task 7; for a local/Docker dev DB created before this change, a fresh docker volume (or a manual UPDATE) is the expected path - consistent with this project's existing "no migration files in repo" convention, and out of scope here. Change the INSERT from:

  $stmt = $pdo->prepare("INSERT INTO teams (name, sort_order) VALUES (?, ?)");
  $stmt->execute([$team_name, $sort_order]);

to:

  $stmt = $pdo->prepare(
      "INSERT INTO teams (name, sort_order, calendar_token_coordinator, calendar_token_member) VALUES (?, ?, ?, ?)"
  );
  $stmt->execute([$team_name, $sort_order, generate_calendar_token(), generate_calendar_token()]);
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && php -l src/db/connection.php && php -l src/admin/team_create_handler.php && grep -q "calendar_token_coordinator" src/db/connection.php && grep -q "calendar_token_coordinator" database/schema.sql && ! grep -q "calendar_token VARCHAR" src/db/connection.php && ! grep -q "calendar_token VARCHAR" database/schema.sql && grep -q "generate_calendar_token" src/admin/team_create_handler.php && echo SCHEMA_AND_GENERATION_OK</automated>
  </verify>
  <done>Both DDL files declare calendar_token_coordinator and calendar_token_member on teams and no longer declare calendar_token on users; team_create_handler.php generates and inserts both tokens for every new team; php -l passes on both PHP files.</done>
</task>


<task type="auto">
  <name>Task 4: Re-key the ICS feed handler and reset endpoints to the team-token model</name>
  <files>src/ics_token_handler.php, src/coordinator/calendar_reset_handler.php, src/member/calendar_reset_handler.php, public/index.php</files>
  <action>
Replace everything in src/ics_token_handler.php from the top of the file through the line `$is_coordinator = ($role === 'coordinator');` (i.e. the file header comment, token validation, DB lookup, context setup, and role flag) with the block below. Leave everything from the lists query onward (the rest of the current file: lists query, events query, ICS header output, the VCALENDAR/VEVENT rendering loop, the icon_emoji map) completely unchanged, EXCEPT for one line inside the `foreach ($lists as $list)` loop: change `$has_time = !empty($list['time_start']) && defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES;` to `$has_time = !empty($list['time_start']);` (this is the only DB_HAS_ reference left in the file once the header replacement below removes the other two).

Replacement header block:

  <?php
  // src/ics_token_handler.php - GET /ics/{token}.ics - Team-scoped ICS feed (coordinator or member token)
  // Token identifies a TEAM + ROLE scope (2 tokens per team, not per user). No session required.

  declare(strict_types=1);

  require_once ROOT_PATH . '/src/utils/calendar.php';

  $raw_token = $_REQUEST['cal_token'] ?? '';

  // Reject obviously invalid tokens early (must be 64 lowercase hex chars)
  if (!preg_match('/^[0-9a-f]{64}$/', $raw_token)) {
      http_response_code(404);
      exit;
  }

  $pdo = get_db();

  // Resolve team + role by token - admin context bypasses RLS (token lookup is credential verification)
  set_admin_context($pdo);
  $team_stmt = $pdo->prepare(
      "SELECT id, 'coordinator' AS role FROM teams WHERE calendar_token_coordinator = ? AND is_active = TRUE
       UNION ALL
       SELECT id, 'member' AS role FROM teams WHERE calendar_token_member = ? AND is_active = TRUE
       LIMIT 1"
  );
  $team_stmt->execute([$raw_token, $raw_token]);
  $cal_team = $team_stmt->fetch(PDO::FETCH_ASSOC);
  reset_rls_context($pdo);

  if (!$cal_team) {
      http_response_code(404);
      exit;
  }

  $team_id = (int)$cal_team['id'];
  $role    = $cal_team['role']; // 'coordinator' or 'member'

  // Set scoped context for data fetches - team-wide token, no individual user
  set_team_context($pdo, $team_id, $role);

  $is_coordinator = ($role === 'coordinator');

  // Lists: coordinators see all; members see public + protected only
  $vis_clause = $is_coordinator ? "visibility IN ('public','protected','private')" : "visibility IN ('public','protected')";
  $stmt = $pdo->prepare(
      "SELECT id, name, date, location, description, time_start, time_end
       FROM lists
       WHERE team_id = ? AND date IS NOT NULL AND {$vis_clause}
       ORDER BY date ASC"
  );
  $stmt->execute([$team_id]);
  $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Events: coordinators see protected + private; members see protected only
  $ev_vis = $is_coordinator ? "visibility IN ('protected','private')" : "visibility = 'protected'";
  $e_stmt = $pdo->prepare(
      "SELECT id, title, description, location, icon, date, is_all_day, time_start, time_end
       FROM events
       WHERE team_id = ? AND {$ev_vis} AND date IS NOT NULL
       ORDER BY date ASC"
  );
  $e_stmt->execute([$team_id]);
  $events = $e_stmt->fetchAll(PDO::FETCH_ASSOC);

  // ICS output - identical rendering to before
  header('Content-Type: text/calendar; charset=UTF-8');
  header('Content-Disposition: attachment; filename="team-' . $team_id . '.ics"');
  header('Cache-Control: no-cache, no-store, must-revalidate');

  $dtstamp  = gmdate('Ymd\THis\Z');
  $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $base_url = $scheme . '://' . $host;
  $role_path = $is_coordinator ? 'coordinator' : 'member';

This replacement removes the old per-user lookup (previously against users.calendar_token) and the separate "verify team is active" step - both are now folded into the single UNION lookup above, since the team-active check and the token-role resolution happen in the same query.

Note the removed variable $cal_user - all downstream code already only ever referenced $team_id, $role and $is_coordinator (never $cal_user directly beyond that point), so nothing past this header block needs further edits except the one $has_time line called out above.
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && php -l src/ics_token_handler.php && grep -q "calendar_token_coordinator" src/ics_token_handler.php && grep -q "calendar_token_member" src/ics_token_handler.php && ! grep -q "DB_HAS_" src/ics_token_handler.php && ! grep -q "u.calendar_token" src/ics_token_handler.php && echo ICS_TOKEN_HANDLER_REKEYED</automated>
  </verify>
  <done>src/ics_token_handler.php resolves tokens against both team columns in one query, sets team-wide (not per-user) RLS context, and contains no DB_HAS_ or per-user calendar_token references.</done>
</task>

<task type="auto">
  <name>Task 4b: Re-key calendar reset - coordinator can rotate either token, members cannot rotate</name>
  <files>src/coordinator/calendar_reset_handler.php, src/member/calendar_reset_handler.php, public/index.php</files>
  <action>
Replace the entire content of src/coordinator/calendar_reset_handler.php with:

  <?php
  // src/coordinator/calendar_reset_handler.php - POST /coordinator/calendar-reset
  // Regenerates one of the team's two calendar tokens (coordinator or member feed).
  // Coordinators may rotate EITHER token - if a link leaks, they replace it (members cannot).

  declare(strict_types=1);

  require_coordinator();

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      redirect('/coordinator/profile');
  }

  require_csrf();

  $pdo        = get_db();
  $team_id    = (int)$_SESSION['team_id'];
  $user_id    = (int)$_SESSION['user_id'];
  $token_type = ($_POST['token_type'] ?? '') === 'member' ? 'member' : 'coordinator';
  $column     = $token_type === 'member' ? 'calendar_token_member' : 'calendar_token_coordinator';
  $token      = generate_calendar_token();

  set_admin_context($pdo);
  $pdo->prepare("UPDATE teams SET {$column} = ? WHERE id = ?")
      ->execute([$token, $team_id]);
  reset_rls_context($pdo);
  set_team_context($pdo, $team_id, 'coordinator', $user_id);

  redirect('/coordinator/profile?cal_reset=' . $token_type);

Note: $column is restricted to one of exactly two hardcoded literal strings by the ternary above before it is ever interpolated into SQL - it is never derived from unsanitized user input, so this interpolation is safe (same pattern already used elsewhere in this codebase for schema-name interpolation).

Delete src/member/calendar_reset_handler.php entirely - per the locked decision, members can view and subscribe to the member feed but cannot regenerate it, so this endpoint no longer exists.

In public/index.php, remove this route block (it sits directly below the coordinator calendar-reset route):

  // Calendar token reset - member
  $path === '/member/calendar-reset'
      => require ROOT_PATH . '/src/member/calendar_reset_handler.php',

Leave the coordinator calendar-reset route immediately above it untouched (same path, same handler file, only the handler's internals changed above).
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && php -l src/coordinator/calendar_reset_handler.php && php -l public/index.php && test ! -f src/member/calendar_reset_handler.php && grep -q "token_type" src/coordinator/calendar_reset_handler.php && ! grep -q "member/calendar_reset_handler" public/index.php && ! grep -q "member/calendar-reset" public/index.php && echo RESET_ENDPOINTS_REKEYED</automated>
  </verify>
  <done>Coordinator can regenerate either team token via a token_type POST field; the member reset handler and its route are both gone.</done>
</task>

<task type="auto">
  <name>Task 5: Wire coordinator and member profile UI to the two-token / one-token model</name>
  <files>src/coordinator/profile_handler.php, src/templates/coordinator/profile.php, src/member/profile_handler.php, src/templates/member/profile.php</files>
  <action>
In src/coordinator/profile_handler.php, change the self-lookup query from selecting u.calendar_token to joining teams and pulling both team-scoped columns:

  $stmt = $pdo->prepare(
      "SELECT u.member_id, u.confirmed_at,
              t.calendar_token_coordinator, t.calendar_token_member,
              p.first_name, p.last_name, p.email, p.phone
       FROM users u
       JOIN members p ON p.id = u.member_id
       JOIN teams t ON t.id = u.team_id
       WHERE u.id = ?"
  );
  $stmt->execute([$user_id]);
  $self = $stmt->fetch();
  $player_id                  = (int)$self['member_id'];
  $calendar_token_coordinator = $self['calendar_token_coordinator'] ?? null;
  $calendar_token_member      = $self['calendar_token_member']      ?? null;

Update the render_coach_page(...) closure's `use (...)` list: replace `$calendar_token` with `$calendar_token_coordinator, $calendar_token_member`.

In src/templates/coordinator/profile.php, replace the single `$ics_url` line with two:

  $ics_url_coordinator = $calendar_token_coordinator ? ($scheme . '://' . $host . '/ics/' . $calendar_token_coordinator . '.ics') : null;
  $ics_url_member      = $calendar_token_member      ? ($scheme . '://' . $host . '/ics/' . $calendar_token_member      . '.ics') : null;

Replace the single "Kalender-Abo" card (the `<div class="card mt-4">...</div>` block inside the existing `<?php if (!$is_confirm_route): ?> ... <?php endif; ?>` wrapper) with TWO cards, stacked with `mt-4` spacing, reusing the exact existing markup pattern (input-group with a readonly text input, a `btn-outline-secondary` copy-to-clipboard button using the same `navigator.clipboard.writeText(...)` pattern, a `btn-outline-primary min-touch` "In Kalender-App öffnen" link, and a `btn-outline-danger min-touch` "Link erneuern" reset form with the same `confirm(...)` guard) - each card re-keyed as follows:

Card 1 (coordinator feed): heading "Kalender-Abo (Koordinator)"; body text explains this feed includes all visible termine including private entries; uses $ics_url_coordinator; input id `ics-url-coord`; reset form action `/coordinator/calendar-reset` with a hidden `<input type="hidden" name="token_type" value="coordinator">`; the existing `?cal_reset=1` success-flash check becomes `($_GET['cal_reset'] ?? '') === 'coordinator'`.

Card 2 (member feed): heading "Kalender-Abo (Mitglieder)"; body text explains this is the link to share with team members (public + protected termine only); uses $ics_url_member; input id `ics-url-member-feed` (must differ from Card 1's id); reset form action `/coordinator/calendar-reset` with a hidden `<input type="hidden" name="token_type" value="member">`; success-flash check becomes `($_GET['cal_reset'] ?? '') === 'member'`.

Both cards keep the `min-touch` classes and the copy-button JS pattern exactly as it exists today, just re-keyed to each card's own input id.

In src/member/profile_handler.php, change the token lookup:

  $link_stmt = $pdo->prepare(
      "SELECT u.member_id, t.calendar_token_member
       FROM users u JOIN teams t ON t.id = u.team_id
       WHERE u.id = ?"
  );
  $link_stmt->execute([$user_id]);
  $link_row       = $link_stmt->fetch(PDO::FETCH_ASSOC);
  $player_id      = (int)($link_row['member_id'] ?? 0);
  $calendar_token = $link_row['calendar_token_member'] ?? null;

The variable name $calendar_token stays as-is - the member template only ever needs one token, so nothing downstream needs renaming.

In src/templates/member/profile.php, the $ics_url computation stays unchanged (still built from $calendar_token). Remove ONLY the regenerate control: delete the `<form method="POST" action="/member/calendar-reset" ...>...Link erneuern...</form>` block entirely, and delete the `<?php if (!empty($_GET['cal_reset'])): ?>...<?php endif; ?>` success-flash block tied to that form (there is no longer a member-triggered reset action to confirm). Keep the input-group with copy button and the "In Kalender-App öffnen" link exactly as they are - members can still view and subscribe, they just cannot regenerate.
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && php -l src/coordinator/profile_handler.php && php -l src/member/profile_handler.php && grep -q "calendar_token_coordinator" src/coordinator/profile_handler.php src/templates/coordinator/profile.php && grep -q "calendar_token_member" src/member/profile_handler.php src/templates/member/profile.php && [ "$(grep -c "token_type" src/templates/coordinator/profile.php)" -ge 2 ] && ! grep -q "member/calendar-reset" src/templates/member/profile.php && echo PROFILE_UI_WIRED</automated>
  </verify>
  <done>Coordinator profile shows two independently-regenerable feed links; member profile shows one read-only feed link with no reset control.</done>
</task>

<task type="auto">
  <name>Task 6: Remove maybe_migrate_db() and collapse every DB_HAS_ guard it powered</name>
  <files>src/db/connection.php, src/coordinator/list_column_create_handler.php, src/coordinator/list_create_handler.php, src/coordinator/list_detail_handler.php, src/coordinator/list_settings_handler.php, src/coordinator/lists_handler.php, src/member/list_detail_handler.php, src/member/lists_handler.php, src/templates/coordinator/list_form.php</files>
  <action>
This task removes the migration-guard machinery in one atomic step - partial removal would break the app immediately, since any surviving `defined('DB_HAS_X')` check would silently evaluate false once maybe_migrate_db() (the only place these constants are ever defined) is deleted, silently dropping list times, events, files, and coach-only columns from the UI with no error.

Note on DB_HAS_LIST_TYPE: this constant is referenced in two of the files below but is never defined anywhere in the codebase (confirmed by a repo-wide grep before this plan was written) - it is unrelated to maybe_migrate_db() and already permanently evaluates to "not defined". Collapsing its guards must preserve that existing, currently-always-taken behavior exactly - do NOT flip it to the "true" branch, that would be an unrelated behavior change outside this task's scope. Only delete the dead branch and the guard, keeping the code path that already always runs today.

1. src/db/connection.php: in get_db(), delete the line `maybe_migrate_db($pdo);` (keep `maybe_init_db($pdo);` immediately above it, unchanged). Delete the entire `function maybe_migrate_db(PDO $pdo): void { ... }` function, including its preceding docblock comment documenting migrations 001-032 - that history now lives in git log and STATE.md, not in a comment attached to a deleted function.

2. src/coordinator/list_column_create_handler.php (around line 40): collapse the `if (DB_HAS_COACH_ONLY) { ... } else { ... }` block to just the `if` body, unconditionally - delete the `if`/`else` wrapper and the else branch entirely, keeping only the INSERT that includes the coach_only column.

3. src/coordinator/list_create_handler.php: delete the `if (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) {` wrapper around the $time_start/$time_end parsing block (around line 55) - keep the body, drop the guard and its closing brace so parsing always runs. Then replace the entire dynamic column-building block (the $cols/$vals/$params assignment, the DB_HAS_LIST_TYPE re-assignment, and the DB_HAS_LIST_TIMES append, roughly lines 74-101) with this single unconditional block, which reproduces exactly what those guards currently always resolve to - list_type omitted (pre-existing/out of scope, see note above), time columns always included:

  // list_type intentionally omitted from this INSERT - pre-existing behavior,
  // unrelated to this migration-guard cleanup (DB_HAS_LIST_TYPE was never defined).
  $cols = "team_id, name, visibility, show_all_rows, date, description, location, time_start, time_end";
  $vals = "?, ?, ?, ?, ?, ?, ?, ?, ?";
  $params = [
      $_SESSION['team_id'], $name, $visibility, $show_all_rows,
      $date !== '' ? $date : null,
      $description !== '' ? $description : null,
      $location !== '' ? $location : null,
      $time_start !== '' ? $time_start : null,
      $time_end   !== '' ? $time_end   : null,
  ];

4. src/coordinator/list_detail_handler.php: line 22 `$list_type_col = (defined('DB_HAS_LIST_TYPE') && DB_HAS_LIST_TYPE) ? ", list_type" : "";` becomes `$list_type_col = "";` (preserves the current always-false behavior, see note above). Line 23 `$list_time_cols = (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) ? ", time_start, time_end" : "";` becomes `$list_time_cols = ", time_start, time_end";`. Both occurrences (lines 60 and 74) of `(DB_HAS_COACH_ONLY ? 'c.coach_only' : 'FALSE AS coach_only')` become simply `'c.coach_only'`.

5. src/coordinator/list_settings_handler.php: line 13 `$time_cols = (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) ? ', time_start, time_end' : '';` becomes `$time_cols = ', time_start, time_end';`. Around line 195, delete the `if (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) {` wrapper around the $new_time_start/$new_time_end parsing block - keep the body, drop guard and closing brace. Around lines 214-240, the `if (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) { UPDATE ... time_start, time_end ... } else { UPDATE ... without time cols ... }` - delete the else branch entirely, keep only the WITH-time-columns UPDATE statement, unconditionally. Around line 314 (inline in the HTML), `<?php if (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES): ?>` ... `<?php endif; ?>` (the Uhrzeit fieldset) - remove both the if and its matching endif, keep the HTML between them.

6. src/coordinator/lists_handler.php: line 10 `$time_col = (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) ? 'time_start, time_end' : 'NULL AS time_start, NULL AS time_end';` becomes `$time_col = 'time_start, time_end';`. Around line 21, remove the `if (defined('DB_HAS_FILES') && DB_HAS_FILES) { ... }` guard around the files query block - keep the body unconditional. Around line 33, remove the `if (defined('DB_HAS_EVENTS') && DB_HAS_EVENTS) { ... }` guard around the events query block - same treatment.

7. src/member/list_detail_handler.php: line 22 `$list_time_cols = (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES) ? ", time_start, time_end" : "";` becomes `$list_time_cols = ", time_start, time_end";`. Line 33 `$local_filter = DB_HAS_COACH_ONLY ? '(c.list_id = ? AND c.coach_only = FALSE)' : 'c.list_id = ?';` becomes `$local_filter = '(c.list_id = ? AND c.coach_only = FALSE)';`.

8. src/member/lists_handler.php: line 10, same pattern as coordinator's, becomes `$time_col = 'time_start, time_end';`. Around line 21, remove the `if (defined('DB_HAS_FILES') && DB_HAS_FILES) { ... }` guard around the files query, keep body unconditional.

9. src/templates/coordinator/list_form.php: around line 38, `<?php if (defined('DB_HAS_LIST_TIMES') && DB_HAS_LIST_TIMES): ?>` ... `<?php endif; ?>` (the Uhrzeit fieldset, closing around line 56) - remove both, keep the HTML between them.
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && for f in src/db/connection.php src/coordinator/list_column_create_handler.php src/coordinator/list_create_handler.php src/coordinator/list_detail_handler.php src/coordinator/list_settings_handler.php src/coordinator/lists_handler.php src/member/list_detail_handler.php src/member/lists_handler.php src/templates/coordinator/list_form.php; do php -l "$f" || exit 1; done && ! grep -rn "DB_HAS_" src/ public/ && ! grep -q "maybe_migrate_db" src/db/connection.php && echo NO_DB_HAS_SURVIVES</automated>
  </verify>
  <done>No DB_HAS_ reference remains anywhere in src/ or public/; maybe_migrate_db() and its call site are gone; every previously-guarded code path now runs unconditionally (or, for the two dead DB_HAS_LIST_TYPE spots, keeps its pre-existing always-taken branch); php -l passes on all 9 files.</done>
</task>

<task type="auto">
  <name>Task 7: Production migration script and final full-repo verification sweep</name>
  <files>database/migrations/20260924_team_scoped_calendar_tokens.sql</files>
  <action>
Create the directory database/migrations/ and write database/migrations/20260924_team_scoped_calendar_tokens.sql with the following content. This is a ONE-TIME script the user runs manually against the PRODUCTION `manager` schema (not `team_manager`, which is Docker/dev only) via psql, as the schema/table owner, before deploying the version of the app produced by this plan.

  -- database/migrations/20260924_team_scoped_calendar_tokens.sql
  -- Team Manager - one-time production migration
  -- Run manually against the PRODUCTION schema "manager" (NOT "team_manager" - that is Docker/dev
  -- only) via psql, as the schema/table OWNER, BEFORE deploying the app version that removes
  -- maybe_migrate_db() and switches ICS tokens from per-user to per-team (2 tokens per team).
  --
  -- Sections are labeled by the privilege they need:
  --   [DDL - needs table OWNER]   ALTER/DROP statements - run as the Postgres role that owns
  --                               the "manager" schema (typically the role from DB_USER).
  --   [DML - needs is_admin GUC] UPDATE/SELECT backfill - every table has RLS policies checking
  --                               current_setting('app.is_admin', true) = 'true'; without the
  --                               SET below, UPDATE silently affects 0 rows (confirmed the hard
  --                               way on 2026-08-25 - see STATE.md).
  --
  -- Idempotent throughout - safe to re-run.
  --
  -- Usage: psql -h <host> -U <owner> -d <database> -f database/migrations/20260924_team_scoped_calendar_tokens.sql

  SET search_path TO manager, public;

  -- Required for the DML sections below to actually affect rows under RLS.
  SET app.is_admin = true;

  -- [DDL] New team-scoped calendar token columns
  ALTER TABLE manager.teams
      ADD COLUMN IF NOT EXISTS calendar_token_coordinator VARCHAR(64) UNIQUE NULL;
  ALTER TABLE manager.teams
      ADD COLUMN IF NOT EXISTS calendar_token_member      VARCHAR(64) UNIQUE NULL;

  -- [DDL] Safety net - columns previously flagged as "apply manually if needed" in
  -- connection.php (pre-this-migration comment). Idempotent, so including them is free;
  -- production state for these two was unconfirmed before this script.
  ALTER TABLE manager.teams
      ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) NULL;
  ALTER TABLE manager.users
      ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL;

  -- [DML] Backfill both tokens for every existing team.
  -- gen_random_uuid() is built into PostgreSQL 13+ - pgcrypto is NOT required.
  -- Two UUIDs, hyphens stripped and concatenated, give 64 hex chars of CSPRNG output.
  UPDATE manager.teams
  SET calendar_token_coordinator = replace(gen_random_uuid()::text, '-', '') || replace(gen_random_uuid()::text, '-', '')
  WHERE calendar_token_coordinator IS NULL;

  UPDATE manager.teams
  SET calendar_token_member = replace(gen_random_uuid()::text, '-', '') || replace(gen_random_uuid()::text, '-', '')
  WHERE calendar_token_member IS NULL;

  -- Fallback only (commented out) - if gen_random_uuid() is somehow unavailable on your PG
  -- version, this needs superuser to CREATE EXTENSION; avoid unless the above fails:
  -- CREATE EXTENSION IF NOT EXISTS pgcrypto;
  -- UPDATE manager.teams SET calendar_token_coordinator = encode(gen_random_bytes(32), 'hex') WHERE calendar_token_coordinator IS NULL;
  -- UPDATE manager.teams SET calendar_token_member      = encode(gen_random_bytes(32), 'hex') WHERE calendar_token_member      IS NULL;

  -- [DDL] Drop the obsolete per-user token column - replaced by the two team-scoped columns
  -- above. May not exist in production if it was never deployed there; IF EXISTS makes this
  -- safe either way.
  ALTER TABLE manager.users
      DROP COLUMN IF EXISTS calendar_token;

  -- Verification - eyeball every team has both tokens, and the old column is gone.
  SELECT id, name,
         (calendar_token_coordinator IS NOT NULL) AS has_coordinator_token,
         (calendar_token_member      IS NOT NULL) AS has_member_token
  FROM manager.teams
  ORDER BY id;

  SELECT column_name FROM information_schema.columns
  WHERE table_schema = 'manager' AND table_name = 'users' AND column_name = 'calendar_token';
  -- Expect ZERO rows from the query above - confirms the old column is gone.

After writing the script, run the final full-repo verification sweep covering every file this entire plan touched - see the automated verification command below, which runs php -l on every PHP file modified across Tasks 1-6, then runs the three grep checks required by this plan: no DB_HAS_ reference anywhere, src/ics_handler.php gone with no route referencing it, and no surviving users.calendar_token reference anywhere outside this new migration script's own DROP COLUMN statement (which legitimately names it in order to remove it).
  </action>
  <verify>
    <automated>cd /Users/sebastianwiller/Documents/github/team-manager && test -f database/migrations/20260924_team_scoped_calendar_tokens.sql && grep -q "SET app.is_admin = true" database/migrations/20260924_team_scoped_calendar_tokens.sql && for f in src/db/connection.php src/admin/team_create_handler.php src/ics_token_handler.php src/coordinator/calendar_reset_handler.php src/coordinator/profile_handler.php src/templates/coordinator/profile.php src/member/profile_handler.php src/templates/member/profile.php src/coordinator/list_column_create_handler.php src/coordinator/list_create_handler.php src/coordinator/list_detail_handler.php src/coordinator/list_settings_handler.php src/coordinator/lists_handler.php src/member/list_detail_handler.php src/member/lists_handler.php src/templates/coordinator/list_form.php public/index.php src/templates/admin/attributes.php src/admin/attribute_edit_handler.php src/templates/admin/attribute_edit.php; do php -l "$f" || exit 1; done && ! grep -rn "DB_HAS_" src/ public/ && test ! -f src/ics_handler.php && ! grep -q "ics_handler.php" public/index.php && ! grep -rn "calendar_token" src/ database/schema.sql | grep -v "_coordinator\|_member\|generate_calendar_token" && echo FULL_SWEEP_CLEAN</automated>
  </verify>
  <done>database/migrations/20260924_team_scoped_calendar_tokens.sql exists, is idempotent, and opens with SET app.is_admin = true; every touched PHP file lints clean; no DB_HAS_ reference, no old public ICS handler/route, and no surviving per-user calendar_token reference exist anywhere in src/ or database/schema.sql.</done>
</task>

</tasks>

<verification>
- php -l passes on every PHP file this plan touched (listed in Task 7's verify command)
- No DB_HAS_ reference survives anywhere under src/ or public/
- src/ics_handler.php does not exist; public/index.php has no route referencing it
- No reference to a per-user users.calendar_token column survives anywhere under src/ or database/schema.sql (the migration script's DROP COLUMN IF EXISTS calendar_token is the sole legitimate exception, and it removes the column rather than reading it)
- src/db/connection.php contains no maybe_migrate_db function and get_db() does not call it
- database/schema.sql and connection.php's inline db_init_schema() both declare teams.calendar_token_coordinator and teams.calendar_token_member, and neither declares users.calendar_token
- database/migrations/20260924_team_scoped_calendar_tokens.sql exists, targets the manager schema explicitly, opens with SET app.is_admin = true, and every DDL/DML statement is idempotent (IF NOT EXISTS / IF EXISTS / WHERE ... IS NULL)
- git log shows the two Task-1 commits followed by one commit per subsequent task, with a clean git status at every step
</verification>

<success_criteria>
- The public, unauthenticated /ics/{team_id}.ics route and its handler no longer exist - the private-event visibility leak is closed
- Every team has exactly two ICS tokens (coordinator feed, member feed); coordinators can independently regenerate either one; members can view and subscribe to the member feed only, with no regenerate control
- get_db() no longer runs any per-request migration check; DB_HAS_ constants and every guard built on them are gone from the codebase
- A single idempotent SQL script exists under database/migrations/ for the user to run once, manually, against production before deploying this change
- All prior uncommitted work (calendar-token feature + admin attribute-UI redesign) is preserved in git history as two coherent commits, created before any of the redesign work began
- Known, accepted tradeoff (team-wide token rotation affects all subscribers, not just one departing member) is documented in this plan's objective, not silently introduced
</success_criteria>

<output>
After completion, create `.planning/quick/260924-uyq-team-scoped-ics-tokens-remove-old-route-/260924-uyq-SUMMARY.md`
</output>
