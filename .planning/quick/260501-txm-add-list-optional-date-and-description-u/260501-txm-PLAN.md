---
id: 260501-txm
description: "add list optional date and description; use date in stats filter with toggle for undated lists"
mode: quick
---

# Quick Plan 260501-txm: List Date & Description

## Goal

Lists get two optional fields: `date` (DATE) and `description` (TEXT). Coaches set them at creation or in settings. The coach stats date filter switches from filtering by `cells.updated_at` to filtering by `lists.date`, with a new "include undated lists" checkbox.

## Tasks

### Task 1: Schema — add date and description to lists

**Files:**
- `database/schema.sql`

**Action:**

In the `lists` CREATE TABLE block, add after `is_hidden`:
```sql
description TEXT         NULL,
date        DATE         NULL,
```

Add migration comments after the existing migration block:
```sql
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS description TEXT NULL;
-- ALTER TABLE lists ADD COLUMN IF NOT EXISTS date DATE NULL;
```

**Done:** `feat(lists): add date and description columns to lists schema`

---

### Task 2: List creation and settings — add date + description fields

**Files:**
- `src/templates/coach/list_form.php`
- `src/coach/list_create_handler.php`
- `src/coach/list_settings_handler.php`

**Action:**

**`list_form.php`** — Add a new section after the name section (before visibility):

```php
<!-- Section: Date (optional) -->
<div class="mb-4">
    <label for="list_date" class="form-label fw-semibold">Datum <span class="text-muted fw-normal">(optional)</span></label>
    <input type="date" id="list_date" name="date" class="form-control">
    <div class="form-text">z. B. Datum des Spiels oder Trainings</div>
</div>

<!-- Section: Description (optional) -->
<div class="mb-4">
    <label for="list_desc" class="form-label fw-semibold">Beschreibung <span class="text-muted fw-normal">(optional)</span></label>
    <textarea id="list_desc" name="description" class="form-control" rows="2" maxlength="500"
              placeholder="z. B. Heimspiel gegen FC Muster, Pokalrunde 2"></textarea>
</div>
```

**`list_create_handler.php`** — After the existing `$show_all_rows` parsing, add:
```php
$date        = trim($_POST['date'] ?? '');
$description = trim($_POST['description'] ?? '');
// Validate date format if provided
if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = '';
}
```

Update the INSERT to include `date` and `description`:
```sql
INSERT INTO lists (team_id, name, visibility, show_all_rows, date, description)
VALUES (?, ?, ?, ?, ?, ?)
```
Pass `$date !== '' ? $date : null` and `$description !== '' ? $description : null`.

**`list_settings_handler.php`** — Update the SELECT to fetch `date` and `description`. In POST handling, parse and validate them the same way as create. Update the UPDATE statement to include both fields. Add the two form fields to the settings template (same HTML as list_form.php, pre-populated with current values).

**Done:** `feat(lists): date and description at creation and in settings`

---

### Task 3: Coach stats — filter by lists.date with undated toggle

**Files:**
- `src/coach/stats_handler.php`
- `src/templates/coach/stats.php`

**Action:**

**`stats_handler.php`** — Add `include_undated` filter parameter after the existing date params:
```php
$filter_include_undated = !empty($_GET['include_undated']);
```

Replace the `$filter_date_from` / `$filter_date_to` SQL blocks (in BOTH the aggregation query and the leaderboard query) with a new helper block:

```php
if ($filter_date_from !== null || $filter_date_to !== null) {
    $date_conds = ['cells.id IS NULL'];  // CROSS JOIN artifact — always include
    if ($filter_include_undated) {
        $date_conds[] = 'lists.date IS NULL';
    }
    $range_conds = [];
    if ($filter_date_from !== null) {
        $range_conds[] = 'lists.date >= ?';
        $agg_params[] = $filter_date_from;
    }
    if ($filter_date_to !== null) {
        $range_conds[] = 'lists.date <= ?';
        $agg_params[] = $filter_date_to;
    }
    if (!empty($range_conds)) {
        $date_conds[] = '(lists.date IS NOT NULL AND ' . implode(' AND ', $range_conds) . ')';
    }
    $agg_sql .= ' AND (' . implode(' OR ', $date_conds) . ')';
}
```

Apply the same pattern in the leaderboard query (using `$lb_params` instead of `$agg_params`).

Pass `$filter_include_undated` to the template.

**`stats.php`** — In the filter form:
- Rename "Von" → "Listendatum von" and "Bis" → "bis" (clarify it's list date, not cell date)
- Add checkbox after the date inputs:
```php
<div class="col-auto d-flex align-items-end pb-1">
    <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" name="include_undated" id="include_undated"
               value="1" <?= $filter_include_undated ? 'checked' : '' ?>>
        <label class="form-check-label small" for="include_undated">
            Ohne Datum einschließen
        </label>
    </div>
</div>
```

In the leaderboard hidden-inputs block, add:
```php
<?php if ($filter_include_undated): ?><input type="hidden" name="include_undated" value="1"><?php endif; ?>
```

**Done:** `feat(stats): filter by list date with undated-lists toggle`
