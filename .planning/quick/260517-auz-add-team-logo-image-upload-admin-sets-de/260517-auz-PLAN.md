---
phase: quick
plan: 260517-auz
type: execute
wave: 1
depends_on: []
files_modified:
  - src/db/connection.php
  - database/schema.sql
  - src/admin/settings_handler.php
  - src/coordinator/logo_handler.php
  - src/templates/coordinator/logo.php
  - src/templates/layout.php
  - public/index.php
  - public/.htaccess
autonomous: true
requirements: []
must_haves:
  truths:
    - "Admin can upload a default logo image on /admin/settings; it is only applied to teams that have no logo yet"
    - "Coordinator can upload a logo image for their own team on /coordinator/logo"
    - "Uploading a coordinator logo never gets overwritten by admin default"
    - "The browser tab favicon for all pages reflects the current team/default logo (or none)"
    - "Uploaded files are stored on disk in uploads/ directory, inaccessible from the web via direct URL"
  artifacts:
    - path: "src/coordinator/logo_handler.php"
      provides: "GET/POST handler for coordinator team-logo upload"
    - path: "src/templates/coordinator/logo.php"
      provides: "Upload form for coordinator"
    - path: "uploads/"
      provides: "On-disk storage for logo images (protected from direct web access)"
  key_links:
    - from: "src/templates/layout.php render_layout_head()"
      to: "/logo"
      via: "<link rel=icon href=/logo>"
    - from: "src/db/connection.php maybe_migrate_db()"
      to: "teams.logo_path column"
      via: "ALTER TABLE teams ADD COLUMN IF NOT EXISTS logo_path"
    - from: "src/admin/settings_handler.php"
      to: "settings key default_team_logo"
      via: "INSERT/UPDATE settings where key='default_team_logo'"
---

<objective>
Add team-logo image upload with three-tier precedence: coordinator uploads per-team logo, admin sets a default for logo-less teams, the logo serves as browser favicon on all pages.

Purpose: Coordinators can brand their team with a custom logo; admin can set a fallback. The favicon integrates the logo into the browser experience without a build step.
Output: Upload forms in admin settings and coordinator logo page, on-disk file storage in protected uploads/ directory, dynamic favicon served via /logo route.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

Key existing patterns:
- Settings stored as key/value pairs in `settings` table (`app_title`, `app_color`, `default_team_logo`)
- Migrations are inline in `maybe_migrate_db()` in `src/db/connection.php` — no migration files
- File uploads go to disk; no blob storage. Protected directory must be outside web root or blocked via .htaccess
- Routes registered in `public/index.php` match statement
- `require_admin()` / `require_coordinator()` guards at top of handlers
- `redirect()` helper in `src/utils/helpers.php`; `e()` for XSS-safe output
- `csrf_field()` / `require_csrf()` for all POST forms
- `$_SESSION['team_id']` carries coordinator's team; `get_db()` returns singleton PDO
- `render_admin_page($title, $active, fn)` / `render_coach_page($title, $active, fn)` for layout
- `render_layout_head()` in `src/templates/layout.php` is the place to inject `<link rel="icon">`
- Deploy is FTP mirror: `src/`, top-level files, and `public/` — so `uploads/` must live at repo root alongside `src/`

Hetzner shared hosting layout:
  public_html/team-manager/
    index.php         (from public/index.php)
    .htaccess         (from public/.htaccess)
    src/
    database/
    uploads/          (NEW — created at runtime if missing; blocked by .htaccess)
</context>

<tasks>

<task type="auto">
  <name>Task 1: DB migration + file storage + /logo favicon route</name>
  <files>
    src/db/connection.php
    database/schema.sql
    public/index.php
    public/.htaccess
  </files>
  <action>
**A. Migration in `src/db/connection.php` — append to `maybe_migrate_db()`:**

Add "Migration 009" at the end of the function body:

```php
// Migration 009: team logo support
// Add logo_path column to teams table (NULL = no logo)
$logo_path_exists = (bool)$pdo->query(
    "SELECT 1 FROM information_schema.columns
     WHERE table_schema = '{$schema}' AND table_name = 'teams' AND column_name = 'logo_path'"
)->fetchColumn();
if (!$logo_path_exists) {
    try {
        $pdo->exec(
            "ALTER TABLE {$schema}.teams
             ADD COLUMN IF NOT EXISTS logo_path VARCHAR(500) NULL"
        );
    } catch (PDOException $e) {
        error_log('team-manager: migration 009 ALTER teams.logo_path skipped — ' . $e->getMessage());
    }
}
// Add default_team_logo setting (value = relative path from ROOT_PATH)
try {
    $pdo->exec(
        "INSERT INTO {$schema}.settings (key, value) VALUES ('default_team_logo', '')
         ON CONFLICT DO NOTHING"
    );
} catch (PDOException $e) {
    error_log('team-manager: migration 009 settings default_team_logo skipped — ' . $e->getMessage());
}
```

**B. `database/schema.sql`** — add `logo_path VARCHAR(500) NULL` column to the `teams` CREATE TABLE definition, and add `('default_team_logo', '')` to the settings INSERT block. (Schema.sql is the canonical reference; migration in connection.php handles existing DBs.)

**C. New route in `public/index.php`** — add before the default 404 case:

```php
// ── Logo / Favicon ────────────────────────────────────────────────────────
$path === '/logo'
    => (function() {
        // Determine logo path:
        // 1. If coordinator/member session: use team logo_path
        // 2. If no team logo: use admin default_team_logo from settings
        // 3. If neither: 404
        $logo_file = null;
        $pdo = get_db();
        if (!empty($_SESSION['team_id'])) {
            $stmt = $pdo->prepare("SELECT logo_path FROM teams WHERE id = ?");
            $stmt->execute([(int)$_SESSION['team_id']]);
            $row = $stmt->fetchColumn();
            if ($row) $logo_file = $row;
        }
        if (!$logo_file) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'default_team_logo'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val) $logo_file = $val;
        }
        if (!$logo_file) {
            http_response_code(404);
            exit;
        }
        $abs = ROOT_PATH . '/' . ltrim($logo_file, '/');
        if (!file_exists($abs) || !is_file($abs)) {
            http_response_code(404);
            exit;
        }
        $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
        $mime = match($ext) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            default => 'application/octet-stream',
        };
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=3600');
        readfile($abs);
        exit;
    })(),
```

**D. `public/.htaccess`** — add rule to deny direct web access to uploads/ directory. Add after the existing `<FilesMatch>` block:

```apache
# Block direct access to uploads directory
<IfModule mod_rewrite.c>
    RewriteRule ^uploads/ - [F,L]
</IfModule>
```

Also ensure .htaccess already passes /logo through to index.php — it does (the `RewriteCond %{REQUEST_FILENAME} !-f` rule catches it since `/logo` is not a file).
  </action>
  <verify>
    Confirm connection.php ends with Migration 009 block. Confirm /logo route exists in index.php match. Confirm .htaccess has uploads/ block rule. Confirm schema.sql has logo_path column.
  </verify>
  <done>Database migration ready; /logo route serves image from disk; uploads/ blocked from direct HTTP access.</done>
</task>

<task type="auto">
  <name>Task 2: Admin default-logo upload in settings + coordinator logo upload page</name>
  <files>
    src/admin/settings_handler.php
    src/coordinator/logo_handler.php
    src/templates/coordinator/logo.php
    public/index.php
  </files>
  <action>
**A. `src/admin/settings_handler.php`** — extend POST handler to accept optional logo file upload.

At the top of the file, add `enctype="multipart/form-data"` will be needed in the form. In the POST block, after saving `app_color`, add:

```php
// Handle default logo upload (optional — skip if no file submitted)
if (!empty($_FILES['default_logo']['tmp_name'])) {
    $file       = $_FILES['default_logo'];
    $allowed    = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo      = new finfo(FILEINFO_MIME_TYPE);
    $mime       = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed, true)) {
        $error = 'Nur PNG, JPEG, GIF, WebP oder SVG-Bilder erlaubt.';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $error = 'Bild ist zu groß (max. 2 MB).';
    } else {
        $ext       = match($mime) {
            'image/png'     => 'png',
            'image/jpeg'    => 'jpg',
            'image/gif'     => 'gif',
            'image/webp'    => 'webp',
            'image/svg+xml' => 'svg',
        };
        $upload_dir = ROOT_PATH . '/uploads';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        // Delete old default logo file if it exists
        $old_stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'default_team_logo'");
        $old_stmt->execute();
        $old_path = $old_stmt->fetchColumn();
        if ($old_path && file_exists(ROOT_PATH . '/' . ltrim($old_path, '/'))) {
            @unlink(ROOT_PATH . '/' . ltrim($old_path, '/'));
        }
        $filename  = 'default_logo_' . time() . '.' . $ext;
        $dest      = $upload_dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $error = 'Hochladen fehlgeschlagen.';
        } else {
            $rel_path = 'uploads/' . $filename;
            $stmt_logo = $pdo->prepare(
                "INSERT INTO settings (key, value) VALUES ('default_team_logo', ?)
                 ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
            );
            $stmt_logo->execute([$rel_path]);
        }
    }
}
```

Wrap this block so it only runs if `$error === ''` at that point (after app_title/color validation passes). The redirect at the end of the POST block already handles success.

In the form template section in the same file, add a new `<div class="mb-4">` block for the logo upload:
- Label: "Standard-Logo (Fallback für Teams ohne eigenes Logo)"
- `<input type="file" name="default_logo" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">`
- Show current logo preview if `default_team_logo` setting is non-empty: `<img src="/logo" style="max-height:64px;" class="mt-2 d-block">`
- Helper text: "Wird nur für Teams verwendet, die noch kein eigenes Logo hochgeladen haben. Max. 2 MB. PNG, JPEG, GIF, WebP, SVG."
- Add `enctype="multipart/form-data"` to the `<form>` tag.

Load the `default_team_logo` setting alongside existing settings at bottom of the GET section:
```php
$stmt3 = $pdo->prepare("SELECT value FROM settings WHERE key = 'default_team_logo'");
$stmt3->execute();
$default_logo = $stmt3->fetchColumn() ?: '';
```
Pass `$default_logo` into the render closure.

**B. Create `src/coordinator/logo_handler.php`** — GET/POST /coordinator/logo:

```php
<?php
// src/coordinator/logo_handler.php — GET/POST /coordinator/logo

declare(strict_types=1);

require_coordinator();

$pdo   = get_db();
$error = '';

// Fetch current team logo_path
$stmt     = $pdo->prepare("SELECT logo_path FROM teams WHERE id = ?");
$stmt->execute([(int)$_SESSION['team_id']]);
$team_row = $stmt->fetch(PDO::FETCH_ASSOC);
$current_logo = $team_row['logo_path'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!empty($_FILES['team_logo']['tmp_name'])) {
        $file    = $_FILES['team_logo'];
        $allowed = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $mime    = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            $error = 'Nur PNG, JPEG, GIF, WebP oder SVG-Bilder erlaubt.';
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = 'Bild ist zu groß (max. 2 MB).';
        } else {
            $ext        = match($mime) {
                'image/png'     => 'png',
                'image/jpeg'    => 'jpg',
                'image/gif'     => 'gif',
                'image/webp'    => 'webp',
                'image/svg+xml' => 'svg',
            };
            $upload_dir = ROOT_PATH . '/uploads';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            // Delete old team logo file if it exists
            if ($current_logo && file_exists(ROOT_PATH . '/' . ltrim($current_logo, '/'))) {
                @unlink(ROOT_PATH . '/' . ltrim($current_logo, '/'));
            }
            $filename = 'team_' . (int)$_SESSION['team_id'] . '_logo_' . time() . '.' . $ext;
            $dest     = $upload_dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Hochladen fehlgeschlagen.';
            } else {
                $rel_path = 'uploads/' . $filename;
                $upd = $pdo->prepare(
                    "UPDATE teams SET logo_path = ? WHERE id = ?"
                );
                $upd->execute([$rel_path, (int)$_SESSION['team_id']]);
                redirect('/coordinator/logo?success=1');
            }
        }
    } else {
        $error = 'Bitte wähle eine Datei aus.';
    }
}

$success = !empty($_GET['success']);

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Team-Logo', 'members', function() use ($error, $success, $current_logo) {
    require ROOT_PATH . '/src/templates/coordinator/logo.php';
});
```

**C. Create `src/templates/coordinator/logo.php`** — upload form:

```php
<?php
// src/templates/coordinator/logo.php
// Variables: $error (string), $success (bool), $current_logo (string path or '')
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success">Logo gespeichert.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($current_logo): ?>
        <div class="mb-4">
            <p class="fw-semibold mb-2">Aktuelles Logo</p>
            <img src="/logo?t=<?= time() ?>" alt="Team-Logo"
                 style="max-height:96px; max-width:200px; object-fit:contain;">
        </div>
        <?php endif; ?>
        <form method="POST" action="/coordinator/logo" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-4">
                <label for="team_logo" class="form-label fw-semibold">
                    <?= $current_logo ? 'Neues Logo hochladen' : 'Logo hochladen' ?>
                </label>
                <input type="file" class="form-control" id="team_logo" name="team_logo"
                       accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" required>
                <div class="form-text">PNG, JPEG, GIF, WebP oder SVG. Max. 2 MB. Das Logo wird auch als Browser-Symbol (Favicon) angezeigt.</div>
            </div>
            <button type="submit" class="btn btn-primary min-touch">Logo speichern</button>
        </form>
    </div>
</div>
```

**D. Register coordinator route in `public/index.php`** — add after the `/coordinator/stats` route:

```php
// ── Coordinator: Logo ──────────────────────────────────────────────────────
$path === '/coordinator/logo'
    => require ROOT_PATH . '/src/coordinator/logo_handler.php',
```
  </action>
  <verify>
    Visit /admin/settings — form has file input for default logo, existing settings still present.
    Visit /coordinator/logo — upload form renders without errors.
    Check that `logo_handler.php` and `logo.php` files exist with correct content.
  </verify>
  <done>Admin can upload default logo on /admin/settings. Coordinator can upload team logo on /coordinator/logo. Files stored in uploads/ with MIME validation.</done>
</task>

<task type="auto">
  <name>Task 3: Inject favicon link into all pages + update admin default-logo apply logic</name>
  <files>
    src/templates/layout.php
  </files>
  <action>
**In `src/templates/layout.php`, inside `render_layout_head()`**, add a favicon `<link>` tag just before the closing `</head>` tag. The /logo route handles the team-vs-default precedence, so the tag is unconditional (a 404 from /logo is silently ignored by browsers):

Add after the `<style>` block, before `</head>`:

```html
    <link rel="icon" href="/logo" type="image/x-icon">
```

This goes inside the `render_layout_head()` function, right before the `?>` that closes the heredoc/function body. Specifically, insert it between the closing `</style>` tag and `</head>`:

```php
    </style>
    <link rel="icon" href="/logo">
</head>
```

**Admin default-logo apply logic** — the requirement says "admin default does not overwrite existing team logos." The /logo route already implements this correctly: it checks `team.logo_path` first, falls back to `default_team_logo` setting only when the team has no logo. No additional code needed.

However, the admin upload in settings_handler.php must NOT retroactively push the default to teams that already have logos. Confirm: the settings key `default_team_logo` is only a fallback read at request time in /logo route — it never writes to `teams.logo_path`. This is already satisfied by the design in Task 2. No bulk UPDATE needed.
  </action>
  <verify>
    Open any page in the browser — browser tab should show logo icon (once a logo is uploaded).
    The `<link rel="icon" href="/logo">` tag appears in HTML source of login page, coordinator page, and admin page.
  </verify>
  <done>Favicon appears in browser tab for all roles. Admin default logo serves as fallback for teams without their own logo. Coordinator-uploaded logos are never overwritten by admin default.</done>
</task>

</tasks>

<verification>
1. `grep -r "logo" /Users/sebastianwiller/Documents/github/team-manager/src/templates/layout.php` — shows favicon link tag
2. `grep -r "maybe_migrate_db\|logo_path\|default_team_logo" /Users/sebastianwiller/Documents/github/team-manager/src/db/connection.php` — shows migration 009
3. `grep "/logo\|/coordinator/logo" /Users/sebastianwiller/Documents/github/team-manager/public/index.php` — shows both routes registered
4. `ls /Users/sebastianwiller/Documents/github/team-manager/src/coordinator/logo_handler.php` — file exists
5. `ls /Users/sebastianwiller/Documents/github/team-manager/src/templates/coordinator/logo.php` — file exists
6. `grep "enctype\|default_logo\|team_logo" /Users/sebastianwiller/Documents/github/team-manager/src/admin/settings_handler.php` — shows multipart form and upload handling
</verification>

<success_criteria>
- Admin uploads a PNG on /admin/settings → stored in uploads/, teams without logo show it as favicon
- Coordinator uploads a PNG on /coordinator/logo → stored in uploads/ with team-specific filename, overrides default for that team only
- Admin re-uploads a new default → previous default file is deleted from disk; teams with own logo unaffected
- Coordinator re-uploads → previous team logo file deleted; new file served immediately
- All HTML pages have `<link rel="icon" href="/logo">` in `<head>`
- Direct HTTP access to /uploads/filename returns 403 (blocked by .htaccess)
- File MIME validated via `finfo` (not extension alone); max 2 MB enforced
</success_criteria>

<output>
After completion, create `.planning/quick/260517-auz-add-team-logo-image-upload-admin-sets-de/260517-auz-SUMMARY.md`
</output>
