---
id: 260430-rqd
description: "admin could set title of app in admin panel. as player and coach you see app title + team name"
mode: quick
---

# Quick Plan 260430-rqd: App Title Branding

## Goal

Admin can configure an app title in the admin panel. Coach and player navbar shows `{app_title} · {team_name}`. Admin navbar shows just `{app_title}`.

## Tasks

### Task 1: Schema + session team_name

**Files:**
- `database/schema.sql` — add `settings` table
- `src/auth/login_handler.php` — store `team_name` in session at login

**Action:**

Add `settings` table to schema.sql after the users section:

```sql
CREATE TABLE IF NOT EXISTS settings (
    key   VARCHAR(100) PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
INSERT INTO settings (key, value) VALUES ('app_title', 'Team Manager') ON CONFLICT DO NOTHING;
```

In login_handler.php, after fetching `$user` from DB, also fetch the team name and store in `$_SESSION['team_name']`. Add this after the existing `$stmt->execute([$username])` and before the password check:

```php
// after $user = $stmt->fetch(); and before password_verify
// then inside the success branch:
$team_stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
$team_stmt->execute([$user['team_id']]);
$team = $team_stmt->fetch();
$_SESSION['team_name'] = $team ? $team['name'] : '';
```

**Verify:** `database/schema.sql` has `settings` table. `login_handler.php` sets `$_SESSION['team_name']`.

**Done:** `feat(settings): add settings schema and team_name to session`

---

### Task 2: Admin settings route + page

**Files:**
- `public/index.php` — add `/admin/settings` route
- `src/templates/admin/layout.php` — add "Einstellungen" nav item
- `src/admin/settings_handler.php` — new file: GET/POST admin settings form

**Action:**

In `public/index.php`, add after the coach create route (in admin section):
```php
$path === '/admin/settings'
    => require ROOT_PATH . '/src/admin/settings_handler.php',
```

In `src/templates/admin/layout.php`, add a third nav item for "Einstellungen" (active key: `'settings'`).

Create `src/admin/settings_handler.php`:
- `require_admin()`
- GET: fetch `app_title` from settings table, render form
- POST: `require_csrf()`, validate not empty, UPDATE settings, PRG redirect

**Verify:** `/admin/settings` loads a form; submitting changes the value in DB.

**Done:** `feat(settings): admin settings page for app title`

---

### Task 3: Navbar shows app title + team name

**Files:**
- `src/templates/layout.php` — update `render_navbar()` to fetch and show app title

**Action:**

In `render_navbar()`, fetch `app_title` from DB with a static cache (one query per request). Replace the hardcoded `"Team Manager"` brand with the fetched title. For coach/player sessions, append ` · {team_name}` from `$_SESSION['team_name']`. Admin sees just the title.

**Verify:** Coach and player navbars show `{app_title} · {team_name}`. Admin navbar shows `{app_title}`.

**Done:** `feat(settings): navbar displays app title and team name`
