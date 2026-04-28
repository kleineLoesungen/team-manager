# Architecture Patterns

**Domain:** PHP/PostgreSQL team management web application
**Researched:** 2026-04-28
**Confidence:** HIGH (based on established PHP/PostgreSQL patterns, RBAC standards, database design for dynamic schemas)

## Recommended Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                      Frontend (Mobile-First)                    │
│  HTML/CSS - No JS Framework - Responsive Design                │
│  - Dashboard / Lists / Statistics / Admin Views                 │
└─────────────────┬───────────────────────────────────────────────┘
                  │ HTTP Requests/Responses
┌─────────────────▼───────────────────────────────────────────────┐
│             Application Layer (PHP)                             │
│  ┌──────────────────┐  ┌──────────────────┐                     │
│  │ Auth & Session   │  │ Request Router   │                     │
│  │ (Credential mgmt)│  │ (RBAC check)     │                     │
│  └──────────────────┘  └──────────────────┘                     │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │            Business Logic Layer                           │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │  │
│  │  │Team Mgmt     │  │Player Mgmt   │  │List Mgmt     │   │  │
│  │  │(Team/Trainer)│  │(Trainer)     │  │(Trainer)     │   │  │
│  │  └──────────────┘  └──────────────┘  └──────────────┘   │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐   │  │
│  │  │Row Operations│  │Statistics    │  │Column Mgmt   │   │  │
│  │  │(Player/Coach)│  │(Aggregation) │  │(Coach)       │   │  │
│  │  └──────────────┘  └──────────────┘  └──────────────┘   │  │
│  └───────────────────────────────────────────────────────────┘  │
│                                                                  │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │            Data Access Layer (Queries)                    │  │
│  │  - User/Auth queries                                      │  │
│  │  - Team/Player queries with visibility filtering          │  │
│  │  - List/Column/Row queries (dynamic schema access)        │  │
│  │  - Statistics computation queries                         │  │
│  └───────────────────────────────────────────────────────────┘  │
└─────────────────┬───────────────────────────────────────────────┘
                  │ SQL
┌─────────────────▼───────────────────────────────────────────────┐
│            PostgreSQL Database                                  │
│  - Users (with roles)                                           │
│  - Teams / Coaches / Players                                    │
│  - Lists / Columns (global + local) / Rows / Values             │
│  - Visibility rules (public/protected/private)                  │
└─────────────────────────────────────────────────────────────────┘
```

## Component Boundaries

### 1. Authentication & Session Management
**Responsibility:** User authentication, session handling, credential generation

**Owned by:** Core Auth Module
- Admin authentication (hardcoded credentials check)
- Trainer/Player authentication (database lookup)
- Session creation and lifecycle
- Password reset workflow (on-screen display only)
- Credential generation (random username/password)

**Communicates with:**
- PostgreSQL User table
- Request Router (for permission checks)

**Key consideration:** Single-sign-on per team; credentials never email-exposed

---

### 2. Request Router & Authorization
**Responsibility:** Route requests to handlers, enforce RBAC at controller level

**Owned by:** Router/Middleware layer
- Parse incoming request (GET/POST/action)
- Extract session user and their role
- Check permissions before delegating to business logic
- Enforce team isolation (user can only see/modify own team's data)
- Return 403 if permission denied

**Communicates with:**
- Authentication (user context)
- All business logic modules (after authorization)

**Key consideration:** Stateless checking; role + team context defines access

---

### 3. Team Management
**Responsibility:** Team creation, trainer assignment, team-level settings

**Owned by:** Admin interface (restricted to config-defined admin)
- Create teams
- Assign trainers to teams
- Team metadata (name, sport, season, etc.)
- Deactivate/archive teams

**Communicates with:**
- PostgreSQL Teams/Trainers/Players tables
- Player Management (for bulk operations)

**Key consideration:** Single admin; all team ops are one-to-one responsibility

---

### 4. Player Management
**Responsibility:** Player CRUD, role assignment, team membership

**Owned by:** Trainer-facing module
- Create players (assign to trainer's team)
- Deactivate players
- Update player metadata (name, position, etc.)
- Bulk import/export capability (future)

**Communicates with:**
- PostgreSQL Players table
- Column Management (to ensure global columns exist for new players)
- Row Operations (to create initial rows for new players in existing lists)

**Key consideration:** Trainers manage only their own team's players

---

### 5. List Management
**Responsibility:** Create, configure, and manage lists (spreadsheet containers)

**Owned by:** Trainer-facing module
- Create lists (Spiel, Training, Trainingscamp, etc.)
- Set list visibility (public/protected/private)
- Configure which global/local columns appear
- Delete/archive lists
- Duplicate lists (copy structure + columns)

**Communicates with:**
- PostgreSQL Lists table
- Column Management (for column assignment)
- Row Operations (to initialize rows when list created)

**Key consideration:** Lists are team-scoped; visibility affects who can read/edit rows

---

### 6. Column Management
**Responsibility:** Define and manage global and local columns

**Owned by:** Trainer-facing module
- **Global columns** (team-level, all lists)
  - Create/modify column with type (Boolean, Number)
  - Assign to team (reusable across lists)
  - Column metadata (name, unit, data type)
- **Local columns** (list-level only)
  - Create/modify column with type (Boolean, Number, Text)
  - Assign to specific list
  - Column metadata (name, data type)

**Communicates with:**
- PostgreSQL Columns table (with list_id NULL for global)
- List Management (for list-scoped operations)
- Row Operations (for data validation when entering values)
- Statistics (for aggregation logic)

**Key consideration:** Global columns are pre-computed in statistics; local columns are not

---

### 7. Row Operations
**Responsibility:** Manage individual player rows and cell values (the "spreadsheet cells")

**Owned by:** Shared Player/Trainer module (visibility-aware)
- **Trainer perspective:** Can view and edit all rows in their lists (based on visibility)
- **Player perspective:** Can only view/edit own row (if list is public/protected)
- Add/edit/delete cell values for a row
- Bulk row operations (import, copy, clear)

**Communicates with:**
- PostgreSQL Rows/Values tables
- List Management (for visibility rules)
- Column Management (for type validation)
- Statistics (triggers recomputation on change)

**Key consideration:** Cell-level granularity; values stored as TEXT with implicit typing based on column

---

### 8. Statistics & Aggregation
**Responsibility:** Compute and display per-player statistics across all lists

**Owned by:** Report/Analytics module
- Query all rows across all visible lists for a player
- For each **global column**, compute:
  - Boolean columns: COUNT(WHERE value = true)
  - Number columns: SUM(CAST(value AS numeric))
- Return per-player statistics dashboard
- Cache for performance (if needed)

**Communicates with:**
- PostgreSQL Rows/Values/Columns/Lists tables
- Row Operations (triggers refresh when data changes)

**Key consideration:** Only global columns are aggregated; local columns excluded; visibility affects which lists contribute to stats

---

### 9. Visibility & Access Control
**Responsibility:** Enforce row/list-level visibility rules

**Owned by:** Request Router + Row Operations (cross-cutting concern)
- **Public lists:** Players can read + edit own row; trainers can read/edit all rows
- **Protected lists:** Players can read only (all rows); trainers can read/edit all rows
- **Private lists:** Only trainers can read/edit; players cannot see
- Team isolation: Never show data from other teams

**Communicates with:**
- All modules requiring data access
- Authentication (user context)

**Key consideration:** Visibility rules are checked **per request**, not cached; dynamic based on user + resource

---

## Data Flow

### User Login Flow
```
Browser → POST /login {username, password}
  → Auth Module: hash password, check database
  → Create session {user_id, role, team_id}
  → Redirect to dashboard
  → (Session stored in PHP $_SESSION or cookie)
```

### Coach Creates List Flow
```
Coach → GET /lists/create-form
  → Router: Check role=coach, team_id from session
  → Return HTML form
Coach → POST /lists/create {name, visibility, columns[]}
  → Router: Authorize (coach of this team)
  → List Manager: Create list record
  → Column Manager: Link global columns to list
  → Row Operations: Initialize rows for all team players
  → Redirect to list view
```

### Player Views/Edits Own Row (Public List)
```
Player → GET /list/{list_id}
  → Router: Authorize (player, team_id=session.team_id)
  → List Manager: Check visibility=public
  → Row Operations: Fetch all rows (player sees own row + can edit, sees others read-only)
  → Return HTML table with visibility rules applied
Player → POST /row/{row_id}/cell/{column_id} {value}
  → Router: Authorize (player, owns row? visibility allows?)
  → Column Manager: Validate type (Boolean/Number/Text)
  → Row Operations: Insert/update value in database
  → Statistics: Trigger recomputation for this player (async or sync)
  → Return updated cell
```

### Coach Views Statistics
```
Coach → GET /statistics
  → Router: Authorize (coach of this team)
  → Statistics Module: Query all global columns for team
  → Statistics Module: For each player in team:
    → Fetch all rows they appear in (all lists)
    → Compute SUM/COUNT per global column
    → Filter by visibility rules (only include public/protected lists)
  → Return dashboard with per-player stats
```

### Admin Resets Trainer Password
```
Admin → GET /admin/teams/{team_id}/coaches/{coach_id}
  → Router: Check hardcoded admin credential
  → Return coach detail page
Admin → POST /admin/coaches/{coach_id}/reset-password
  → Router: Authorize (admin only)
  → Auth Module: Generate new random password
  → Auth Module: Hash and store in database
  → Return password on-screen (no email)
  → Log action in audit trail (optional)
```

## Database Schema (Key Tables)

### Core Tables

**users**
```sql
id (PK)
team_id (FK to teams) — except admin
username (unique)
password_hash
role (admin, coach, player)
name (full name)
active (boolean)
created_at
updated_at
```

**teams**
```sql
id (PK)
name
sport (optional)
season (optional)
active
created_at
updated_at
```

**lists**
```sql
id (PK)
team_id (FK)
name
purpose (Spiel, Training, etc. — advisory only)
visibility (public, protected, private)
created_by (FK to users/coach)
created_at
updated_at
```

**columns**
```sql
id (PK)
team_id (FK) — for global columns
list_id (FK) — for local columns; NULL if global
name
data_type (boolean, number, text)
is_global (boolean, denormalized for query simplicity)
sort_order
created_at
```

**rows**
```sql
id (PK)
list_id (FK)
player_id (FK to users)
created_at
updated_at
```

**values** — Core cell data
```sql
id (PK)
row_id (FK)
column_id (FK)
value (TEXT — implicit type based on column.data_type)
created_at
updated_at
```

### Optional Supporting Tables

**audit_log** — For tracking password resets, list changes
```sql
id
user_id (who acted)
action (reset_password, create_list, etc.)
resource_type
resource_id
timestamp
```

**column_assignments** — If list can selectively include/exclude global columns
```sql
id
list_id (FK)
column_id (FK)
is_included (boolean)
```

## Component Build Order

### Phase 1: Foundation (Authentication, Users, Teams)
**Why first:** Everything depends on knowing who the user is and which team they belong to

1. **Database schema** (users, teams, basic tables)
2. **Auth module** (login, session, credential generation)
3. **Request router** (RBAC middleware, session extraction)
4. **Admin interface** (create teams, assign coaches — foundation for everything)

**Deliverable:** Admin can create teams and assign coaches; coaches can log in

---

### Phase 2: Core Data Model (Players, Lists, Columns)
**Why second:** Once auth works, build the data structures trainers manage

1. **Player management** (CRUD for team's players)
2. **Column management** (define global/local columns)
3. **List management** (create lists, assign columns)
4. **Row initialization** (when list/player created, ensure rows exist)

**Deliverable:** Trainers can create players, lists, and columns; empty spreadsheet structure ready

---

### Phase 3: Cell Operations (The Spreadsheet)
**Why third:** Data model ready; now implement editing

1. **Row operations** (cell read/write with type validation)
2. **Visibility enforcement** (public/protected/private checks at read/write)
3. **Bulk operations** (import, copy cells, etc. — if needed early)

**Deliverable:** Trainers and players can view/edit cells within visibility rules

---

### Phase 4: Statistics & Reporting
**Why fourth:** Requires complete row data; independent of further changes

1. **Statistics aggregation** (SUM/COUNT global columns)
2. **Statistics dashboard** (per-player view)
3. **Caching** (optional, if performance needed)

**Deliverable:** Statistics page shows accurate global column aggregates per player

---

### Phase 5: Polish & Performance
**Why last:** Core flow complete; optimize and refine

1. **Audit logging** (track password resets, list changes)
2. **Query optimization** (N+1 fixes, indexes on team_id, visibility)
3. **Error handling** (graceful failures, user feedback)
4. **Mobile UX refinement** (responsive tables, touch-friendly controls)

---

## Key Database Design Patterns

### 1. Dynamic Column Schema
**Pattern:** Use EAV (Entity-Attribute-Value) table structure instead of ALTER TABLE

- **columns** table defines structure
- **values** table stores all cell data (row_id, column_id, value)
- Avoids schema migrations when coaches add columns
- Trades query complexity for operational flexibility

**Query example:** Fetch row 42 with all cell values
```sql
SELECT c.id, c.name, c.data_type, v.value
FROM columns c
LEFT JOIN values v ON c.id = v.column_id AND v.row_id = 42
WHERE c.team_id = $team_id OR (c.list_id IN (SELECT id FROM lists WHERE team_id = $team_id))
ORDER BY c.sort_order;
```

### 2. Visibility-Aware Queries
**Pattern:** Always filter by team_id + visibility rules in WHERE clause

Never trust the user interface; enforce at query layer:
```sql
SELECT r.*, v.value
FROM rows r
JOIN values v ON r.id = v.row_id
WHERE r.list_id = $list_id
  AND EXISTS (
    SELECT 1 FROM lists l
    WHERE l.id = r.list_id
    AND l.team_id = $user_team_id
    AND (
      l.visibility = 'private' AND $user_role = 'coach'
      OR l.visibility IN ('protected', 'public')
    )
  );
```

### 3. Global Column Aggregation
**Pattern:** Pre-compute or query-time aggregate, cache if slow

At statistics query time:
```sql
SELECT 
  p.id, p.name,
  COALESCE(COUNT(CASE WHEN c.data_type = 'boolean' AND v.value = 'true' THEN 1 END), 0) as total_booleans,
  COALESCE(SUM(CASE WHEN c.data_type = 'number' THEN CAST(v.value AS numeric) ELSE 0 END), 0) as total_numbers
FROM users p
LEFT JOIN rows r ON p.id = r.player_id
LEFT JOIN values v ON r.id = v.row_id
LEFT JOIN columns c ON v.column_id = c.id
WHERE p.team_id = $team_id
  AND c.is_global = true
  AND r.list_id IN (
    SELECT id FROM lists WHERE team_id = $team_id AND visibility IN ('public', 'protected')
  )
GROUP BY p.id, p.name;
```

### 4. Team Isolation
**Pattern:** Every query includes team_id filter; deny by default

```sql
-- Admin bypass: check hardcoded credentials
-- Trainer/Player: always filter by session.team_id
SELECT * FROM users WHERE team_id = $session_team_id AND id = $requested_user_id;
```

## Patterns to Follow

### Pattern 1: Dependency Injection for Database Access
**What:** Pass database connection/query builder to modules, don't use globals

**When:** Every module that queries
**Example:**
```php
class ListManager {
  private $db;
  public function __construct(PDO $db) {
    $this->db = $db;
  }
  public function createList($team_id, $name, $visibility, $columns) {
    // Use $this->db for queries
  }
}
```

### Pattern 2: Request Context Object
**What:** Pass user context (id, role, team_id) through the request lifecycle

**When:** Every handler
**Example:**
```php
class RequestContext {
  public $user_id;
  public $user_role; // admin, coach, player
  public $team_id;
}
// In handler:
function handleListCreate(RequestContext $context, $list_data) {
  // Use $context->team_id for isolation
}
```

### Pattern 3: Authorization Checks Before Business Logic
**What:** Check permissions before querying or modifying data

**When:** Every request that modifies or accesses team/player data
**Example:**
```php
// In Router/Middleware:
if ($context->user_role !== 'coach') {
  return 403; // Deny before calling ListManager
}
$list_manager->createList($context->team_id, $list_data);
```

### Pattern 4: Explicit Type Validation on Cell Input
**What:** Validate cell values against column.data_type before insert

**When:** Every cell write
**Example:**
```php
function setCellValue($column_id, $value) {
  $column = $this->getColumn($column_id);
  if ($column->data_type === 'number' && !is_numeric($value)) {
    throw new ValidationException('Expected number');
  }
  if ($column->data_type === 'boolean' && !in_array($value, ['true', 'false'])) {
    throw new ValidationException('Expected true/false');
  }
  // Insert $value as TEXT
}
```

### Pattern 5: Visibility Rules as Query Filters
**What:** Never return restricted data; filter at SQL layer

**When:** Every read query
**Example:**
```php
function getListRows($list_id, RequestContext $context) {
  // Query includes: WHERE team_id = $context->team_id AND visibility checks
}
```

## Anti-Patterns to Avoid

### Anti-Pattern 1: Storing Passwords in Plain Text
**What:** Hardcoding or storing passwords without hashing
**Why bad:** Breach exposes all credentials
**Instead:** Use password_hash() and password_verify(); hash admin password too

### Anti-Pattern 2: Client-Side Authorization
**What:** Hiding UI elements based on role, but not enforcing server-side
**Why bad:** Clever users can bypass and access restricted data
**Instead:** Every server request checks role + context; assume client is untrusted

### Anti-Pattern 3: ALTER TABLE for New Columns
**What:** Modifying schema every time a trainer adds a column
**Why bad:** Migrations are complex, slow, risk table locks
**Instead:** Use EAV pattern (values table) for dynamic columns

### Anti-Pattern 4: Unscoped Queries
**What:** Writing queries without team_id filter
**Why bad:** Data leaks across teams
**Instead:** Every query includes WHERE team_id = $context->team_id (except admin)

### Anti-Pattern 5: Caching Visibility-Dependent Data
**What:** Caching list rows without considering who's reading
**Why bad:** Privacy leak if cache key doesn't include user role
**Instead:** Don't cache row data, or include (user_id, list_id) in cache key

### Anti-Pattern 6: Statistics Computed in Application Code
**What:** Fetching all rows in PHP and summing in a loop
**Why bad:** Slow, doesn't scale, inefficient memory
**Instead:** Aggregate in SQL; let PostgreSQL do the math

## Scalability Considerations

| Concern | At 100 Users (10 teams) | At 10K Users (100 teams) | At 1M Users (10K teams) |
|---------|------------------------|--------------------------|-------------------------|
| **List & Row Count** | ~1K lists, ~10K rows | ~100K lists, ~1M rows | ~10M lists, ~100M rows |
| **Query Performance** | Simple index on team_id + list_id | Add partial indexes (visibility), analyze statistics | Consider table partitioning by team_id, read replicas |
| **Statistics Computation** | Query-time acceptable (~100ms) | Cache per player (~1s refresh) | Pre-compute, batch job (hourly refresh) |
| **Database Size** | ~100MB | ~1-5GB | ~100-500GB |
| **Architecture Impact** | Single DB sufficient | Separate read replica for stats | Sharding by team_id, dedicated stats cache layer (Redis) |
| **Session Storage** | PHP $_SESSION (files) | Shared session store (Redis/Memcache) | Distributed session store |

### Current Project Scalability
For a greenfield team management app with expected ~10-50 teams in Year 1, start with:
- Single PostgreSQL database
- Query-time statistics (no caching)
- Indexes on team_id, user_id, list_id, column_id
- No caching layer initially; add Redis if stats queries exceed 500ms

## Build Order Implications

**Phase 1-2 teams can build in parallel:**
- Authentication is independent
- Schema can be designed upfront

**Phase 2-3 can overlap slightly:**
- Column/List management can proceed while Row Ops are being built
- Pre-define column types to avoid mid-stream schema changes

**Phase 4 depends on Phase 3:**
- Statistics queries require complete row data with visibility rules in place
- Don't build stats aggregation until visibility enforcement is tested

**Phase 5 is optimization-only:**
- Don't prematurely optimize queries; profile first
- Add indexes only after identifying slow queries (EXPLAIN ANALYZE)

## Recommended Implementation Approach

### No Framework, Procedural PHP with Dependency Injection
**Why:**
- Avoids heavyweight framework overhead
- Explicit request routing and handler mapping
- Easy to understand for solo developer
- Straightforward deployment (no build step)

**Structure:**
```
/src
  /handlers        (request handlers, 1 per action)
  /managers        (business logic: ListManager, PlayerManager, etc.)
  /db              (query builders, EAV pattern utilities)
  /auth            (Auth module)
  /utils           (helpers, validators, type checking)
/public
  /index.php       (single entry point router)
  /assets          (CSS, minimal JS)
/database
  /migrations      (schema setup, one-time DDL)
  /seeds           (test data)
```

**Router pattern:**
```php
// index.php
$action = $_GET['action'] ?? 'dashboard';
$context = new RequestContext($_SESSION);

switch ($action) {
  case 'login':
    $handler = new LoginHandler($db);
    $handler->handle($_POST, $context);
    break;
  case 'list_create':
    if ($context->user_role !== 'coach') { http_response_code(403); exit; }
    $handler = new ListCreateHandler($db);
    $handler->handle($_POST, $context);
    break;
  // ... etc
}
```

This keeps code simple, testable, and avoids magic.

---

## Summary

**Core Architecture:**
- **Frontend:** Mobile-first HTML/CSS, stateless requests
- **Application:** Procedural PHP with role-based request router
- **Data Access:** Parametric queries with EAV pattern for dynamic columns
- **Database:** PostgreSQL with team isolation, visibility rules at query layer

**Component Boundaries:**
- Auth & Sessions (standalone)
- Team/Player/List/Column Managers (business logic modules)
- Row Operations (cell-level access, visibility-aware)
- Statistics (aggregation, read-only)
- Router/Middleware (RBAC enforcement)

**Build Order:**
1. Foundation (Auth, Router, Teams, Players)
2. Data Model (Columns, Lists, Rows)
3. Cell Operations (Visibility enforcement)
4. Statistics (Aggregation)
5. Polish (Logging, Performance, UX)

**Key Design Decisions:**
- EAV pattern for columns/rows (flexible schema)
- Visibility checks at query layer (security by default)
- Team_id in every query (isolation guaranteed)
- PostgreSQL aggregation for statistics (not PHP loops)
- Single database, single admin (no multi-tenant complexity)

This architecture prioritizes simplicity, security (defense in depth), and mobile-first UX while remaining scalable to 100+ teams without fundamental restructuring.
