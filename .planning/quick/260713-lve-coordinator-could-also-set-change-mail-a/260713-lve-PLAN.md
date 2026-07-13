---
phase: quick-260713-lve
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/coordinator/member_edit_email_handler.php
  - src/templates/coordinator/member_edit_email.php
  - public/index.php
  - src/coordinator/members_handler.php
  - src/templates/coordinator/members.php
autonomous: true
requirements: [LVE-01]

must_haves:
  truths:
    - "Coordinator can navigate to an edit-email page for any member on their team"
    - "Coordinator can set or clear a member's email address"
    - "Invalid email format is rejected with a German error message"
    - "Coordinator cannot edit emails for members on other teams"
    - "After save, coordinator is redirected to member list with a success message"
    - "Member cards on /coordinator/members show an E-Mail bearbeiten link"
  artifacts:
    - path: "src/coordinator/member_edit_email_handler.php"
      provides: "GET+POST handler for coordinator editing member email"
    - path: "src/templates/coordinator/member_edit_email.php"
      provides: "Edit-email form template (coordinator layout)"
  key_links:
    - from: "src/templates/coordinator/members.php"
      to: "/coordinator/members/{id}/edit-email"
      via: "anchor link in card footer"
    - from: "src/coordinator/member_edit_email_handler.php"
      to: "users table"
      via: "UPDATE WHERE id=? AND role='member' AND team_id=?"
    - from: "public/index.php"
      to: "src/coordinator/member_edit_email_handler.php"
      via: "preg_match route before deactivate/reactivate/reset-password pattern"
---

<objective>
Coordinators can set or update the email address of members on their team, mirroring the admin pattern for coordinator emails.

Purpose: Email addresses on members are needed for Phase 5 notification sending. Coordinators manage their team members directly, so they need the ability to maintain member email addresses without involving the admin.
Output: New edit-email route + handler + template for coordinators; members list updated with link and success feedback.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@public/index.php
@src/admin/coordinator_edit_email_handler.php
@src/templates/admin/coordinator_edit_email.php
@src/coordinator/members_handler.php
@src/templates/coordinator/members.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Create member_edit_email handler, template, and route</name>
  <files>
    src/coordinator/member_edit_email_handler.php,
    src/templates/coordinator/member_edit_email.php,
    public/index.php
  </files>
  <action>
Create `src/coordinator/member_edit_email_handler.php` by adapting `src/admin/coordinator_edit_email_handler.php`:

- Replace `require_admin()` with `require_coordinator()`
- Use `$_REQUEST['member_id']` (not `coordinator_id`)
- Ownership SELECT: `WHERE id = ? AND role = 'member' AND team_id = ?` — bind `[$member_id, $_SESSION['team_id']]` — triple-constraint, no RLS shortcut
- Variable name: `$member` (not `$coordinator`)
- POST validation: identical — `filter_var($email_raw, FILTER_VALIDATE_EMAIL)`, max 255 chars, allow empty to clear (store NULL)
- UPDATE: `WHERE id = ? AND role = 'member' AND team_id = ?` — bind `[$email_or_null, $member_id, $_SESSION['team_id']]`
- On success redirect: `/coordinator/members?success=` . urlencode('E-Mail-Adresse für ' . $member['first_name'] . ' ' . $member['last_name'] . ' gespeichert.')
- On GET with `?error=`: populate `$error` from `$_GET['error']` via `e()`
- Load layout: `require ROOT_PATH . '/src/templates/coordinator/layout.php';`
- Render: `render_coach_page('E-Mail-Adresse bearbeiten — ' . e($member['first_name'] . ' ' . $member['last_name']), 'members', function() use ($member, $error, $success) { require ...; });`

Create `src/templates/coordinator/member_edit_email.php` by adapting `src/templates/admin/coordinator_edit_email.php`:

- Back link: `href="/coordinator/members"` with label "Zurück zur Übersicht"
- Context line: "Mitglied: **{first_name} {last_name}**" (not "Koordinator:")
- Form action: `/coordinator/members/{id}/edit-email`
- Input id/name: `member_email` / `email`
- Placeholder: `mitglied@email.de`
- Helper text: "Wird für Benachrichtigungen genutzt. Nur für den Koordinator sichtbar."
- Cancel link back to `/coordinator/members`
- All other structure identical: alert-danger / alert-success blocks, csrf_field(), btn-primary "Speichern"

Update `public/index.php`: add the new route **before** the existing `preg_match('#^/coordinator/members/(\d+)/(deactivate|reactivate|reset-password)$#', ...)` entry (more specific path must come first):

```php
// /coordinator/members/{id}/edit-email — GET+POST: edit member email
(bool)preg_match('#^/coordinator/members/(\d+)/edit-email$#', $path, $matches)
    => (function() use ($matches) {
        $_REQUEST['member_id'] = (int)$matches[1];
        require ROOT_PATH . '/src/coordinator/member_edit_email_handler.php';
    })(),
```
  </action>
  <verify>
    Visit /coordinator/members/{valid_id}/edit-email while logged in as coordinator — page renders with member name and email form. Submit with an invalid email string — error message appears. Submit with empty field — redirects to /coordinator/members (clears email to NULL).
  </verify>
  <done>
    Handler exists, renders form via coordinator layout, validates email, saves or clears email with triple-constraint UPDATE, redirects with success message on save.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add email link to members list and surface success feedback</name>
  <files>
    src/coordinator/members_handler.php,
    src/templates/coordinator/members.php
  </files>
  <action>
Update `src/coordinator/members_handler.php`:

1. Add `email` to the SELECT query:
   ```sql
   SELECT id, first_name, last_name, username, is_active, email
   FROM users
   WHERE role = 'member'
   ORDER BY is_active DESC, first_name, last_name
   ```
2. Add `$success` variable after `$error`:
   ```php
   $success = !empty($_GET['success']) ? e($_GET['success']) : '';
   ```
3. Pass `$success` into the render closure alongside `$players` and `$error`.

Update `src/templates/coordinator/members.php`:

1. At the top of the template (before the d-flex header row), render the success alert if set:
   ```php
   <?php if ($success): ?>
   <div class="alert alert-success mb-3"><?= $success ?></div>
   <?php endif; ?>
   ```
   (Note: `$success` is already escaped in the handler, so no double-escape.)

2. In each **active** member card footer (`card-footer` div), add an "E-Mail bearbeiten" anchor link after the existing buttons:
   ```php
   <a href="/coordinator/members/<?= (int)$player['id'] ?>/edit-email"
      class="btn btn-sm btn-outline-secondary min-touch">
       <i class="bi bi-envelope me-1"></i>E-Mail
   </a>
   ```
   Place this alongside the existing "Passwort zurücksetzen" form and "Deaktivieren" form. Keep the card-footer as `d-flex gap-2 flex-wrap` (add `flex-wrap` to avoid overflow on small screens).

3. Optionally show a small email indicator in the card body under the username `code` tag: if `$player['email']` is set, show `<span class="text-muted small"><i class="bi bi-envelope"></i> <?= e($player['email']) ?></span>`.

4. For **inactive** member cards, also add the E-Mail link in the card footer alongside the "Reaktivieren" button so coordinators can still manage emails for inactive members.
  </action>
  <verify>
    Visit /coordinator/members as coordinator — member cards show an E-Mail button. Click it — arrives at edit-email page for that member. Save a valid email — redirected to /coordinator/members with green success banner at top. Email address is visible in the member card.
  </verify>
  <done>
    Members list shows E-Mail link per member, success redirect banner displays, saved email address is visible in card body.
  </done>
</task>

</tasks>

<verification>
- GET /coordinator/members/{id}/edit-email renders form with coordinator layout and member name
- POST with invalid email shows German error, stays on form
- POST with valid email: UPDATE executes with triple-constraint (id + team_id + role='member'), redirects to /coordinator/members?success=...
- POST with empty email: stores NULL, redirects with success
- /coordinator/members shows success alert after redirect
- E-Mail button visible in each member card (active + inactive)
- Coordinator cannot access /coordinator/members/{member_from_other_team}/edit-email (redirects to /coordinator/members)
- Route placed before the (deactivate|reactivate|reset-password) pattern in index.php to prevent match interference
</verification>

<success_criteria>
- Coordinator can open the edit-email page for any of their team's members
- Valid email addresses are saved; invalid addresses show a German error
- Empty submission clears the email (NULL)
- Unauthorized member IDs (other teams) silently redirect away
- Member list reflects saved email and shows success feedback
</success_criteria>

<output>
After completion, create `.planning/quick/260713-lve-coordinator-could-also-set-change-mail-a/260713-lve-SUMMARY.md`
</output>
