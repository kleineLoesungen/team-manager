---
phase: quick-260430-rhh
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - public/index.php
  - src/admin/team_action_handler.php
  - src/admin/coach_action_handler.php
  - src/templates/admin/dashboard.php
  - src/admin/coaches_handler.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "Admin can deactivate an active team (already worked, stays working)"
    - "Admin can reactivate an inactive team"
    - "Admin can deactivate an active coach"
    - "Admin can reactivate an inactive coach"
    - "Teams page shows active teams first, inactive teams in a collapsed Bootstrap section"
    - "Coaches page shows active coaches first, inactive coaches in a collapsed Bootstrap section"
  artifacts:
    - path: "src/admin/team_action_handler.php"
      provides: "reactivate action for teams"
    - path: "src/admin/coach_action_handler.php"
      provides: "deactivate and reactivate actions for coaches"
    - path: "src/templates/admin/dashboard.php"
      provides: "active/inactive grouping with Bootstrap collapse for teams"
    - path: "src/admin/coaches_handler.php"
      provides: "active/inactive grouping with Bootstrap collapse for coaches"
    - path: "public/index.php"
      provides: "router patterns updated to allow reactivate action"
  key_links:
    - from: "src/templates/admin/dashboard.php"
      to: "/admin/teams/{id}/reactivate"
      via: "POST form on inactive team card"
    - from: "src/admin/coaches_handler.php (inline template)"
      to: "/admin/coaches/{id}/deactivate and /admin/coaches/{id}/reactivate"
      via: "POST forms on coach list items"
---

<objective>
Enable deactivate + reactivate for both teams and coaches, and group each admin list into active (visible) and inactive (collapsed) sections.

Purpose: Admin currently cannot reactivate anything — effectively a one-way soft delete. Grouping reduces noise when many items are inactive.
Output: Updated action handlers, router patterns, and both admin list templates.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@public/index.php
@src/admin/team_action_handler.php
@src/admin/coach_action_handler.php
@src/templates/admin/dashboard.php
@src/admin/coaches_handler.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add reactivate to team handler and deactivate/reactivate to coach handler; update router</name>
  <files>src/admin/team_action_handler.php, src/admin/coach_action_handler.php, public/index.php</files>
  <action>
**src/admin/team_action_handler.php** — add `reactivate` branch after the existing `deactivate` branch:

```php
} elseif ($action === 'reactivate') {
    $stmt = $pdo->prepare("UPDATE teams SET is_active = TRUE WHERE id = ?");
    $stmt->execute([$team_id]);
    redirect('/admin/teams');
}
```

**src/admin/coach_action_handler.php** — currently only handles `reset-password`. Add two new action branches before the closing `redirect('/admin/coaches')`:

After the existing SELECT verify block, add:

```php
if ($action === 'reset-password') {
    // ... existing password reset code (no change) ...
} elseif ($action === 'deactivate') {
    $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ? AND role = 'coach'");
    $stmt->execute([$coach_id]);
    redirect('/admin/coaches');
} elseif ($action === 'reactivate') {
    $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id = ? AND role = 'coach'");
    $stmt->execute([$coach_id]);
    redirect('/admin/coaches');
} else {
    redirect('/admin/coaches');
}
```

Restructure the file so the `$action !== 'reset-password'` early-exit guard is replaced by a proper if/elseif chain covering all three valid actions.

**public/index.php** — update both admin route regexes to include `reactivate`:

Teams route (line ~41):
```php
(bool)preg_match('#^/admin/teams/(\d+)/(edit|deactivate|reactivate)$#', $path, $matches)
```
(remove `reset-password` from team pattern — teams have no password reset)

Coaches route (line ~57):
```php
(bool)preg_match('#^/admin/coaches/(\d+)/(deactivate|reactivate|reset-password)$#', $path, $matches)
```
  </action>
  <verify>
    <automated>php -l src/admin/team_action_handler.php && php -l src/admin/coach_action_handler.php && php -l public/index.php</automated>
  </verify>
  <done>All three files pass PHP lint. Router accepts reactivate for both teams and coaches. Coach handler handles deactivate, reactivate, and reset-password. Team handler handles edit, deactivate, and reactivate.</done>
</task>

<task type="auto">
  <name>Task 2: Group teams and coaches into active/inactive sections with Bootstrap collapse</name>
  <files>src/templates/admin/dashboard.php, src/admin/coaches_handler.php</files>
  <action>
**Pattern for both pages:** Split items into `$active_*` and `$inactive_*` arrays in the handler (or at the top of the template), then render:

1. Active items — normal list, always visible, count in header
2. "Inaktiv (N)" toggle — `<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#inactiveItems">` with a `bi-chevron-down` icon, only rendered when inactive count > 0
3. Inactive items — inside `<div class="collapse" id="inactiveItems">`, styled with `opacity-75`

---

**src/templates/admin/dashboard.php** (teams page):

At the top of the template (before the `<div class="d-flex ...">` header), split `$teams`:
```php
$active_teams   = array_filter($teams, fn($t) => $t['is_active']);
$inactive_teams = array_filter($teams, fn($t) => !$t['is_active']);
```

Update the header count to show only active count (or all if preferred):
```php
<span class="text-muted"><?= count($active_teams) ?> aktive Team(s)</span>
```

Render `$active_teams` in the existing card loop (unchanged card markup).

After the active cards, if `count($inactive_teams) > 0`, add:
```php
<div class="mt-4">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#inactiveTeams"
            aria-expanded="false">
        <i class="bi bi-chevron-down"></i>
        Inaktiv (<?= count($inactive_teams) ?>)
    </button>
    <div class="collapse mt-2" id="inactiveTeams">
        <div class="row g-3">
            <?php foreach ($inactive_teams as $team): ?>
            <!-- same card markup but with reactivate button instead of deactivate -->
            <div class="col-12">
                <div class="card border-secondary opacity-75">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="h5 fw-semibold mb-1 text-muted"><?= e($team['name']) ?></h2>
                                <span class="badge bg-secondary">Deaktiviert</span>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <form method="POST"
                                      action="/admin/teams/<?= $team['id'] ?>/reactivate"
                                      onsubmit="return confirm('<?= e('Das Team wird reaktiviert. Trainer können sich wieder anmelden.') ?>')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
```

For active teams: remove the `border-secondary opacity-75` condition (active cards are always clean). Keep the "Team deaktivieren" button only on active team cards (it was already gated by `$team['is_active']`).

---

**src/admin/coaches_handler.php** (coaches page — inline template):

Split coaches in the handler before `render_admin_page()`:
```php
$active_coaches   = array_filter($coaches, fn($c) => $c['is_active']);
$inactive_coaches = array_filter($coaches, fn($c) => !$c['is_active']);
```

Pass both to the closure: `function() use ($active_coaches, $inactive_coaches, $teams, $error)`

In the inline template:
- Header: show `count($active_coaches) . ' aktive Trainer'`
- Active coaches loop: same list-group markup as today, but add a "Deaktivieren" POST form button next to "Passwort zurücksetzen":
```php
<form method="POST"
      action="/admin/coaches/<?= $coach['id'] ?>/deactivate"
      onsubmit="return confirm('<?= e('Der Trainer wird deaktiviert und kann sich nicht mehr anmelden.') ?>')">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-sm btn-outline-warning">
        Deaktivieren
    </button>
</form>
```
- After active section, if inactive coaches exist, add collapse toggle + list with "Reaktivieren" button and no "Passwort zurücksetzen":
```php
<form method="POST"
      action="/admin/coaches/<?= $coach['id'] ?>/reactivate"
      onsubmit="return confirm('<?= e('Der Trainer wird reaktiviert und kann sich wieder anmelden.') ?>')">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-sm btn-outline-success btn-sm">
        <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
    </button>
</form>
```

Use collapse id `inactiveCoaches` for the coaches page collapse target.
  </action>
  <verify>
    <automated>php -l src/templates/admin/dashboard.php && php -l src/admin/coaches_handler.php</automated>
  </verify>
  <done>Both templates lint clean. Active teams/coaches are shown normally with deactivate buttons. Inactive section is collapsed by default. Inactive teams have a reactivate button. Inactive coaches have a reactivate button and no password-reset button.</done>
</task>

</tasks>

<verification>
After execution, manually verify:
1. Visit /admin/teams — active teams visible, "Inaktiv (N)" toggle appears if any inactive exist
2. Click "Team deaktivieren" — team moves to inactive section after redirect
3. Expand inactive section, click "Reaktivieren" — team returns to active section
4. Visit /admin/coaches — same grouping pattern
5. Click "Deaktivieren" on a coach — coach moves to inactive section
6. Expand inactive section, click "Reaktivieren" on coach — coach returns to active
7. Inactive coach has no "Passwort zurücksetzen" button (not needed while deactivated)
</verification>

<success_criteria>
- Teams: deactivate and reactivate both work; inactive grouped in collapsed section
- Coaches: deactivate and reactivate both work; inactive grouped in collapsed section
- No PHP errors; all POST actions use CSRF tokens
- Collapsed section defaults to hidden; toggle shows chevron icon and count
</success_criteria>

<output>
After completion, create `.planning/quick/260430-rhh-admin-ui-active-inactive-grouping-for-te/260430-rhh-SUMMARY.md`
</output>
