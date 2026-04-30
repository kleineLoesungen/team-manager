---
phase: quick
plan: 260430-rbt
type: execute
wave: 1
depends_on: []
files_modified:
  - src/coach/list_detail_handler.php
  - src/templates/coach/list_detail.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "Coach sees all player rows as a single inline-editable table (no navigation away to edit)"
    - "Each cell renders the correct input type: checkbox for boolean, number input for number, text input for text"
    - "Inputs are pre-populated with existing cell values on GET"
    - "A single 'Speichern' button submits all rows at once via POST to /coach/lists/{id}"
    - "Server validates and upserts all submitted cells; redirects to same page with success indicator"
    - "CSRF token is validated on POST; invalid requests are rejected"
  artifacts:
    - path: "src/coach/list_detail_handler.php"
      provides: "Handles bulk POST of cells[player_id][column_id] = value; validates per type; upserts all"
    - path: "src/templates/coach/list_detail.php"
      provides: "Table wrapped in <form>, inline inputs per cell, single Speichern button"
  key_links:
    - from: "src/templates/coach/list_detail.php"
      to: "POST /coach/lists/{id}"
      via: "<form method='POST'> wrapping entire table"
      pattern: "form.*method.*POST"
    - from: "src/coach/list_detail_handler.php"
      to: "cells table"
      via: "UPSERT per player × column"
      pattern: "INSERT INTO cells.*ON CONFLICT"
---

<objective>
Replace the per-row "Bearbeiten" navigate-away pattern on the coach list detail page with a single inline-editable table form. All players are editable in place; one "Speichern" button submits everything at once.

Purpose: Coaches enter data for multiple players in one operation instead of navigating to a separate page per player — the primary data-entry workflow.
Output: Updated list_detail_handler.php (handles bulk POST) and list_detail.php (inline inputs, single form).
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
  <name>Task 1: Add bulk POST handler to list_detail_handler.php</name>
  <files>src/coach/list_detail_handler.php</files>
  <action>
Add a POST branch before the existing GET render. The handler already loads $list_id, $pdo, $columns, $players, and $cells — reuse all of that.

POST processing logic (insert above the `$error = ...` line, before `render_coach_page`):

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $post_error = '';
    try {
        $submitted = $_POST['cells'] ?? [];

        foreach ($players as $player) {
            $pid = (int)$player['id'];
            foreach ($columns as $col) {
                $col_id    = (int)$col['id'];
                $data_type = $col['data_type'];
                $raw_value = $submitted[$pid][$col_id] ?? null;

                switch ($data_type) {
                    case 'boolean':
                        $validated_value = isset($submitted[$pid][$col_id]) ? '1' : '0';
                        break;
                    case 'number':
                        if ($raw_value !== null && $raw_value !== '') {
                            $int_valid   = filter_var($raw_value, FILTER_VALIDATE_INT)   !== false;
                            $float_valid = filter_var($raw_value, FILTER_VALIDATE_FLOAT) !== false;
                            $validated_value = ($int_valid || $float_valid) ? $raw_value : null;
                        } else {
                            $validated_value = null;
                        }
                        break;
                    case 'text':
                    default:
                        $validated_value = ($raw_value !== null) ? mb_substr($raw_value, 0, 255) : null;
                        break;
                }

                $upsert = $pdo->prepare(
                    "INSERT INTO cells (list_id, column_id, player_id, value)
                     VALUES (?, ?, ?, ?)
                     ON CONFLICT (list_id, column_id, player_id)
                     DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
                );
                $upsert->execute([$list_id, $col_id, $pid, $validated_value]);
            }
        }

        redirect('/coach/lists/' . $list_id . '?success=1');

    } catch (PDOException $e) {
        error_log('Bulk cell save error: ' . $e->getMessage());
        $post_error = 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.';
    }
}
```

Also update the `$error` line that reads from `$_GET['error']` so that `$post_error` (if set) takes precedence:
```php
$error   = $post_error ?? (!empty($_GET['error']) ? e($_GET['error']) : '');
```

Note: `$post_error` must be initialized to `''` before the POST block so it exists in scope for the GET path too.

Authorization guarantee: `require_coach()` already runs at top of file. The loop processes only players belonging to `$_SESSION['team_id']` (fetched via the same team-scoped query), so no cross-team writes are possible.
  </action>
  <verify>
    <automated>php -l src/coach/list_detail_handler.php</automated>
  </verify>
  <done>File parses without errors. POST block is present with require_csrf(), player loop, column loop, type switch, UPSERT, and redirect on success.</done>
</task>

<task type="auto">
  <name>Task 2: Replace table cells with inline inputs in list_detail.php</name>
  <files>src/templates/coach/list_detail.php</files>
  <action>
Replace the table section (everything inside `<?php else: ?>` down to `<?php endif; ?>`) with an inline-edit form. Keep the header block (badge + settings button) and the "Lokale Spalte hinzufügen" details/summary unchanged.

Changes:

1. Wrap the table in a `<form method="POST" action="/coach/lists/<?= (int)$list['id'] ?>">` that includes `<?= csrf_field() ?>`.

2. In each `<td>` for a cell, replace the static value display with the appropriate input based on `$col['data_type']`:

   **boolean:**
   ```php
   $checked = ($val === '1') ? 'checked' : '';
   echo '<input type="checkbox" class="form-check-input"
         name="cells[' . (int)$player['id'] . '][' . (int)$col['id'] . ']"
         value="1" ' . $checked . '>';
   ```

   **number:**
   ```php
   $escaped = ($val !== null && $val !== '') ? e($val) : '';
   echo '<input type="number" class="form-control form-control-sm"
         style="min-width:70px; max-width:100px"
         name="cells[' . (int)$player['id'] . '][' . (int)$col['id'] . ']"
         value="' . $escaped . '">';
   ```

   **text (default):**
   ```php
   $escaped = ($val !== null) ? e($val) : '';
   echo '<input type="text" class="form-control form-control-sm"
         style="min-width:100px"
         name="cells[' . (int)$player['id'] . '][' . (int)$col['id'] . ']"
         value="' . $escaped . '" maxlength="255">';
   ```

3. Remove the last `<th></th>` (edit button column) and the corresponding `<td>` with the "Bearbeiten" `<a>` link — no per-row edit button needed.

4. Add the submit button after the closing `</table>` but inside the `</form>`:
   ```html
   <div class="mt-3">
       <button type="submit" class="btn btn-primary min-touch">Speichern</button>
   </div>
   ```

5. Keep the "Zurück zu Listen" link below the `</form>` (outside the form, after `<?php endif; ?>`).

Mobile consideration: number inputs use `style="min-width:70px; max-width:100px"` and text inputs use `style="min-width:100px"` so the table-responsive horizontal scroll handles overflow naturally on small screens. Do not add JS — pure HTML form, progressive enhancement.
  </action>
  <verify>
    <automated>php -l src/templates/coach/list_detail.php</automated>
  </verify>
  <done>File parses without errors. Table is wrapped in a POST form. Each data cell renders an input (checkbox/number/text) pre-populated from $cells map. No "Bearbeiten" link remains. A "Speichern" submit button appears after the table inside the form.</done>
</task>

</tasks>

<verification>
After both tasks:
1. `php -l src/coach/list_detail_handler.php` — no parse errors
2. `php -l src/templates/coach/list_detail.php` — no parse errors
3. Load /coach/lists/{id} in browser: table shows inputs pre-populated, no "Bearbeiten" button
4. Change values across multiple rows, click "Speichern": redirects back to same page with success message, changed values are visible in inputs
5. Verify CSRF: submit form with invalid/missing token → rejected (403 or error)
</verification>

<success_criteria>
- Coach can edit any cell directly in the list detail table — no navigation to a separate page
- All rows save in a single POST submission
- Inputs reflect current stored values on GET (pre-populated)
- Boolean columns show a checkbox; number columns show a number input; text columns show a text input
- Invalid number values are silently skipped (null); text values are truncated at 255 chars
- CSRF protection is enforced on POST
- "Zurück zu Listen" remains accessible outside the form
</success_criteria>

<output>
After completion, create `.planning/quick/260430-rbt-coach-list-detail-bulk-inline-editing/260430-rbt-SUMMARY.md`
</output>
