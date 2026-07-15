---
phase: quick
plan: 260517-mwr
type: execute
wave: 1
depends_on: []
files_modified:
  - src/coordinator/list_detail_handler.php
  - src/templates/coordinator/list_detail.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Coordinator sees a single 'Speichern' button at the bottom of the list detail page"
    - "Submitting saves both the description and all cell values in one POST"
    - "The description textarea appears above the table as before"
    - "Works for both member lists and free lists"
  artifacts:
    - path: "src/coordinator/list_detail_handler.php"
      provides: "save_all action that handles description + cells together"
    - path: "src/templates/coordinator/list_detail.php"
      provides: "Single form wrapping description textarea + table, one Speichern button at bottom"
  key_links:
    - from: "list_detail.php"
      to: "list_detail_handler.php"
      via: "POST action=save_all"
      pattern: "save_all"
---

<objective>
Merge the separate description save form and cell values save form on the coordinator list detail page into a single form with one "Speichern" button at the bottom.

Purpose: Reduces friction — coordinator fills in description and cell values, then saves once instead of twice.
Output: Single POST action `save_all` that saves description and all cells atomically.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add save_all handler action</name>
  <files>src/coordinator/list_detail_handler.php</files>
  <action>
In the POST dispatch block (around line 96), add a new `save_all` branch that combines the `save_description` logic and the `save_cells` logic into one atomic operation:

1. Read `$new_description = trim($_POST['description'] ?? '')` from POST.
2. Run the existing cells save loop (currently under the `else` / `save_cells` branch) — no changes to that logic.
3. After cells are saved (or inside the same try block), run the description UPDATE:
   `UPDATE lists SET description = ?, updated_at = NOW() WHERE id = ? AND team_id = ?`
   Pass `$new_description !== '' ? $new_description : null` so empty string becomes NULL.
4. On success redirect to `/coordinator/lists/{list_id}?success=1`.
5. On PDOException log and set `$post_error`.

Keep the existing `save_description` and `save_cells` branches intact (they are still used by free-list delete-row sub-forms and could be needed for backward compat). Just add `save_all` as a new named branch before the fallthrough `else`.

Change the fallthrough `else` (currently the save_cells branch) to also accept `action === 'save_cells'` explicitly so the logic is clear:
```php
} elseif ($action === 'save_all' || $action === 'save_cells') {
```
Then inside that branch, when `$action === 'save_all'`, also save the description. Use a flag:

```php
} elseif ($action === 'save_all' || $action === 'save_cells') {
    try {
        // Save cells (existing logic unchanged)
        $submitted = $_POST['cells'] ?? [];
        // ... existing loop ...

        // If save_all, also save description
        if ($action === 'save_all') {
            $new_description = trim($_POST['description'] ?? '');
            $upd = $pdo->prepare(
                "UPDATE lists SET description = ?, updated_at = NOW() WHERE id = ? AND team_id = ?"
            );
            $upd->execute([
                $new_description !== '' ? $new_description : null,
                $list_id,
                $_SESSION['team_id'],
            ]);
        }

        redirect('/coordinator/lists/' . $list_id . '?success=1');
    } catch (PDOException $e) {
        error_log('Bulk cell save error: ' . $e->getMessage());
        $post_error = 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
    }
}
```

This means the `save_description` branch (lines 158-173) stays for any future direct use, and the cells+description merge lives in the combined branch.
  </action>
  <verify>php -l src/coordinator/list_detail_handler.php</verify>
  <done>Handler parses cleanly; save_all branch present handling both description update and cells upsert.</done>
</task>

<task type="auto">
  <name>Task 2: Merge template into single form</name>
  <files>src/templates/coordinator/list_detail.php</files>
  <action>
The template currently has:
- Lines 40-49: standalone `<form action="save_description">` wrapping the description textarea + its own Speichern button.
- Lines 177-282 (free list) and lines 340-442 (member list): separate `<form action="save_cells">` wrapping the table + Speichern button.

**Changes:**

1. **Remove the standalone description form** (lines 40-49). Replace it with just the textarea (no wrapping `<form>`, no individual submit button):

```php
<div class="mb-3">
    <textarea name="description" class="form-control form-control-sm"
              rows="2" maxlength="500"
              placeholder="Beschreibung hinzufügen (optional)…"><?= e($list['description'] ?? '') ?></textarea>
</div>
```

The textarea must be inside the main save form, so it gets submitted together with cells. The description `<div>` should appear above the table but inside the main `<form>` tag.

2. **Member list form** (currently `<form method="POST" action="/coordinator/lists/<?= (int)$list['id'] ?>">` around line 340):
   - Add `<input type="hidden" name="action" value="save_all">` (replacing no action / implicit save_cells).
   - Move the description textarea div (from step 1) to be the first element inside this form, before the table.
   - The existing Speichern button at the bottom stays as-is.

3. **Free list form** (currently around line 177, `<form method="POST" ...>` wrapping the table):
   - Add `<input type="hidden" name="action" value="save_all">` (replacing `save_cells`).
   - Move the description textarea div (from step 1) to be the first element inside this form, before the table.
   - The existing Speichern button at the bottom stays as-is.

4. **Edge cases — empty-state branches:**
   - Member list: when `empty($players)` or `empty($columns)` — no save form is rendered. In these cases, render the description textarea in its own standalone form with `action="save_description"` (existing behaviour, not merged), so description can still be edited even without a table. This keeps existing functionality intact.
   - Free list: same — when columns or rows are empty and no main form renders, keep a standalone description form.

   Simplest approach: check before the main form whether we'll render the full table. If yes, include textarea inside the main form. If no (empty state), show the standalone description form. Implement with a PHP variable:

   ```php
   $show_full_form = $is_free_list
       ? (!empty($free_rows) && !empty($columns))
       : (!empty($players) && !empty($columns));
   ```

   Then:
   - If `$show_full_form`: description textarea is inside the main save form (action=save_all).
   - If `!$show_full_form`: render standalone description form (action=save_description) above the empty-state message.

5. The "Zurück zur Übersicht" link at the very bottom (lines 448-452) is outside all forms — keep it as-is.

Do NOT change the free-list delete-row ghost forms (`<form id="delete-row-...">`) — they use their own action and are not affected.
  </action>
  <verify>php -l src/templates/coordinator/list_detail.php</verify>
  <done>
- Template parses cleanly.
- No standalone save_description form remains when the full table is rendered.
- Description textarea appears at the top inside the main form.
- Main form uses action=save_all.
- Single Speichern button at the bottom of the form.
- Empty-state paths still have a description-only standalone form.
  </done>
</task>

</tasks>

<verification>
After both tasks:
1. `php -l src/coordinator/list_detail_handler.php` — no parse errors
2. `php -l src/templates/coordinator/list_detail.php` — no parse errors
3. Load a member list with data: description textarea + table visible, single Speichern at bottom.
4. Submit — description and cells both saved, redirects with `?success=1`.
5. Load a free list with rows+columns: same single-form behaviour.
6. Load a list with no columns: standalone description form still works.
</verification>

<success_criteria>
- One Speichern button at the bottom of the list detail page for both member and free lists (when table is shown).
- Single POST to `save_all` saves description + all cell values.
- No regression on free-list add/delete-row forms (separate actions, unaffected).
- No regression on empty-state pages (description still editable via standalone form).
</success_criteria>

<output>
After completion, create `.planning/quick/260517-mwr-combine-description-and-list-values-save/260517-mwr-SUMMARY.md`
</output>
