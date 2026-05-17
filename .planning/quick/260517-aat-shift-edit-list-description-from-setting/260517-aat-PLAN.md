---
phase: quick-260517-aat
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/coordinator/list_settings_handler.php
  - src/coordinator/list_detail_handler.php
  - src/templates/coordinator/list_detail.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Coordinator can edit description directly on the list view without visiting settings"
    - "Settings page no longer shows a description field"
    - "Existing descriptions still display and save correctly"
  artifacts:
    - path: src/coordinator/list_detail_handler.php
      provides: "POST action=save_description — updates lists.description for this list"
    - path: src/templates/coordinator/list_detail.php
      provides: "Inline description form replacing the read-only paragraph"
    - path: src/coordinator/list_settings_handler.php
      provides: "Description field removed from settings form and UPDATE query"
  key_links:
    - from: src/templates/coordinator/list_detail.php
      to: src/coordinator/list_detail_handler.php
      via: "POST action=save_description"
      pattern: "action.*save_description"
---

<objective>
Move the description edit field from the list settings page into the list detail (list view) page.

Purpose: Reduce navigation friction — coordinators can update the list description without leaving the list view and going into settings.
Output: Inline editable description on list detail; description removed from settings form.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add save_description POST handler to list_detail_handler.php</name>
  <files>src/coordinator/list_detail_handler.php</files>
  <action>
In the POST dispatch block (around line 99 where `$action` is read), add a new branch for `action === 'save_description'`:

```php
} elseif ($action === 'save_description') {
    $new_description = trim($_POST['description'] ?? '');
    try {
        $upd = $pdo->prepare(
            "UPDATE lists SET description = ?, updated_at = NOW() WHERE id = ? AND team_id = ?"
        );
        $upd->execute([
            $new_description !== '' ? $new_description : null,
            $list_id,
            $_SESSION['team_id'],
        ]);
        redirect('/coordinator/lists/' . $list_id . '?success=1');
    } catch (PDOException $e) {
        error_log('List description save error: ' . $e->getMessage());
        $post_error = 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
    }
```

Place this branch before the `else` block that handles `save_cells`. This action applies to both member and free lists — no `$is_free_list` gate needed.
  </action>
  <verify>
    <automated>grep -n "save_description" /Users/sebastianwiller/Documents/github/team-manager/src/coordinator/list_detail_handler.php</automated>
  </verify>
  <done>Handler contains a POST branch for action=save_description that updates lists.description and redirects on success.</done>
</task>

<task type="auto">
  <name>Task 2: Replace read-only description with inline edit form in list_detail.php; remove description from list_settings_handler.php</name>
  <files>
    src/templates/coordinator/list_detail.php
    src/coordinator/list_settings_handler.php
  </files>
  <action>
**In src/templates/coordinator/list_detail.php (lines 40-42):**

Replace the read-only paragraph:
```php
<?php if (!empty($list['description'])): ?>
<p class="text-muted small mb-3"><?= e($list['description']) ?></p>
<?php endif; ?>
```

With an inline editable form that is always visible (no conditional — empty state shows placeholder):
```php
<form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>" class="mb-3">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_description">
    <div class="d-flex gap-2 align-items-start">
        <textarea name="description" class="form-control form-control-sm"
                  rows="2" maxlength="500"
                  placeholder="Beschreibung hinzufügen (optional)…"><?= e($list['description'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-sm btn-outline-secondary min-touch text-nowrap">Speichern</button>
    </div>
</form>
```

**In src/coordinator/list_settings_handler.php:**

1. Remove the description textarea block (lines 201-205):
```php
<div class="mb-4">
    <label for="list_desc" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
    <textarea id="list_desc" name="description" class="form-control" rows="2" maxlength="500"
              placeholder="z. B. Heimspiel gegen FC Muster, Pokalrunde 2"><?= e($list['description'] ?? '') ?></textarea>
</div>
```

2. In the main settings POST handler (the `else` branch around line 113), remove `$new_description` extraction and from the UPDATE:
   - Remove: `$new_description = trim($_POST['description'] ?? '');`
   - Change the UPDATE query to remove `description = ?,` and remove the corresponding `$new_description` parameter from the execute array.

The updated UPDATE becomes:
```php
$upd = $pdo->prepare(
    "UPDATE lists SET visibility = ?, show_all_rows = ?, is_hidden = ?, date = ?, updated_at = NOW()
     WHERE id = ? AND team_id = ?"
);
$upd->execute([
    $new_visibility,
    $new_show_all_rows,
    $new_is_hidden,
    $new_date !== '' ? $new_date : null,
    $list_id,
    $_SESSION['team_id'],
]);
```

Note: The SELECT at the top of list_settings_handler.php still fetches `description` — leave that as-is (harmless, keeps the variable available if needed for display elsewhere).
  </action>
  <verify>
    <automated>grep -n "save_description\|description" /Users/sebastianwiller/Documents/github/team-manager/src/templates/coordinator/list_detail.php && grep -c "list_desc" /Users/sebastianwiller/Documents/github/team-manager/src/coordinator/list_settings_handler.php</automated>
  </verify>
  <done>
    - list_detail.php shows inline description form (with textarea + Speichern button) instead of read-only paragraph.
    - list_settings_handler.php no longer has a description textarea or description in its UPDATE query.
    - Saving description on list view works; settings save no longer touches description.
  </done>
</task>

</tasks>

<verification>
1. Visit a list detail page — description textarea is visible and editable inline.
2. Edit description and click Speichern — redirects back with success flash, description shows updated value.
3. Visit the list settings page — no description field present in the form.
4. Save settings — description in DB is unchanged (settings no longer overwrites it).
</verification>

<success_criteria>
- Coordinator edits description directly on list view without navigating to settings.
- Settings page form has no description field.
- Description persists correctly across saves and page reloads.
</success_criteria>

<output>
After completion, create `.planning/quick/260517-aat-shift-edit-list-description-from-setting/260517-aat-SUMMARY.md`
</output>
