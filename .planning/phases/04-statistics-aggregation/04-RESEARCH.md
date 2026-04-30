# Phase 4: Statistics & Aggregation — Research

**Researched:** 2026-04-30  
**Domain:** SQL aggregation, statistics querying, leaderboard filtering  
**Confidence:** HIGH

## Summary

Phase 4 implements per-player statistics aggregation across all lists. Coaches access a statistics page (`/coach/stats`) showing all players with aggregated metrics computed from global columns (sum for number-type columns, count of true values for boolean-type columns), plus a team-wide leaderboard sortable by any global column. Players access their own statistics page (`/player/stats`) showing only their row. The phase reuses existing visibility logic (players see public/protected lists only; coaches see all) and leverages PostgreSQL's native GROUP BY and aggregate functions for performance.

**Primary recommendation:** Use PostgreSQL GROUP BY with SUM/COUNT aggregate functions in prepared statements, filter visibility at the SQL layer via RLS and application logic, and render both tables using Bootstrap's `.table-responsive` for mobile compatibility. No custom aggregation logic needed — push computation to the database.

---

## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01:** Coach statistics page `/coach/stats` uses Bootstrap table with responsive horizontal scrollbar — consistent with Phase 3 list views.

**D-02:** Coaches see all list types (public, protected, private) in their statistics calculation.

**D-03:** Leaderboard (STAT-03) appears on the same `/coach/stats` page below the player statistics table — not a separate page.

**D-04:** Players have separate `/player/stats` page showing only one row (their own statistics).

**D-05:** Player statistics include only public and protected lists — private lists excluded.

**D-06:** Coach and player navigation includes new "Statistik" entry pointing to respective stats pages.

### Claude's Discretion

- Exact SQL aggregation queries (GROUP BY, JOINs on `list_global_columns` + `cells`)
- STAT-02 filtering details: dropdown for list selection, date range filter implementation (based on `lists.created_at` or `cells.updated_at`)
- Leaderboard: column selection dropdown, GET-parameter routing for active sort column
- Empty state handling: show empty values or dashes when player has no entries
- Number formatting: integer vs. float display per column type

### Deferred Ideas (OUT OF SCOPE)

- CSV export of statistics (Backlog: EXT-V2-01)
- Column type changes after data entry (Backlog: LIST-V2-01)
- Detailed UX for date-range picker vs. preset periods

---

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| STAT-01 | Per-player statistics page aggregating global columns (SUM for numbers, COUNT of true for booleans) across all relevant lists | PostgreSQL GROUP BY with SUM/COUNT aggregates; application layer filters by visibility + player |
| STAT-02 | Statistics filterable by specific lists or date range | SQL WHERE clause on `lists.created_at` or `cells.updated_at`; GET-parameter filtering in handler |
| STAT-03 | Team-wide leaderboard sorted by any global column | GROUP BY query with ORDER BY, dropdown selector for active sort column, GET-parameter routing |

---

## Standard Stack

### Core Aggregation
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PostgreSQL | 14+ | GROUP BY, SUM(), COUNT() aggregates | Native, zero-dependency; proven performant for team-scale data; already deployed |
| PDO prepared statements | Built-in (PHP 8.3+) | Parameterized aggregate queries | Prevents SQL injection; framework-agnostic; existing pattern in codebase |

### Presentation
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Bootstrap 5.3 | CDN | `.table-responsive` for responsive table layout | Already in use; mobile-first; no build dependency |
| Native PHP templating | N/A | HTML output for statistics tables | Lightweight; consistent with existing handlers |

### Integration
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `src/db/visibility.php` | Phase 3 | `can_view_list()` helper | Reuse existing logic for list visibility checks during aggregation |
| `src/db/connection.php` | Phase 3 | `set_team_context()` for RLS | Already sets `app.current_role` + `app.current_user_id`; enables visibility-filtered queries |
| `src/auth/session.php` | Phase 1 | `require_coach()`, `require_player()` | Middleware for role-gating; establishes RLS context |

### Why NOT Custom Solutions
| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Aggregate computation | Custom row-by-row loops in PHP | PostgreSQL GROUP BY + SUM/COUNT | Database-side aggregation is orders of magnitude faster; avoids transferring raw data over network |
| Visibility filtering for stats | PHP loops through list visibility checks per row | RLS policies on lists + `can_view_list()` in WHERE clause | Centralized, debuggable, enforced at DB layer; consistent with Phase 3 |
| Leaderboard sorting | In-memory sort after fetch | SQL ORDER BY in aggregate query | Single query to DB; no sorting overhead in PHP; database query planner optimizes automatically |

---

## Architecture Patterns

### Recommended Query Structure for STAT-01 & STAT-02

```sql
-- Coach view: all lists in team; players see only public/protected
SELECT 
    u.id AS player_id,
    u.first_name,
    u.last_name,
    c.id AS column_id,
    c.name AS column_name,
    c.data_type,
    CASE 
        WHEN c.data_type = 'number' THEN SUM(CAST(cells.value AS NUMERIC))
        WHEN c.data_type = 'boolean' THEN COUNT(CASE WHEN cells.value = 'true' THEN 1 END)
    END AS aggregated_value
FROM users u
LEFT JOIN cells ON u.id = cells.player_id
LEFT JOIN lists ON cells.list_id = lists.id
LEFT JOIN columns c ON cells.column_id = c.id
WHERE 
    u.team_id = $team_id
    AND c.list_id IS NULL  -- Global columns only
    AND (
        -- Visibility logic: coach sees all; player sees public/protected
        $role = 'coach' 
        OR (
            $role = 'player' 
            AND lists.visibility IN ('public', 'protected')
        )
    )
    -- Optional: date range filter on cells.updated_at or lists.created_at
GROUP BY u.id, u.first_name, u.last_name, c.id, c.name, c.data_type
ORDER BY u.first_name, u.last_name, c.sort_order;
```

**Key design points:**
- Uses `LEFT JOIN` on `cells` to include players with no entries (shows NULL/empty)
- Filters on `c.list_id IS NULL` to select global columns only
- Visibility filter (`lists.visibility`) applied in WHERE clause — works with RLS
- CASE expression handles column-type-specific aggregation (SUM vs. COUNT)
- GROUP BY ensures one row per player per column

### Leaderboard Query (STAT-03)

```sql
-- Single column leaderboard: order by player's aggregate value in chosen column
SELECT 
    u.id AS player_id,
    u.first_name,
    u.last_name,
    CASE 
        WHEN $active_column_type = 'number' THEN SUM(CAST(cells.value AS NUMERIC))
        WHEN $active_column_type = 'boolean' THEN COUNT(CASE WHEN cells.value = 'true' THEN 1 END)
    END AS rank_value
FROM users u
LEFT JOIN cells ON u.id = cells.player_id
LEFT JOIN lists ON cells.list_id = lists.id
LEFT JOIN columns c ON cells.column_id = c.id AND c.id = $active_column_id
WHERE 
    u.team_id = $team_id
    AND c.list_id IS NULL  -- Global column
    AND ($role = 'coach' OR lists.visibility IN ('public', 'protected'))
GROUP BY u.id, u.first_name, u.last_name
ORDER BY rank_value DESC NULLS LAST;
```

### Router Integration Pattern

```php
// public/index.php — new routes for Phase 4

$path === '/coach/stats'
    => require ROOT_PATH . '/src/coach/stats_handler.php',

$path === '/player/stats'
    => require ROOT_PATH . '/src/player/stats_handler.php',
```

### Handler Pattern (Coach Statistics)

```php
// src/coach/stats_handler.php

declare(strict_types=1);

require_coach();  // Enforce role, set RLS context

$pdo = get_db();
$team_id = (int)$_SESSION['team_id'];
$list_filter = (int)($_GET['list_id'] ?? 0);  // Optional: filter by specific list
$date_from = $_GET['date_from'] ?? null;      // Optional: start date
$date_to = $_GET['date_to'] ?? null;          // Optional: end date

// Build aggregation query with optional filters
$sql = "SELECT ... FROM users u LEFT JOIN cells ...";
$params = [$team_id];

if ($list_filter > 0) {
    $sql .= " AND cells.list_id = ?";
    $params[] = $list_filter;
}

if ($date_from) {
    $sql .= " AND cells.updated_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $sql .= " AND cells.updated_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Leaderboard: fetch single-column sort
$leaderboard_column_id = (int)($_GET['sort_by'] ?? 0);  // Active leaderboard sort column
$leaderboard_sql = "SELECT ... ORDER BY rank_value DESC";
$leaderboard_stmt = $pdo->prepare($leaderboard_sql);
$leaderboard_stmt->execute([$team_id, $leaderboard_column_id]);
$leaderboard = $leaderboard_stmt->fetchAll(PDO::FETCH_ASSOC);

render_coach_page('Statistik', 'stats', function() use ($statistics, $leaderboard) {
    // Render statistics table + leaderboard
});
```

### Navigation Updates

**Coach layout (`src/templates/coach/layout.php`):**
```php
// Add to nav items:
<li class="nav-item">
    <a class="nav-link <?= $active === 'stats' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
       href="/coach/stats">
        <i class="bi bi-graph-up me-2"></i>Statistik
    </a>
</li>
```

**Player layout (`src/templates/player/layout.php`):**
```php
// Add to nav items:
<li class="nav-item">
    <a class="nav-link <?= $active === 'stats' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
       href="/player/stats">
        <i class="bi bi-graph-up me-2"></i>Statistik
    </a>
</li>
```

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Statistics computation | Loop through cells, manually SUM/COUNT in PHP | PostgreSQL GROUP BY + aggregate functions | Database aggregation 100–1000× faster; avoids memory overhead and network round-trips |
| Visibility filtering | Fetch all lists, check visibility in PHP loop | RLS on lists table + WHERE clause filtering | Enforced at DB layer; prevents accidental data leakage; matches Phase 1–3 patterns |
| Leaderboard sorting | Fetch all players, sort in-memory with usort() | SQL ORDER BY with aggregate query | Query planner optimizes; single database round-trip; no PHP sorting overhead |
| Date range parsing | Manual string parsing of date inputs | PDO parameterization + PostgreSQL date casting | Native type safety; SQL injection prevention; handles invalid dates gracefully |

**Key insight:** The codebase already uses RLS for access control and prepared statements for SQL injection prevention. Phase 4 doubles down on these patterns — aggregate computation is too expensive to do in PHP when PostgreSQL's built-in functions are available.

---

## Common Pitfalls

### Pitfall 1: Incorrect Visibility Filtering in Aggregation
**What goes wrong:** Coach aggregation includes private lists (correct), but player aggregation accidentally includes private lists (data leak).  
**Why it happens:** Copy-paste of coach query without adjusting WHERE clause for `lists.visibility IN ('public', 'protected')`.  
**How to avoid:** Create separate handler functions for coach and player aggregation; make visibility filter explicit in each query.  
**Warning signs:** Player sees statistics values that appear in their private team notes but not in their editable lists.

### Pitfall 2: NULL Handling in Aggregates
**What goes wrong:** Players with no cell entries show as completely blank (NULL) rather than "0" or "—".  
**Why it happens:** LEFT JOIN returns NULL when no matching cells exist; aggregate functions (SUM, COUNT) then return NULL instead of 0.  
**How to avoid:** Use `COALESCE(SUM(...), 0)` in query or handle NULL display in template with `$value ?? '—'`.  
**Warning signs:** Statistics table has empty cells instead of zero or dash.

### Pitfall 3: Query Performance Regression
**What goes wrong:** Aggregation query times out with 100+ players and 1000+ cells.  
**Why it happens:** Missing indexes on `cells(player_id, column_id, list_id)` or `lists(team_id, visibility)`.  
**How to avoid:** Ensure indexes exist (schema.sql already has them; verify before execution). Run EXPLAIN ANALYZE on aggregation query. Monitor query time during testing.  
**Warning signs:** Coach statistics page takes > 5 seconds to load; browser timeout.

### Pitfall 4: Type Mismatch on CAST
**What goes wrong:** Number column with mixed text/numeric values causes `CAST ... AS NUMERIC` to fail.  
**Why it happens:** Cell values stored as TEXT; invalid numbers (e.g., "abc") cause exception.  
**How to avoid:** Application layer validates number input before INSERT (Phase 3 responsibility). For aggregation, use `TRY_CAST` (PostgreSQL 16+) or wrap in error handling.  
**Warning signs:** Query execution exception when number column contains non-numeric values; affects all queries, not just stats.

### Pitfall 5: RLS Leakage on Aggregation Query
**What goes wrong:** Player accidentally sees statistics for private lists (filtered by app, but RLS bypass during admin context).  
**Why it happens:** Prior request set `app.is_admin = 'true'`; RLS context not reset before player query.  
**How to avoid:** `require_player()` calls `set_team_context()` which resets admin context first. Verify reset_rls_context() is called at handler start.  
**Warning signs:** Player stats show values from private lists after an admin interaction.

---

## Code Examples

### Example 1: Coach Statistics Query (STAT-01)
**Source:** PostgreSQL documentation + Phase 3 RLS patterns

```sql
-- Prepare statement in PHP:
$pdo->prepare("
    SELECT 
        u.id AS player_id,
        u.first_name,
        u.last_name,
        c.id AS column_id,
        c.name AS column_name,
        c.data_type,
        COALESCE(
            CASE 
                WHEN c.data_type = 'number' THEN SUM(CAST(cells.value AS NUMERIC))
                WHEN c.data_type = 'boolean' THEN COUNT(CASE WHEN cells.value = '1' OR cells.value = 'true' THEN 1 END)
                ELSE NULL
            END,
            0
        ) AS aggregated_value
    FROM users u
    CROSS JOIN (
        SELECT DISTINCT id, name, data_type 
        FROM columns 
        WHERE team_id = ? AND list_id IS NULL
    ) c
    LEFT JOIN cells ON u.id = cells.player_id AND c.id = cells.column_id
    LEFT JOIN lists ON cells.list_id = lists.id
    WHERE u.team_id = ? AND u.role = 'player'
    GROUP BY u.id, u.first_name, u.last_name, c.id, c.name, c.data_type
    ORDER BY u.first_name, u.last_name, c.sort_order
");

// Execute:
$stmt->execute([$team_id, $team_id]);
```

**Why CROSS JOIN?** Ensures every player appears for every global column, even if no cells exist (NULL aggregates become 0 via COALESCE).

### Example 2: Player Statistics Query (STAT-01, Visibility-Filtered)
**Source:** Extends coach query with visibility constraint

```sql
-- Same as coach query, but add WHERE clause:
WHERE u.team_id = ? 
  AND u.role = 'player'
  AND u.id = ?  -- Only own player
  AND (
      lists.visibility IN ('public', 'protected')
      OR lists.id IS NULL  -- No list match = global column, always visible
  )
```

### Example 3: Leaderboard Query (STAT-03)
**Source:** Phase 3 + PostgreSQL ORDER BY patterns

```sql
-- GET parameter: sort_by column_id
SELECT 
    u.id AS player_id,
    u.first_name,
    u.last_name,
    COALESCE(
        CASE 
            WHEN ? = 'number' THEN SUM(CAST(cells.value AS NUMERIC))
            WHEN ? = 'boolean' THEN COUNT(CASE WHEN cells.value = '1' OR cells.value = 'true' THEN 1 END)
        END,
        0
    ) AS rank_value
FROM users u
LEFT JOIN cells ON u.id = cells.player_id
LEFT JOIN lists ON cells.list_id = lists.id
LEFT JOIN columns c ON cells.column_id = c.id AND c.id = ?
WHERE u.team_id = ? 
  AND c.list_id IS NULL
GROUP BY u.id, u.first_name, u.last_name
ORDER BY rank_value DESC NULLS LAST;

// Execute:
$stmt->execute([$column_data_type, $column_data_type, $column_id, $team_id]);
```

### Example 4: Bootstrap Statistics Table (Mobile-Responsive)
**Source:** Bootstrap 5.3 documentation

```php
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead class="table-light">
            <tr>
                <th>Spieler</th>
                <?php foreach ($global_columns as $col): ?>
                    <th><?= e($col['name']) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statistics as $player): ?>
                <tr>
                    <td class="fw-semibold"><?= e($player['first_name'] . ' ' . $player['last_name']) ?></td>
                    <?php foreach ($global_columns as $col): ?>
                        <td>
                            <?php 
                                $value = $player_stats[$player['id']][$col['id']] ?? null;
                                echo $value !== null ? e((string)$value) : '—';
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

### Example 5: Filter Form (STAT-02)
**Source:** Phase 3 filter patterns (GET-based, PRG pattern)

```php
<form method="get" class="row g-2 mb-4">
    <div class="col-auto">
        <label for="list_filter" class="form-label">Liste</label>
        <select name="list_id" id="list_filter" class="form-select form-select-sm">
            <option value="">Alle Listen</option>
            <?php foreach ($available_lists as $list): ?>
                <option value="<?= $list['id'] ?>" <?= (int)($_GET['list_id'] ?? 0) === $list['id'] ? 'selected' : '' ?>>
                    <?= e($list['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="col-auto">
        <label for="date_from" class="form-label">Von</label>
        <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" 
               value="<?= e($_GET['date_from'] ?? '') ?>">
    </div>
    
    <div class="col-auto">
        <label for="date_to" class="form-label">Bis</label>
        <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" 
               value="<?= e($_GET['date_to'] ?? '') ?>">
    </div>
    
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filtern</button>
        <a href="/coach/stats" class="btn btn-sm btn-outline-secondary">Zurücksetzen</a>
    </div>
</form>
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Manual row-by-row aggregation in PHP | PostgreSQL GROUP BY + native aggregates | SQL standard (always) | 100–1000× faster; database query planner optimizes; zero PHP memory overhead |
| Separate pagination/limiting logic | Built-in LIMIT/OFFSET in aggregate query | SQL standard | Single query to DB; fewer round-trips; better performance on large datasets |
| Materialized statistics views (pre-compute) | On-demand queries with indexes | Modern databases (2010s+) | Simpler maintenance; always up-to-date; no stale cache issues for small teams |
| Form-based date filtering | GET-parameter date filters (PRG pattern) | Web standards (REST) | Bookmarkable, cacheable, shareable URLs; consistent with Phase 3 approach |

**Deprecated/outdated:**
- Custom reporting tools (Tableau, Metabase, etc.): Overkill for single admin app; native SQL sufficient
- Pagination via OFFSET (high cardinality): Team-manager scale (few 100s of players) doesn't warrant cursor-based pagination
- Scheduled batch aggregation: No time-critical stats needed; on-demand queries sufficient

---

## Validation Architecture

**Skip:** `workflow.nyquist_validation` is explicitly set to `false` in `.planning/config.json` — no test framework required for this phase.

---

## Open Questions

1. **Date filtering semantics (STAT-02)**
   - What we know: CONTEXT.md defers implementation details to Claude; filter is either `lists.created_at` or `cells.updated_at`
   - What's unclear: Should "date range" filter on when the list was created or when data was entered?
   - Recommendation: Use `cells.updated_at` — more intuitive for coaches ("show stats for entries from Jan 1–31"). Lists created before that date still contribute cells if they were updated in-range.

2. **Empty state for players with zero entries**
   - What we know: D-05 mentions "show empty values, no error"
   - What's unclear: Display as "0", "—", or blank?
   - Recommendation: Use "—" (em-dash) for non-numeric columns (booleans); "0" for numeric columns. Matches accounting/sports leaderboard convention.

3. **Leaderboard ranking ties**
   - What we know: STAT-03 requires sorting by column value
   - What's unclear: How to display tied values (alphabetical by name, or leave as-is)?
   - Recommendation: Leave as-is (stable sort by query result order); if needed later, add secondary sort by player name in ORDER BY clause.

4. **Performance monitoring baseline**
   - What we know: Query uses INDEX on `cells(player_id, column_id, list_id)` and `lists(team_id, visibility)`
   - What's unclear: What query time is acceptable? (5s? 1s? 100ms?)
   - Recommendation: Target < 500ms for typical team (50 players, 20 lists, 5 global columns). Monitor with `time` command during QA.

---

## Sources

### Primary (HIGH confidence)
- [PostgreSQL: Aggregate Functions](https://www.postgresql.org/docs/current/tutorial-agg.html) — Official docs on GROUP BY, SUM(), COUNT() semantics
- [Bootstrap 5.3: Tables](https://getbootstrap.com/docs/5.3/content/tables/) — Official guide on `.table-responsive` and responsive layouts
- [PHP: PDO Prepared Statements](https://www.php.net/manual/en/pdo.prepare.php) — Official PHP reference for parameterized queries

### Secondary (MEDIUM confidence)
- [REST API Design: Filtering, Sorting, and Pagination](https://www.moesif.com/blog/technical/api-design/REST-API-Design-Filtering-Sorting-and-Pagination/) — Industry best practices for GET-parameter filtering and sorting
- [W3Schools: Bootstrap Responsive Tables](https://www.w3schools.com/howto/howto_css_table_responsive.asp) — Practical guide to horizontal scrolling on mobile
- [Tania Rascia: REST API Sorting, Filtering, Pagination](https://www.taniarascia.com/rest-api-sorting-filtering-pagination/) — Comprehensive guide to filter parameter conventions

### Code references
- `.planning/phases/01-foundation/01-CONTEXT.md` — RLS patterns, session handling
- `.planning/phases/03-lists-columns-cells/03-CONTEXT.md` — EAV schema, visibility logic
- `src/db/visibility.php` — `can_view_list()` helper for authorization checks
- `src/db/connection.php` — RLS context setup; `set_team_context()` signature

---

## Metadata

**Confidence breakdown:**
- Standard stack (PostgreSQL + PDO): **HIGH** — both deployed and tested in Phases 1–3; documentation current
- Architecture patterns (GROUP BY queries, RLS): **HIGH** — PostgreSQL best practices well-documented; Phase 3 established RLS patterns
- Pitfalls (NULL handling, visibility leakage): **HIGH** — grounded in PostgreSQL documentation + codebase review
- Open questions: **MEDIUM** — deferred to Claude's discretion per CONTEXT.md; design choices straightforward once constraints clarified

**Research date:** 2026-04-30  
**Valid until:** 2026-05-14 (14 days — stable domain, low churn)
