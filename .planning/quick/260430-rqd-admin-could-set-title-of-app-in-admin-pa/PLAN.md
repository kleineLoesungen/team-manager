---
task_id: 260430-rqd
type: execute
wave: 1
depends_on: []
autonomous: true
files_modified:
  - database/schema.sql
  - public/index.php
  - src/admin/admin_settings_handler.php
  - src/templates/admin/layout.php
  - src/templates/layout.php

must_haves:
  truths:
    - "Admin sees a 'Einstellungen' nav item in the admin sidebar and mobile tabs"
    - "Admin can change the app title via a form at /admin/settings and is redirected back on success"
    - "Coach navbar shows '{app_title} · {team_name}' using the DB-stored title"
    - "Player navbar shows '{app_title} · {team_name}' using the DB-stored title"
    - "Admin navbar continues to show the raw app title (no team suffix)"
    - "Changing the title in the admin panel is immediately reflected for coaches and players on next request"
  artifacts:
    - path: "database/schema.sql"
      provides: "settings table DDL + seed row for app_title"
      contains: "CREATE TABLE IF NOT EXISTS settings"
    - path: "src/admin/admin_settings_handler.php"
      provides: "GET form + POST update for app_title"
      exports: []
    - path: "src/templates/layout.php"
      provides: "render_navbar() that reads app_title from DB for coach/player"
      contains: "get_setting"
  key_links:
    - from: "src/admin/admin_settings_handler.php"
      to: "settings table"
      via: "UPDATE settings SET value = ? WHERE key = 'app_title'"
    - from: "src/templates/layout.php render_navbar()"
      to: "settings table"
      via: "get_setting('app_title') helper querying settings table"
    - from: "src/templates/layout.php render_navbar()"
      to: "$_SESSION['team_name']"
      via: "session lookup for team_name suffix in coach/player context"
---

<objective>
Add a runtime-configurable app title. The admin sets it in a new "Einstellungen" page under /admin/settings. Coaches and players see the title in their navbar as "{app_title} · {team_name}". The admin navbar shows just the app title.

Purpose: Allows branding without code changes or deployments.
Output: settings table in DB, admin settings page, updated navbar in all three layouts.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
</execution_context>

<context>
@database/schema.sql
@public/index.php
@src/templates/layout.php
@src/templates/admin/layout.php
@src/templates/coach/layout.php
@src/templates/player/layout.php
@src/admin/coaches_handler.php
@src/auth/session.php
@src/db/connection.php
@src/utils/csrf.php

<interfaces>
<!-- Key patterns in this codebase. Executor must follow these exactly. -->

DB access:
```php
$pdo = get_db();  // returns PDO, sets search_path to team_manager schema
```

Auth guards (call at top of every handler):
```php
require_admin();   // sets admin RLS context; redirects non-admin to /login
require_coach();   // sets team RLS context; redirects non-coach to /login
require_player();  // sets team RLS context; redirects non-player to /login
```

CSRF (POST handlers):
```php
require_csrf();   // call before processing POST; dies with 403 on mismatch
csrf_field()      // returns hidden input HTML string; echo inside <form>
```

Layout renderers:
```php
render_admin_page(string $title, string $active, callable $body): void
render_coach_page(string $title, string $active, callable $body): void
render_player_page(string $title, string $active, callable $body): void
// All call render_navbar() internally via render_layout_head() → render_navbar()
```

Output escaping:
```php
e(mixed $value): string  // htmlspecialchars wrapper — always use for user data
```

Redirect:
```php
redirect(string $url): never
```

Session keys (coach/player after login):
- $_SESSION['team_id']   — int
- $_SESSION['team_name'] — string  (set at login)
- $_SESSION['role']      — 'coach' | 'player'
- $_SESSION['display_name'] — string
- $_SESSION['is_admin']  — bool (admin only)
</interfaces>
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add settings table to schema and implement get_setting() helper</name>
  <files>
    database/schema.sql
    src/db/connection.php
  </files>
  <action>
**database/schema.sql** — append at the end of the file (after the cells table block):

```sql
-- ── App Settings ─────────────────────────────────────────────────────────────

-- Key-value store for runtime configuration (e.g. app_title)
CREATE TABLE IF NOT EXISTS settings (
    key   VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);

INSERT INTO settings (key, value)
VALUES ('app_title', 'Team Manager')
ON CONFLICT (key) DO NOTHING;
```

**src/db/connection.php** — add `get_setting()` function at the end of the file:

```php
/**
 * Read a single setting value from the settings table.
 * Returns $default if the key does not exist.
 * Uses the admin bypass when called from admin context; for coach/player
 * context RLS must permit SELECT on settings (settings has no RLS — it is
 * a public read table restricted only by schema isolation).
 */
function get_setting(string $key, string $default = ''): string {
    $pdo  = get_db();
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row !== false ? (string)$row['value'] : $default;
}
```

No RLS policy is needed on `settings` — the table is read-only for non-admin roles and the schema isolation (search_path = team_manager) is sufficient. The admin handler will use a direct UPDATE (not RLS-gated).
  </action>
  <verify>
    <automated>psql "$DATABASE_URL" -c "\d team_manager.settings" 2>/dev/null || echo "Run schema manually: psql ... -f database/schema.sql; then verify table exists"</automated>
  </verify>
  <done>
    - `settings` table exists in team_manager schema with (key VARCHAR PK, value TEXT)
    - Seed row `('app_title', 'Team Manager')` present
    - `get_setting(string $key, string $default): string` exported from src/db/connection.php
  </done>
</task>

<task type="auto">
  <name>Task 2: Admin settings page — GET form + POST handler + routing</name>
  <files>
    src/admin/admin_settings_handler.php
    src/templates/admin/layout.php
    public/index.php
  </files>
  <action>
**src/admin/admin_settings_handler.php** — create new file following the coaches_handler.php pattern:

```php
<?php
// src/admin/admin_settings_handler.php — Admin app settings page

declare(strict_types=1);

require_admin();

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $new_title = trim($_POST['app_title'] ?? '');
    if ($new_title === '') {
        $new_title = 'Team Manager';
    }
    // Clamp to 100 chars
    $new_title = mb_substr($new_title, 0, 100);

    $stmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = 'app_title'");
    $stmt->execute([$new_title]);

    redirect('/admin/settings?success=1');
}

$current_title = get_setting('app_title', 'Team Manager');
$success       = !empty($_GET['success']);

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Einstellungen', 'settings', function() use ($current_title, $success) {
    if ($success) {
        echo '<div class="alert alert-success">Einstellungen gespeichert.</div>';
    }
    ?>
    <form method="POST" action="/admin/settings" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="app_title" class="form-label fw-semibold">App-Titel</label>
            <input type="text"
                   id="app_title"
                   name="app_title"
                   class="form-control"
                   value="<?= e($current_title) ?>"
                   maxlength="100"
                   placeholder="Team Manager"
                   required>
            <div class="form-text text-muted">
                Wird in der Navigationsleiste für Trainer und Spieler angezeigt
                (Format: <em>Titel · Teamname</em>).
            </div>
        </div>
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-floppy me-1"></i>Speichern
        </button>
    </form>
    <?php
});
```

**src/templates/admin/layout.php** — add "Einstellungen" nav item to both the desktop sidebar `<ul>` and the mobile tabs `<div class="d-flex">`. Insert after the existing "Trainer" item:

Desktop sidebar — add after the Trainer `<li>`:
```php
<li class="nav-item">
    <a class="nav-link <?= $active === 'settings' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
       href="/admin/settings">
        <i class="bi bi-gear-fill me-2"></i>Einstellungen
    </a>
</li>
```

Mobile tabs — add after the Trainer `<a>`:
```php
<a class="flex-fill text-center py-2 <?= $active === 'settings' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
   href="/admin/settings">Einstellungen</a>
```

**public/index.php** — add a new route entry in the Admin section, after the coach action route block:

```php
// ── Admin: Settings ────────────────────────────────────────────────────────
$path === '/admin/settings'
    => require ROOT_PATH . '/src/admin/admin_settings_handler.php',
```

Insert this before the Coach routes block (`// ── Coach: Players`).
  </action>
  <verify>
    <automated>php -l src/admin/admin_settings_handler.php && php -l public/index.php && php -l src/templates/admin/layout.php</automated>
  </verify>
  <done>
    - GET /admin/settings renders a form with the current app_title value pre-filled
    - POST /admin/settings updates the settings table and redirects to GET with ?success=1
    - Empty title submission coerces to 'Team Manager'
    - Title longer than 100 chars is truncated
    - Admin sidebar and mobile tabs show "Einstellungen" link, active when $active === 'settings'
    - All three files pass `php -l` lint check
  </done>
</task>

<task type="auto">
  <name>Task 3: Update render_navbar() to show app_title · team_name for coach/player</name>
  <files>
    src/templates/layout.php
  </files>
  <action>
Replace `render_navbar()` in `src/templates/layout.php` so it fetches `app_title` from the DB and displays the correct brand string per role.

The updated function:

```php
/**
 * Output the shared navigation bar.
 * - Admin: shows app_title only
 * - Coach/Player: shows "{app_title} · {team_name}" using session team_name
 * Shows current user + logout on the right.
 */
function render_navbar(): void {
    $display_name = '';
    $role_label   = '';

    if (!empty($_SESSION['is_admin'])) {
        $display_name = e(ADMIN_USERNAME);
        $role_label   = 'Admin';
    } elseif (!empty($_SESSION['display_name'])) {
        $display_name = e($_SESSION['display_name']);
        $role_label   = ($_SESSION['role'] ?? '') === 'coach' ? 'Trainer' : 'Spieler';
    }

    // Resolve brand string
    $app_title = get_setting('app_title', 'Team Manager');
    if (!empty($_SESSION['team_name'])) {
        // Coach or player context: show "App · Team"
        $brand = e($app_title) . ' · ' . e($_SESSION['team_name']);
    } else {
        // Admin or unauthenticated: show app title only
        $brand = e($app_title);
    }
    ?>
    <nav class="navbar navbar-light bg-white border-bottom px-3">
        <span class="navbar-brand fw-semibold"><?= $brand ?></span>
        <?php if ($display_name): ?>
        <div class="d-flex align-items-center gap-3">
            <small class="text-muted">
                <?= $display_name ?> <span class="badge bg-secondary"><?= e($role_label) ?></span>
            </small>
            <a href="/logout" class="btn btn-sm btn-outline-secondary">Abmelden</a>
        </div>
        <?php endif; ?>
    </nav>
    <?php
}
```

Note: `get_setting()` is defined in `src/db/connection.php` which is already required by `public/index.php` before any handler runs, so it is available at template render time. No additional require needed here.

The `· ` separator is a middle dot (U+00B7) followed by a regular space — use the literal character, not an HTML entity, since the template already outputs inside a UTF-8 document.
  </action>
  <verify>
    <automated>php -l src/templates/layout.php</automated>
  </verify>
  <done>
    - render_navbar() passes php -l
    - When $_SESSION['team_name'] is set (coach/player), brand is "{app_title} · {team_name}"
    - When $_SESSION['team_name'] is absent (admin, login page), brand is app_title only
    - The hardcoded string 'Team Manager' is no longer in render_navbar(); it comes from the DB via get_setting()
  </done>
</task>

</tasks>

<verification>
After all three tasks are complete:

1. `php -l` must pass on all modified PHP files (no syntax errors)
2. Schema must contain the settings table with the seed row
3. Admin panel must show "Einstellungen" in nav; visiting /admin/settings must render the form
4. Submitting the form must update the value and redirect back with the success banner
5. Log in as a coach — navbar must show "{configured_title} · {team_name}"
6. Log in as a player — navbar must show "{configured_title} · {team_name}"
7. Admin navbar must show the configured title without a team suffix
</verification>

<success_criteria>
- Admin can navigate to /admin/settings and change the app title without touching code or config files
- Title change is reflected immediately on the next page load for coaches and players
- The login page navbar shows the app title only (no team, since no session yet)
- All PHP files pass syntax check
</success_criteria>

<output>
After completion, create `.planning/quick/260430-rqd-admin-could-set-title-of-app-in-admin-pa/SUMMARY.md` with:
- What was built
- Files modified
- Any deviations from this plan
</output>
