---
phase: quick
plan: 260517-amy
type: execute
wave: 1
depends_on: []
files_modified:
  - src/coordinator/list_settings_handler.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "Coordinator can rename a list from the list settings page"
    - "Saving an empty or too-long name shows a validation error without data loss"
    - "Markdown file title edit already works (no change needed)"
  artifacts:
    - path: "src/coordinator/list_settings_handler.php"
      provides: "name field in list settings form + POST handler"
      contains: "new_name"
  key_links:
    - from: "list settings form"
      to: "UPDATE lists SET name"
      via: "POST handler in list_settings_handler.php"
      pattern: "new_name.*UPDATE lists"
---

<objective>
Add a name (title) field to the list settings page so coordinators can rename a list.

Purpose: The list settings form currently only offers visibility, show_all_rows, is_hidden, and date — the name is read-only display. Coordinators need to rename lists without creating a new one. The markdown file settings already has a name field (file_detail.php lines 81-83), so no change is needed there.

Output: A `name` input at the top of the existing list settings form; POST handler validates and persists the change via `UPDATE lists SET name = ?`.
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
  <name>Task 1: Add name field to list settings form and handler</name>
  <files>src/coordinator/list_settings_handler.php</files>
  <action>
Two changes in a single file:

**A — Form template (inside render_coach_page closure, existing `<form>` block):**

Add a name input as the first field in the settings form, before the visibility `<div class="mb-4">`:

```php
<div class="mb-4">
    <label for="list_name" class="form-label fw-semibold">Name</label>
    <input type="text" id="list_name" name="name"
           class="form-control" maxlength="100" required
           value="<?= e($list['name']) ?>">
</div>
```

**B — POST handler (the `else` branch starting at line 113, currently handling visibility/show_all_rows/is_hidden/date):**

1. Read and validate `$new_name = trim($_POST['name'] ?? '')`:
   - Empty → `$error = 'Name ist erforderlich.'`
   - `mb_strlen($new_name) > 100` → `$error = 'Name darf max. 100 Zeichen haben.'`
2. Include `name = ?` in the existing `UPDATE lists SET ...` statement and pass `$new_name` as the first bind parameter.
3. After a successful save, also update `$list['name']` in the local array so the page title reflects the change immediately on the error re-render path (only relevant if the redirect is removed, which it is not — redirect already happens on success).

The existing redirect on success (`redirect('/coordinator/lists/' . $list_id . '?success=1')`) already re-fetches the page, so no extra refresh logic is needed.

Ownership check stays unchanged: `WHERE id = ? AND team_id = ?`.

Do NOT add a separate action branch — keep name editing in the same form and handler block as visibility/date.
  </action>
  <verify>
    <automated>php -l src/coordinator/list_settings_handler.php</automated>
  </verify>
  <done>
    - List settings form shows a pre-filled "Name" text input above the visibility select
    - Submitting the form with a new name updates the list name in the DB and redirects with ?success=1
    - Submitting with an empty name shows a German validation error without redirecting
    - Markdown file settings unchanged (already has name field)
  </done>
</task>

</tasks>

<verification>
After implementation, manually verify:
1. Visit `/coordinator/lists/{id}/settings` — name input appears pre-filled
2. Change the name, submit — page title and breadcrumb reflect the new name
3. Submit with empty name — inline error shown, no data wiped
</verification>

<success_criteria>
Coordinator can rename a list from its settings page. No other settings behaviour is affected.
</success_criteria>

<output>
After completion, create `.planning/quick/260517-amy-list-and-markdown-title-should-be-editab/260517-amy-SUMMARY.md`
</output>
