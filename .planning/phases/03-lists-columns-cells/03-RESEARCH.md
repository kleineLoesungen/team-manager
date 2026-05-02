# Phase 3: Lists, Columns & Cells - Research

**Researched:** 2026-04-30
**Domain:** Dynamic list management with entity-attribute-value (EAV) data model, visibility rules, and role-based cell editing
**Confidence:** HIGH

## Summary

Phase 3 builds the core list management feature where coaches create lists with configurable columns (global at team level, local per list), and players/coaches edit cells according to visibility rules (public/protected/private). The architecture extends the existing PostgreSQL RLS patterns and handler conventions. Key challenge is implementing the EAV schema cleanly (columns table + cells table) while maintaining strong visibility enforcement through RLS and application-layer checks. No new libraries needed — use existing PHP/PostgreSQL stack, Bootstrap table-responsive for mobile scrolling, and the established handler/template patterns.

**Primary recommendation:** Implement EAV schema with three tables (lists, columns, cells), use RLS policies for visibility filtering, apply visibility checks before every cell read/write operation, and follow the established POST-redirect-GET pattern for all state changes.

## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01:** Listen-Übersicht zeigt Listen als Bootstrap-Karten (name, visibility badges, column count, action buttons)
**D-02:** Coach nav gets two new items: "Listen" (`/coach/lists`) and "Spalten" (`/coach/columns`)
**D-03:** Open lists render as HTML table (players as rows, columns as columns; global columns first)
**D-04:** Mobile: horizontal scrollbar (Bootstrap `table-responsive`), no fixed first column
**D-05:** Empty tables show all players with empty cells (no placeholder text)
**D-06:** Row editing via dedicated page: `GET/POST /coach/lists/{id}/rows/{player_id}/edit` (single handler for all rows)
**D-07:** In list table, each player row has "Edit" button; no inline editing, no JavaScript required
**D-08:** Access rules exactly per requirements: CELL-01 through CELL-04
**D-09:** Global columns managed on separate page `/coach/columns` (team-level, boolean or number only)
**D-10:** Local columns defined directly in list detail view ("Add column" button, boolean/number/text)
**D-11:** List creation form has three sections: name + visibility state + select global columns (local columns added after creation)
**D-12:** Separate `/player` layout analog to `/coach`: `render_player_page()`, own navigation
**D-13:** Player home `/player/lists` shows only public lists (protected/private hidden)
**D-14:** In list view, player sees all rows but only own row has "Edit" button (others read-only)
**D-15:** Player row editing: `GET /player/lists/{id}/rows/{player_id}/edit` (server-side check: `$_SESSION['user_id'] === $player_id`)

### Claude's Discretion

- Exact DB schema for EAV: `lists`, `columns`, `cells` tables (types, constraints, RLS policies)
- Column ordering in table (global first, then local; sorted by `sort_order` or `created_at`)
- Cell value validation by `data_type` (boolean: 0/1, number: integer/float, text: max length)
- Success feedback after cell save (PRG redirect with `?success=1` banner)
- HTML `<select>` vs. radio buttons for visibility state

### Deferred Ideas (OUT OF SCOPE)

- Optional list metadata (date, start time, end time, notes/description) → Backlog 999.2
- Row visibility control (all rows vs. own row only) → Backlog 999.2
- Column reordering → v2 LIST-V2-01
- Retroactive column type changes → v2

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| LIST-01 | Coach creates list with name | Handler pattern established; `require_coach()` + RLS; insertion into `lists` table with default visibility 'public' |
| LIST-02 | Coach defines global columns (boolean/number) at team level | Separate `/coach/columns` handler; global columns belong to team; `data_type` constraint in schema |
| LIST-03 | Coach defines local columns (boolean/number/text) per list | In-list "Add column" button; local columns scoped to `list_id`; text type allowed locally only |
| LIST-04 | Each list has visibility state (public/protected/private) | Enum/varchar check constraint; visibility enforced at SELECT and UPDATE time |
| LIST-05 | Coach changes visibility state of existing list | POST handler with list_id + new state; RLS policy must prevent non-owner coaches from editing |
| CELL-01 | Player edits only own row in public lists | Access check: `user_id == player_id AND list.visibility='public'`; deny on private lists |
| CELL-02 | Coach edits all rows in public/protected lists | Access check: role='coach' AND list.visibility IN ('public', 'protected') |
| CELL-03 | Private lists invisible to players; coaches have full access | RLS `SELECT` on lists/cells filters visibility for non-coach roles |
| CELL-04 | User sees all rows of visible list but edits only allowed rows | Query returns all rows; form/button rendering checks ownership before rendering edit button |

## Standard Stack

### Core (EAV Model)
| Library/Pattern | Version | Purpose | Why Standard |
|-----------------|---------|---------|--------------|
| PostgreSQL tables: `lists`, `columns`, `cells` | 14+ | Entity-attribute-value structure | Avoids schema migrations when coaches add columns; proven pattern for flexible metadata; PostgreSQL excels at this |
| RLS policies | Native | Row filtering per visibility + role | Enforces authorization at DB layer; can't accidentally leak filtered rows if app layer fails |
| PDO prepared statements | Built-in PHP | SQL parameterization | Prevents SQL injection; explicit type binding with PostgreSQL native prepared statements |

### Core Handlers & Layout
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| `render_coach_page()` extension | Existing | Sidebar nav + mobile tabs for new items | Reuse established pattern; add 'lists' and 'columns' active states |
| `require_coach()` middleware | Existing | Session + RLS context | Already sets team context via `set_team_context()` |
| `require_auth()` + player session | Existing base | New player auth path | Adapt existing session patterns for `/player` routes |
| Bootstrap 5 `table-responsive` | 5.3 CDN | Mobile horizontal scroll | Standard Bootstrap pattern for large tables |
| POST-redirect-GET (PRG) | Pattern | Form submission → redirect with `?error=` or `?success=` | Established in Phase 2; prevents form resubmission |

### Supporting
| Technology | Version | Purpose | When to Use |
|------------|---------|---------|-------------|
| Bootstrap `.card` | 5.3 CDN | List overview cards (D-01) | Consistent with player cards in Phase 2 |
| `.badge` (visibility) | 5.3 CDN | public/protected/private labels | Quick visual status on list cards |
| `<details>/<summary>` (collapsible) | HTML5 native | Group inactive items without JS | Established in Phase 2 players.php |
| `csrf_field()` helper | Existing | Hidden CSRF token in all forms | Required on all POST; already available |

### Validation & Data Type Handling
| Concern | Approach | Notes |
|---------|----------|-------|
| Boolean columns | `0` or `1` (smallint/boolean in DB) | Client: checkbox input; server validates to 0/1 |
| Number columns | integer or float | Input validation: `filter_var($_POST['value'], FILTER_VALIDATE_INT)` or regex for floats |
| Text columns (local only) | VARCHAR with max length | Input validation: `strlen()` check; max 255 chars suggested |
| Column type enforcement | Pre-fetch column metadata, validate against `data_type` enum | Query: `SELECT data_type FROM columns WHERE id = ?` before INSERT/UPDATE cell |

**Installation:**
```bash
# No new dependencies — use existing stack
# Database schema extensions in database/schema.sql (new tables: lists, columns, cells)
# New RLS policies in database/rls_policies.sql (visibility filtering)
```

**Version verification:**
- PHP 8.3+ (already in use)
- PostgreSQL 14+ (already in use)
- Bootstrap 5.3 (already via CDN)

## Architecture Patterns

### Recommended Project Structure

```
src/
├── coach/
│   ├── lists_handler.php           # GET /coach/lists — show all lists as cards
│   ├── list_create_handler.php     # GET/POST /coach/lists/create
│   ├── list_detail_handler.php     # GET /coach/lists/{id} — table + local column UI
│   ├── list_settings_handler.php   # GET/POST /coach/lists/{id}/settings — change visibility
│   ├── list_row_edit_handler.php   # GET/POST /coach/lists/{id}/rows/{player_id}/edit
│   ├── columns_handler.php         # GET /coach/columns — show global columns
│   └── columns_create_handler.php  # POST /coach/columns/create
├── player/
│   ├── lists_handler.php           # GET /player/lists — show public lists
│   ├── list_detail_handler.php     # GET /player/lists/{id} — table (read-only rows except own)
│   └── list_row_edit_handler.php   # GET/POST /player/lists/{id}/rows/{player_id}/edit (own row only)
├── templates/
│   ├── coach/
│   │   ├── layout.php              # render_coach_page() with new nav items
│   │   ├── lists.php               # List card overview
│   │   ├── list_form.php           # Create/edit list (name + visibility + select global columns)
│   │   ├── list_detail.php         # Table view with local column UI
│   │   ├── list_row_form.php       # Edit row cells
│   │   ├── columns.php             # Global columns overview
│   │   └── column_form.php         # Create global column
│   └── player/
│       ├── layout.php              # render_player_page()
│       ├── lists.php               # Public lists as cards
│       ├── list_detail.php         # Table view (read-only rows)
│       └── list_row_form.php       # Edit own row
└── db/
    └── visibility.php              # Helper: get_list_visibility(), can_view_list(), can_edit_cell()
```

### Pattern 1: EAV Schema Design

**What:** Three-table structure: `lists` (entity), `columns` (attribute metadata), `cells` (values).

**When to use:** Flexible column types per coach; coaches define columns dynamically without schema migrations.

**Example:**

```sql
-- Source: database/schema.sql (Phase 3 extension)

-- Lists — one per coach/team, has visibility state
CREATE TABLE lists (
    id              SERIAL PRIMARY KEY,
    team_id         INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    name            VARCHAR(100) NOT NULL,
    visibility      VARCHAR(10) NOT NULL DEFAULT 'public'
                    CHECK (visibility IN ('public', 'protected', 'private')),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_lists_team_id ON lists(team_id);
CREATE INDEX idx_lists_visibility ON lists(visibility);

-- Columns — metadata for attributes (global or local)
CREATE TABLE columns (
    id              SERIAL PRIMARY KEY,
    team_id         INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    list_id         INTEGER REFERENCES lists(id) ON DELETE CASCADE,
    -- list_id IS NULL → global column; list_id IS NOT NULL → local column
    name            VARCHAR(100) NOT NULL,
    data_type       VARCHAR(10) NOT NULL
                    CHECK (data_type IN ('boolean', 'number', 'text')),
    -- text type allowed ONLY if list_id IS NOT NULL (local columns only)
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    sort_order      INTEGER DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_columns_team_id ON columns(team_id);
CREATE INDEX idx_columns_list_id ON columns(list_id);

-- Cells — the actual data (EAV values)
CREATE TABLE cells (
    id              SERIAL PRIMARY KEY,
    list_id         INTEGER NOT NULL REFERENCES lists(id) ON DELETE CASCADE,
    column_id       INTEGER NOT NULL REFERENCES columns(id) ON DELETE CASCADE,
    player_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    -- value stored as TEXT; parsed by app layer per column.data_type
    value           TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(list_id, column_id, player_id)
);
CREATE INDEX idx_cells_list_id ON cells(list_id);
CREATE INDEX idx_cells_column_id ON cells(column_id);
CREATE INDEX idx_cells_player_id ON cells(player_id);
```

### Pattern 2: Visibility-Filtered RLS Policies

**What:** RLS policies on `lists` and `cells` tables enforce visibility: public visible to all, protected visible to coaches only, private visible to coaches only.

**When to use:** Every SELECT or UPDATE on lists/cells must respect visibility state.

**Example:**

```sql
-- Source: database/rls_policies.sql (Phase 3 extension)

ALTER TABLE lists ENABLE ROW LEVEL SECURITY;
ALTER TABLE columns ENABLE ROW LEVEL SECURITY;
ALTER TABLE cells ENABLE ROW LEVEL SECURITY;

-- Lists: visibility state controls who sees what
CREATE POLICY lists_visibility_select ON lists
    FOR SELECT
    USING (
        current_setting('app.is_admin', true) = 'true'
        OR team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        OR (
            -- Public lists visible to all authenticated users in that team
            visibility = 'public'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
        OR (
            -- Protected/private visible only to coaches
            visibility IN ('protected', 'private')
            AND current_setting('app.current_role', true) = 'coach'
            AND team_id = NULLIF(current_setting('app.current_team_id', true), '')::integer
        )
    );

-- Cells: visibility inherited from parent list + ownership checks
CREATE POLICY cells_visibility_select ON cells
    FOR SELECT
    USING (
        EXISTS (
            SELECT 1 FROM lists
            WHERE lists.id = cells.list_id
            AND (
                current_setting('app.is_admin', true) = 'true'
                OR lists.visibility = 'public'
                OR (lists.visibility IN ('protected', 'private')
                    AND current_setting('app.current_role', true) = 'coach')
            )
        )
    );

CREATE POLICY cells_ownership_update ON cells
    FOR UPDATE
    USING (
        (player_id = NULLIF(current_setting('app.current_user_id', true), '')::integer
         AND EXISTS (SELECT 1 FROM lists WHERE id = cells.list_id AND visibility = 'public'))
        OR current_setting('app.current_role', true) = 'coach'
    );
```

**Critical:** These RLS policies are **not sufficient alone**. The application layer must also check visibility before rendering edit buttons and form targets. Double-check in handlers.

### Pattern 3: Ownership & Visibility Check in Handlers

**What:** Before allowing a cell edit, verify both (1) player owns the cell and (2) list visibility permits edit.

**When to use:** Every POST to `list_row_edit_handler.php` for both coach and player paths.

**Example:**

```php
// Source: src/db/visibility.php (new helper file)

/**
 * Check if a user can edit a cell (player editing own row or coach editing any row in appropriate list).
 * Returns true only if all conditions met:
 * 1. User is authenticated (has user_id in session)
 * 2. List visibility allows editing (public/protected for coaches; public only for players)
 * 3. If player: can only edit own cells (player_id == $_SESSION['user_id'])
 * 4. If coach: can edit any cell in lists of their team
 */
function can_edit_cell(PDO $pdo, int $list_id, int $column_id, int $player_id): bool {
    $pdo = get_db();
    reset_rls_context($pdo);
    set_admin_context($pdo); // Bypass RLS to check rules explicitly

    // Get list visibility and team context
    $stmt = $pdo->prepare(
        "SELECT visibility, team_id FROM lists WHERE id = ?"
    );
    $stmt->execute([$list_id]);
    $list = $stmt->fetch();

    if (!$list) {
        return false; // List not found
    }

    // Verify user is in correct team
    if ((int)$_SESSION['team_id'] !== (int)$list['team_id']) {
        return false; // Cross-team access attempt
    }

    // Coach: can edit in public or protected lists
    if (($_SESSION['role'] ?? '') === 'coach') {
        return in_array($list['visibility'], ['public', 'protected']);
    }

    // Player: can edit only own cells in public lists
    if (($_SESSION['role'] ?? '') === 'player') {
        return $list['visibility'] === 'public' && (int)$_SESSION['user_id'] === $player_id;
    }

    return false; // Unknown role
}

/**
 * Check if a user can view a list (see it in the list overview and open its detail view).
 */
function can_view_list(PDO $pdo, int $list_id): bool {
    $pdo = get_db();
    reset_rls_context($pdo);
    set_admin_context($pdo);

    $stmt = $pdo->prepare(
        "SELECT visibility, team_id FROM lists WHERE id = ?"
    );
    $stmt->execute([$list_id]);
    $list = $stmt->fetch();

    if (!$list || (int)$list['team_id'] !== (int)$_SESSION['team_id']) {
        return false;
    }

    // Player can see only public lists
    if (($_SESSION['role'] ?? '') === 'player') {
        return $list['visibility'] === 'public';
    }

    // Coach can see all lists in their team
    if (($_SESSION['role'] ?? '') === 'coach') {
        return true;
    }

    return false;
}
```

### Pattern 4: Column Metadata Query (Type Validation)

**What:** Before accepting a cell value, fetch column metadata and validate against `data_type`.

**When to use:** Every cell POST submission (coach or player row edit).

**Example:**

```php
// Source: src/coach/list_row_edit_handler.php (excerpt)

// POST handler for row edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $list_id   = (int)$_REQUEST['list_id'] ?? 0;
    $player_id = (int)$_REQUEST['player_id'] ?? 0;
    
    if (!can_edit_cell($pdo, $list_id, -1, $player_id)) {
        http_response_code(403);
        die('Nicht berechtigt.');
    }
    
    // For each column submitted in the form:
    foreach (($_POST['cells'] ?? []) as $column_id => $raw_value) {
        $column_id = (int)$column_id;
        
        // Fetch column metadata to validate against type
        $col_stmt = $pdo->prepare(
            "SELECT id, data_type FROM columns 
             WHERE id = ? AND (team_id = ? OR list_id = ?)"
        );
        $col_stmt->execute([$column_id, $_SESSION['team_id'], $list_id]);
        $column = $col_stmt->fetch();
        
        if (!$column) {
            // Column doesn't exist or not in this team/list
            http_response_code(400);
            die('Spalte nicht gefunden.');
        }
        
        // Validate based on data_type
        $validated_value = null;
        switch ($column['data_type']) {
            case 'boolean':
                $validated_value = $raw_value ? '1' : '0';
                break;
            case 'number':
                if (!filter_var($raw_value, FILTER_VALIDATE_INT) &&
                    !filter_var($raw_value, FILTER_VALIDATE_FLOAT)) {
                    // Invalid number — skip or error
                    continue;
                }
                $validated_value = $raw_value;
                break;
            case 'text':
                if (strlen($raw_value) > 255) {
                    continue; // Text too long
                }
                $validated_value = $raw_value;
                break;
        }
        
        // Upsert cell
        $stmt = $pdo->prepare(
            "INSERT INTO cells (list_id, column_id, player_id, value)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (list_id, column_id, player_id)
             DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
        );
        $stmt->execute([$list_id, $column_id, $player_id, $validated_value]);
    }
    
    redirect("/coach/lists/$list_id?success=1");
}
```

### Anti-Patterns to Avoid

- **Inline cell editing without dedicated form page:** D-06 explicitly requires separate edit page; don't use AJAX inline editing or modal dialogs.
- **Rendering edit buttons without visibility checks:** D-14 says only render "Edit" for cells user can actually edit; check `can_edit_cell()` before rendering button.
- **Storing visibility state per cell instead of per list:** Visibility is a list-level property; don't duplicate on every cell row.
- **Trusting RLS alone for authorization:** RLS is defense-in-depth, but application layer MUST also check visibility before rendering forms and processing POST. Never assume RLS will catch all bad requests.
- **Not validating column data_type on cell writes:** Malicious POST could submit text to a boolean column; validate type against schema before INSERT/UPDATE.
- **Forgetting to set RLS context in player handlers:** If implementing `/player` routes, must call `set_team_context()` after `require_auth()` — similar to coach handlers.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Form validation for numbers / booleans | Custom regex or string checks | `filter_var()` with FILTER_VALIDATE_INT/FLOAT | Built-in, handles edge cases (locales, scientific notation), timing-safe |
| Column ordering / sorting in query | Manual PHP array sorting after fetch | SQL `ORDER BY sort_order, created_at` in query | O(n) in PHP vs. O(log n) in DB; DB can index; wrong sort order in UI if client-side |
| Visibility enforcement for rows | Check in PHP loop after fetching | RLS policy + application-layer `can_view_list()` check | RLS prevents accidental disclosure if query is wrong; app layer can be audited separately |
| CSRF protection on forms | Custom token generation | Existing `csrf_field()` helper | Already implemented, timing-safe comparison, session-backed |
| Pagination for large tables | Manual OFFSET/LIMIT + UI controls | Bootstrap table-responsive (horizontal scroll) on initial implementation | Phase 3 scope is ~50 players per team; pagination not needed yet; responsive scroll is simpler |

**Key insight:** EAV with weak visibility checks is the #1 footgun here. A coach accidentally sees a player's cells in a private list due to a missing RLS policy or a forgotten visibility check in the handler = data leak. Use the `can_edit_cell()` and `can_view_list()` helpers in **every** handler that touches lists or cells.

## Common Pitfalls

### Pitfall 1: Confusing Global vs. Local Column Scopes

**What goes wrong:** A coach creates a global column "Tore", then a local column "Tore" in a specific list. When querying cells, the handler joins columns without distinguishing scope, returns wrong values, or displays duplicate column names in the table.

**Why it happens:** Columns table uses list_id to mark global (NULL) vs. local (not NULL). Queries without proper scoping group by (column.id) instead of (column.id, column.list_id), collapsing distinct columns.

**How to avoid:** 
- Always query: `SELECT * FROM columns WHERE (list_id = ? OR (list_id IS NULL AND team_id = ?)) ORDER BY list_id IS NULL DESC, sort_order`
- Global columns first (list_id IS NULL), then local (list_id = ?).
- Never rely on column name uniqueness; use column.id as the primary key.

**Warning signs:** "Why does the table show two 'Tore' columns?" or "Values are appearing in the wrong column" in list detail view.

### Pitfall 2: Visibility State Not Synced to RLS Context Variable

**What goes wrong:** Handler checks `can_view_list()` but forgot to call `set_team_context()` before the RLS query, so RLS blocks the row and query returns empty.

**Why it happens:** PHP-FPM connection pooling means session from a prior request's RLS context can bleed into the next request if not explicitly reset and reset.

**How to avoid:** 
- Every handler must follow this pattern: `require_coach()` or `require_auth()` (which sets team context) → query → `reset_rls_context()` if temporarily bypassing RLS.
- Call `reset_rls_context()` right after an admin context query (e.g., visibility check lookup).
- Verify `set_team_context()` is called in `require_coach()` and `require_player()` (new for Phase 3).

**Warning signs:** Queries return empty but the row exists in the DB (check psql directly). Or: "I'm a coach but can't see my own lists."

### Pitfall 3: Cell Values Not Validated Against Column Type

**What goes wrong:** Coach submits form with a text value for a boolean column (e.g., "maybe"). App inserts the string "maybe" into the cell. Player later sees "maybe" instead of 0/1. Statistics query breaks because it tries to SUM "maybe".

**Why it happens:** Dynamic column types require runtime validation. Easy to skip if distracted or testing with hand-crafted POST requests.

**How to avoid:**
- Before every cell INSERT/UPDATE, query the column metadata and validate value against `data_type` enum.
- Use `filter_var()` for numbers, ternary for boolean (`$val ? '1' : '0'`), `strlen()` for text.
- Unit test cell validation with invalid inputs (boundary cases: negative numbers, text that is "numeric-like").

**Warning signs:** Stats page shows "Cannot SUM string value" or column displays nonsensical values.

### Pitfall 4: Edit Button Rendered Without Ownership/Visibility Check

**What goes wrong:** Template renders "Edit" button for every row in the table. Player sees their own "Edit" button AND a coach's "Edit" button (or coach sees a player's "Edit" button). Clicking it triggers a 403 or accesses the wrong player's data.

**Why it happens:** Template loops `foreach ($rows as $row)` and unconditionally renders `<button>Edit</button>`. Application checks are applied only in the POST handler, not in the GET template render.

**How to avoid:**
- In the GET handler (list detail), pre-compute a `$can_edit_by_player` array before passing to template.
- Template checks: `if ($can_edit_by_player[$row['player_id']] ?? false) { echo '<button>'; }`
- Or call `can_edit_cell()` inline in template with same parameters (slower but explicit).
- Always check BOTH player ownership AND visibility state.

**Warning signs:** Player sees an "Edit" button for another player's row, or coach sees an "Edit" button for a row in a private list.

### Pitfall 5: Local Column Type Validation Allows "Text" for Global Columns

**What goes wrong:** Coach creates a global column "Notes" with type "text". App inserts into columns table successfully. Later, coach tries to use this global column in another list; statistics query assumes only boolean/number, breaks.

**Why it happens:** Global columns should have `data_type IN ('boolean', 'number')`. Local columns allow 'text'. Validation in the create handler might not check `list_id IS NULL → forbid 'text'`.

**How to avoid:**
- In columns_create_handler.php: if `list_id IS NULL` (global column), enforce `data_type IN ('boolean', 'number')` explicitly: `if (empty($_POST['list_id']) && $_POST['data_type'] === 'text') { $error = '...'; }`
- In list_detail_handler.php: when adding local column, allow 'text' but only if `list_id` is set.

**Warning signs:** Coach creates a global "Notes" column, tries to use it in a list, then runs statistics — stats query fails with "cannot aggregate text".

## Code Examples

Verified patterns from codebase and proposed Phase 3 handlers:

### List Overview (Coach) — Handler Pattern

```php
// Source: src/coach/lists_handler.php (Phase 3 — new)

declare(strict_types=1);

require_coach();

$pdo = get_db();

// Fetch lists for this team (RLS context already set by require_coach)
$stmt = $pdo->prepare(
    "SELECT 
        lists.id, 
        lists.name, 
        lists.visibility,
        COUNT(DISTINCT columns.id) as column_count,
        COUNT(DISTINCT users.id) as player_count
    FROM lists
    LEFT JOIN columns ON (columns.list_id = lists.id OR 
                         (columns.list_id IS NULL AND columns.team_id = lists.team_id))
    CROSS JOIN users  -- all players in team
    WHERE lists.team_id = ?
    GROUP BY lists.id
    ORDER BY lists.created_at DESC"
);
$stmt->execute([$_SESSION['team_id']]);
$lists = $stmt->fetchAll();

$error = !empty($_GET['error']) ? e($_GET['error']) : '';
$success = !empty($_GET['success']) ? 'Liste aktualisiert.' : '';

require ROOT_PATH . '/src/templates/coach/layout.php';

render_coach_page('Listen', 'lists', function() use ($lists, $error, $success) {
    if ($error) echo '<div class="alert alert-danger">' . $error . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    require ROOT_PATH . '/src/templates/coach/lists.php';
});
```

### List Table with Row Edit Button (Coach) — Template Pattern

```php
// Source: src/templates/coach/list_detail.php (Phase 3 — excerpt)

<?php
// $list: the list object (id, name, visibility)
// $rows: array of all players [id, first_name, last_name, ...]
// $columns: array of column metadata sorted (global first, then local)
// $cells: map of [player_id][column_id] => cell value
?>

<div class="table-responsive">
    <table class="table table-sm table-hover">
        <thead>
            <tr>
                <th>Spieler</th>
                <?php foreach ($columns as $col): ?>
                    <th><?= e($col['name']) ?></th>
                <?php endforeach; ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <?php foreach ($columns as $col): ?>
                <td>
                    <?= htmlspecialchars(($cells[$row['id']][$col['id']] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </td>
                <?php endforeach; ?>
                <td>
                    <a href="/coach/lists/<?= (int)$list['id'] ?>/rows/<?= (int)$row['id'] ?>/edit"
                       class="btn btn-sm btn-outline-primary min-touch">
                        Bearbeiten
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

### Cell Form Submission — Handler Pattern

```php
// Source: src/coach/list_row_edit_handler.php (Phase 3 — POST excerpt)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $list_id   = (int)($_POST['list_id'] ?? 0);
    $player_id = (int)($_POST['player_id'] ?? 0);
    $pdo = get_db();
    
    // Double-check access before allowing save
    if (!can_edit_cell($pdo, $list_id, 0, $player_id)) {
        redirect("/coach/lists/$list_id?error=" . urlencode('Nicht berechtigt.'));
    }
    
    // Process each submitted cell
    foreach (($_POST['cells'] ?? []) as $column_id => $raw_value) {
        $column_id = (int)$column_id;
        
        // Validate against column type
        $col_stmt = $pdo->prepare(
            "SELECT data_type FROM columns 
             WHERE id = ? AND team_id = ?"
        );
        $col_stmt->execute([$column_id, $_SESSION['team_id']]);
        $column = $col_stmt->fetch();
        
        if (!$column) continue; // Column not found or not in team
        
        // Type-specific validation
        $validated_value = null;
        switch ($column['data_type']) {
            case 'boolean':
                $validated_value = $raw_value ? '1' : '0';
                break;
            case 'number':
                if (filter_var($raw_value, FILTER_VALIDATE_INT) ||
                    filter_var($raw_value, FILTER_VALIDATE_FLOAT)) {
                    $validated_value = $raw_value;
                }
                break;
            case 'text':
                if (strlen($raw_value) <= 255) {
                    $validated_value = $raw_value;
                }
                break;
        }
        
        if ($validated_value !== null) {
            // Upsert cell
            $stmt = $pdo->prepare(
                "INSERT INTO cells (list_id, column_id, player_id, value)
                 VALUES (?, ?, ?, ?)
                 ON CONFLICT (list_id, column_id, player_id)
                 DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
            );
            $stmt->execute([$list_id, $column_id, $player_id, $validated_value]);
        }
    }
    
    redirect("/coach/lists/$list_id?success=1");
}
```

### Player Session & Auth — Handler Pattern

```php
// Source: src/auth/session.php (Phase 3 — new require_player function)

/**
 * Require a player session. Similar to require_coach but role-specific.
 * Called at the top of every /player/* handler.
 */
function require_player(): void {
    check_session_timeout();
    if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'player') {
        redirect('/login');
    }
    $pdo = get_db();
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id']);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Hardcoded columns in table schema | EAV (columns + cells tables) | Phase 3 design | Coaches can add columns without schema migration; enables flexible team-specific data models |
| Form submission via inline modal | Dedicated page with GET/POST pattern (PRG) | Phase 2 established; continued Phase 3 | Cleaner separation of concerns; form state explicitly in URL; prevents double-submission |
| Single user role (coach) | Multiple roles (admin, coach, player) | Phase 1–2; extended Phase 3 | Enables permission model; RLS can enforce per-role visibility |
| Visibility enforced only in app | Visibility enforced in RLS + app | Phase 1 design principle; extended Phase 3 | Defense-in-depth; query-level filtering prevents accidental disclosure |

**Deprecated/outdated:**
- No deprecated PHP/PostgreSQL patterns in this stack — it's modern (PHP 8.3+, PostgreSQL 14+, native prepared statements).
- Phase 3 will not introduce breaking changes to Phase 1–2 code.

## Open Questions

1. **Column reordering UI:** CONTEXT.md defers column reordering to v2. For Phase 3, should we include a hidden `sort_order` column that defaults to insertion order (created_at), or require explicit sort_order entry?
   - **What we know:** D-03 says "global columns first, then local" but doesn't specify ordering within each group.
   - **What's unclear:** Should coaches be able to manually reorder columns in Phase 3, or only see them in insertion order?
   - **Recommendation:** Use `sort_order` (integer, default 0) and `created_at` as tiebreaker in queries. Coaches cannot reorder in Phase 3 (no UI), but infrastructure is ready for v2.

2. **Player redirect after login:** login_handler.php currently redirects to `/player` (returns 404). Should this redirect to `/player/lists` (home of public lists)?
   - **What we know:** D-13 specifies `/player/lists` as the player home.
   - **Recommendation:** Update login_handler.php: `redirect('/player/lists')` instead of `redirect('/player')`.

3. **Number column storage:** Should number columns accept only integers, or floats as well?
   - **What we know:** CONTEXT.md mentions "Zahl" (number) type but doesn't specify integer vs. float.
   - **Recommendation:** Accept both; store as TEXT in cells, validate with `filter_var(..., FILTER_VALIDATE_INT)` OR `filter_var(..., FILTER_VALIDATE_FLOAT)`. Statistics in Phase 4 can sum both.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHP built-in; PSR-12 assertions or custom test files in /tests |
| Config file | N/A — Phase 1–2 had no formal test suite; Phase 3 may introduce tests as complexity grows |
| Quick run command | `php tests/test_eav_model.php` (once created) |
| Full suite command | Manual per-handler testing via browser or curl (or PHPUnit if added) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| LIST-01 | Coach creates list with name and default visibility | integration | Test GET/POST /coach/lists/create | ❌ Wave 0 |
| LIST-02 | Coach creates global column (boolean/number only) | integration | Test POST /coach/columns/create with type validation | ❌ Wave 0 |
| LIST-03 | Coach creates local column in list (boolean/number/text) | integration | Test POST /coach/lists/{id}/columns/create | ❌ Wave 0 |
| LIST-04 | List has visibility state (public/protected/private) | unit | Validate schema CHECK constraint | ✅ schema.sql |
| LIST-05 | Coach changes visibility of existing list | integration | Test POST /coach/lists/{id}/settings with new visibility | ❌ Wave 0 |
| CELL-01 | Player edits only own row in public lists | integration | Test player POST /player/lists/{id}/rows/{own_id}/edit (403 if not own or not public) | ❌ Wave 0 |
| CELL-02 | Coach edits all rows in public/protected lists | integration | Test coach POST /coach/lists/{id}/rows/{any_id}/edit (403 if private) | ❌ Wave 0 |
| CELL-03 | Private lists invisible to players | integration | Test player GET /player/lists/xxx (returns 404 or filters out) | ❌ Wave 0 |
| CELL-04 | User sees all rows but edits only allowed | integration | Test GET /coach/lists/{id} shows all rows; only own row has edit button (player) | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** Manual test: create list → add global/local columns → edit cell in public list (coach + player)
- **Per wave merge:** All 9 requirements tested end-to-end
- **Phase gate:** All tests pass before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/test_list_creation.php` — covers LIST-01, LIST-04
- [ ] `tests/test_column_creation.php` — covers LIST-02, LIST-03
- [ ] `tests/test_cell_visibility.php` — covers CELL-01, CELL-02, CELL-03, CELL-04
- [ ] `database/schema.sql` — tables `lists`, `columns`, `cells` with proper constraints
- [ ] `database/rls_policies.sql` — policies for visibility filtering
- [ ] `src/db/visibility.php` — helper functions `can_view_list()`, `can_edit_cell()`

## Sources

### Primary (HIGH confidence)
- **Codebase review:** 
  - `src/auth/session.php` — RLS context management patterns (set_team_context, reset_rls_context)
  - `src/coach/players_handler.php` — handler pattern (require_coach, PRG redirect)
  - `src/coach/player_create_handler.php` — form handling + credential modal pattern
  - `src/templates/coach/layout.php` — render_coach_page signature; nav item pattern
  - `database/schema.sql` — existing table structure (users, teams)
  - `database/rls_policies.sql` — RLS policy examples (team_isolation)
  - `public/index.php` — router pattern for handler dispatch
  - `src/utils/csrf.php` — CSRF token helpers
  - `src/utils/helpers.php` — utility functions (e(), redirect(), generate_username, generate_random_password)

- **CONTEXT.md (Phase 3 discussion):**
  - D-01 to D-15: Locked decisions on navigation, table rendering, edit pages, accessibility rules, player area layout

- **REQUIREMENTS.md (Phase 3 requirements):**
  - LIST-01 to LIST-05: List and column management
  - CELL-01 to CELL-04: Cell editing and visibility rules

- **STATE.md (project state):**
  - Architecture principles (Team Isolation, Visibility Centralization, Ownership Checks)
  - Pitfalls from Phases 1–2 (session stale state, credentials in logs, type confusion in dynamic data)

- **CLAUDE.md (project instructions):**
  - Stack: PHP 8.3+, PostgreSQL 14+, Bootstrap 5.3 CDN, no framework
  - Constraints: Mobile-first, German UI, no email, single admin
  - Established patterns: PDO with native prepared statements, password_hash(), session with OWASP config

### Secondary (MEDIUM confidence)
- **PostgreSQL documentation:** EAV pattern is well-documented; JSON/JSONB support mentioned in CLAUDE.md as rationale for Postgres choice; RLS is native PostgreSQL feature (documentation at postgresql.org)
- **Bootstrap 5 documentation:** `table-responsive` class and `.badge` component are standard Bootstrap utilities (getbootstrap.com)
- **PHP built-in functions:** `filter_var()`, `password_hash()`, `password_verify()`, `htmlspecialchars()`, `session_*` functions are all PHP core (php.net/manual)

### Tertiary (confidence: validation needed)
- None — all findings verified against codebase or official docs.

## Metadata

**Confidence breakdown:**
- **Standard stack:** HIGH — PHP/PostgreSQL with existing patterns verified in codebase
- **Architecture (EAV + RLS):** HIGH — design aligns with STATE.md architecture principles; patterns from Phase 1–2 established
- **Pitfalls:** HIGH — identified from Phase 1–2 lessons + specific Phase 3 risks (type validation, visibility enforcement)
- **Validation Architecture:** MEDIUM — no existing test suite in codebase; framework choice deferred; test cases inferred from requirements

**Research date:** 2026-04-30  
**Valid until:** 2026-05-07 (7 days — medium-volatility domain; minimal library updates expected, but column type validation and RLS policy details may shift if team structure changes)

**Known limitations:**
- Number column float vs. integer distinction not yet decided — flagged as Open Question #3.
- Player session middleware (`require_player()`) inferred from `require_coach()` pattern; exact implementation deferred to planning phase.
- Test framework choice (PHPUnit vs. custom assertions vs. manual browser testing) deferred to planning phase.
