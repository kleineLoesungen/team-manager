# Phase 7: Live-Ticker — Öffentlicher Event-Ticker mit Kurznachrichten - Research

**Researched:** 2026-07-26  
**Domain:** Public event ticker with short messages, auto-reload polling, team-managed tags  
**Confidence:** HIGH

## Summary

Phase 7 extends the Team Manager application with a public-facing event ticker system. Coordinators create tickers (active/closed), post messages with optional tags, and share editor privileges with selected members. The ticker is accessible without authentication and auto-refreshes every 5 seconds using vanilla JavaScript polling. Messages display newest-first with team-configurable color-coded tags. The implementation builds on existing patterns (PRG, RLS via `set_team_context()`, CSRF, Bootstrap templates) and introduces minimal new complexity: four new database tables, four new handler files, three new template files, and a character-limit validation.

**Primary recommendation:** Implement polling via vanilla `setTimeout(() => location.reload(), 5000)` (browser-native, no framework), store ticker state in PostgreSQL with row-level security via team_id, and use Bootstrap badges for tag styling. Avoid WebSockets, AJAX, and real-time libraries — cost outweighs benefit for 5-second polling interval.

## User Constraints (from CONTEXT.md)

### Locked Decisions

**D-01: Auto-Reload via Vanilla JS**  
Auto-reload implemented as `setTimeout(() => location.reload(), 5000)` on public ticker view. No meta-refresh (would reset scroll position).

**D-02: Silent Auto-Update Hint**  
Stateless text "Wird automatisch aktualisiert…" in muted color (`text-muted`) below ticker header. No countdown timer or animation.

**D-03: Auto-Reload Only When Active**  
Auto-reload JavaScript runs ONLY when ticker status is `'active'`. Closed tickers display static content without reload.

**D-04: Posting Restricted to Auth Area**  
Posting form available ONLY in authenticated sections (coordinator + member areas). Public view is read-only, even when user is logged in.

**D-05: Reverse Chronological Message Order**  
Messages display newest-first. New messages appear at top; no scroll required.

**D-06: Message Format: Timestamp + Optional Tag**  
Each message shows: timestamp (auto-set, editable) + optional tag/category as colored Bootstrap badge. No author name displayed.

**D-07: Character Limit: 280 per Message**  
Database: `VARCHAR(280)`. Form includes JS counter (`"42/280"`).

**D-08: Tags Optional**  
Message may have no tag; badge only renders if tag selected.

**D-09: Team-Wide Tag Configuration**  
Coordinator configures tags on Settings page (new section "Ticker-Tags"). Tags: team-scoped, with custom label + color.

**D-10: "Spalten" Renamed to "Einstellungen"**  
Existing `/coordinator/columns` → `/coordinator/settings`. "Spalten" becomes subsection. New "Ticker-Tags" section added.

**D-11: Member Freigabe via Checkboxes**  
Ticker edit form includes member checkboxes. Freigabe is per-ticker, not global.

**D-12: Shared Editing Rights**  
Coordinator + freigegeben members: can create, edit, delete ALL messages (no row-level ownership).

**D-13: Member Post Area**  
Members post in member area (`/member/ticker`). New nav entry in member sidebar.

**D-14: Member Ticker View: Feed + Form**  
Single page shows live feed (like public view) + post form combined (no separate page for posting).

### Claude's Discretion

- **Coordinator entry point:** New nav entry "Ticker" in sidebar + mobile tab (analog to existing)
- **DB schema:** Four tables outlined in CONTEXT (see Database Design section)
- **URL schema:** Nine routes `/coordinator/ticker*`, `/member/ticker*`, `/ticker*`, `/coordinator/settings`
- **Public overview:** Active tickers prominent (top), closed tickers muted (below)
- **Login-page integration:** Link position/styling for ticker links
- **Tag color mapping:** Bootstrap classes (`bg-success`, `bg-warning`, `bg-danger`, `bg-primary`, `bg-secondary`)

### Deferred Ideas (OUT OF SCOPE)

- Push notifications on new messages
- WebSocket real-time updates
- Message reactions/likes
- Ticker search/filtering

---

## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| TICKER-01 | Coordinator creates, closes, deletes tickers; tickers have name, description, active/closed status | DB schema: `tickers` table; handlers: `/coordinator/ticker/new`, `/coordinator/ticker/{id}`, `/coordinator/ticker/{id}/close`, `/coordinator/ticker/{id}/delete` |
| TICKER-02 | Coordinator + freigegeben members post short messages (280 chars max) with optional tag; messages editable/deletable | DB schema: `ticker_messages` + `ticker_members` join table; handlers: `/coordinator/ticker/{id}` POST, `/member/ticker/{id}` POST; JS counter validation |
| TICKER-03 | Authorization: Coordinator always can edit ticker + post; members must be in `ticker_members` join to post; public cannot post | RLS via `set_team_context()` + `ticker_members` join check; no Coordinator-level RBAC needed (single table per team) |
| TICKER-04 | Public view accessible without login; read-only; uses team_id isolation, not auth | Public routes `/ticker` (list), `/ticker/{id}` (feed) with no `require_coordinator()` / `require_member()` guard; `set_team_context($pdo, $team_id)` isolates data |
| TICKER-05 | Auto-reload every 5 sec (active ticker only); silent hint text; browser-native polling | Vanilla JS `setTimeout()` in template conditional on `status === 'active'`; static hint text below header |
| TICKER-06 | Public ticker overview page (`/ticker`) + links on login page (active + closed) | Public routes; Links on login template; Active tickers listed first, closed below |

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.3+ | Server-side application logic | Current stable; security patches; session/form handling; PDO native to runtime |
| PostgreSQL | 14+ | Relational database with RLS | ACID compliance; row-level security for team isolation; team_id-scoped queries enforced at DB level |
| PDO (PDO_PGSQL) | Built-in | Database access + prepared statements | Prevents SQL injection; consistent interface; no ORM overhead for simple CRUD |
| Bootstrap 5 | 5.3+ via CDN | Mobile-first CSS + badges/cards | Pre-built components (forms, badges, cards); no build step; responsive; widespread browser support |
| Native PHP templating | N/A | HTML output + form rendering | No additional dependencies; direct control; suitable for straightforward templates |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Vanilla JavaScript | ES6 (native browser) | Auto-reload + form validation | Character counter (`oninput`); `setTimeout()` polling; no framework needed for simple interactions |
| Bootstrap badges | 5.3+ CDN classes | Color-coded tag display | `bg-success`, `bg-warning`, `bg-danger`, `bg-primary`, `bg-secondary` for tag categories |
| htmlspecialchars() | Built-in PHP | Output escaping | Prevent XSS on message text, tag labels, ticker name/description |
| password_hash() / password_verify() | Built-in (PHP 5.5+) | Password hashing | Already in use project-wide; no change needed for Phase 7 |
| session_start() | Built-in (PHP 7.1+) | Session management | Existing project pattern; user auth for coordinator/member areas |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Vanilla JS polling every 5s | WebSockets (real-time) | WebSocket requires persistent server connection; shared hosting (Hetzner) doesn't guarantee; 5s latency acceptable for user experience |
| Vanilla JS polling every 5s | AJAX fetch + DOM replace | AJAX maintains scroll position but adds JS complexity; full page reload simpler, acceptable for small message feeds |
| Bootstrap badges for tags | Custom CSS color classes | Bootstrap badges: pre-tested, accessible, mobile-responsive; custom CSS adds maintenance burden |
| Character limit validation (JS + DB) | DB-only (VARCHAR(280)) | JS counter provides immediate feedback; DB limit (VARCHAR) is safety net against malformed submissions |
| Single `timestamp` column (auto-set) | Separate `created_at` + `updated_at` | Use single `timestamp` field (editableafter creation); `created_at` not needed for this feature; simplify schema |

**Installation:**
```bash
# No new packages required for Phase 7
# Existing stack covers all functionality (PHP, PostgreSQL, Bootstrap, Vanilla JS)
```

---

## Architecture Patterns

### Recommended Project Structure (Phase 7 additions)

```
src/
├── coordinator/
│   ├── ticker_handler.php          # GET /coordinator/ticker (list)
│   ├── ticker_detail_handler.php   # GET/POST /coordinator/ticker/{id} (detail + post)
│   ├── ticker_create_handler.php   # GET/POST /coordinator/ticker/new
│   └── ticker_close_handler.php    # POST /coordinator/ticker/{id}/close
│   └── ticker_delete_handler.php   # POST /coordinator/ticker/{id}/delete
│   └── settings_handler.php        # Renamed from columns_handler.php; adds ticker-tags section
├── member/
│   ├── ticker_handler.php          # GET /member/ticker (list of freigegeben tickers)
│   └── ticker_detail_handler.php   # GET/POST /member/ticker/{id} (feed + post)
├── templates/
│   ├── coordinator/
│   │   ├── ticker.php              # Coordinator ticker list
│   │   ├── ticker_form.php         # Create/edit form (reused for new + edit)
│   │   ├── ticker_detail.php       # Ticker detail + message list + post form
│   │   ├── ticker_delete_confirm.php # Two-step delete confirmation
│   │   └── settings.php            # Renamed from columns.php; includes ticker-tags section
│   ├── member/
│   │   ├── ticker_list.php         # Member ticker list (freigegeben tickers)
│   │   └── ticker_detail.php       # Member view: feed + post form combined
│   └── public/
│       ├── ticker_overview.php     # Public /ticker overview
│       └── ticker_detail.php       # Public /ticker/{id} feed (with auto-reload script)
├── db/
│   └── visibility.php              # (Existing) Add ticker-specific visibility helpers if needed
└── utils/
    └── helpers.php                 # (Existing) Extend with e() for tag/message escaping
database/
└── schema.sql                      # Add four new tables: tickers, ticker_messages, ticker_members, ticker_tags
```

### Pattern 1: Public Endpoint Without Authentication

**What:** Public routes (`/ticker`, `/ticker/{id}`) with no `require_coordinator()` guard. Uses `set_team_context($pdo, $team_id)` to isolate data by team.

**When to use:** Read-only public endpoints that need team-scoped data isolation without user authentication.

**Example:**

```php
<?php
// src/public/ticker_handler.php — Public ticker overview

// No require_coordinator() or require_member()
// Team context set manually from URL or inferred

$team_id = (int)($_GET['team'] ?? 1); // Or derive from URL slug
$pdo = new PDO(...);

// Set team context for RLS — all subsequent queries filtered by team_id
set_team_context($pdo, $team_id, null, null);

$tickers = $pdo->query(
    "SELECT id, name, description, status, created_at FROM tickers 
     WHERE team_id = current_setting('app.current_team_id')::int
     ORDER BY status = 'active' DESC, created_at DESC"
)->fetchAll();

// Render public template with tickers
include 'src/templates/public/ticker_overview.php';
```

**Source:** Modeled on `src/ics_handler.php` from Phase 6. No session required; team isolation enforced at DB level.

### Pattern 2: Message Timestamp as Editable Field

**What:** Message `timestamp` column auto-populated at INSERT (database-generated or PHP-set), but user-editable on UPDATE via text input.

**When to use:** Feature requires immutable creation time but allows human-friendly timestamp correction (e.g., "message posted at 14:35 but actually happened at 14:30").

**Example:**

```php
<?php
// Handler: POST /coordinator/ticker/{id} — post new message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $timestamp = trim($_POST['timestamp'] ?? date('H:i')); // User provides time or defaults to now
    $tag_id = $_POST['tag_id'] ?? null;
    
    // Validation
    if (strlen($message) > 280) {
        $error = "Nachricht darf maximal 280 Zeichen lang sein.";
    } else if (!preg_match('/^\d{2}:\d{2}$/', $timestamp)) {
        $error = "Uhrzeit muss Format HH:MM haben.";
    } else {
        // Insert with user-provided timestamp
        $stmt = $pdo->prepare(
            "INSERT INTO ticker_messages (ticker_id, tag_id, message, timestamp, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$ticker_id, $tag_id, $message, $timestamp]);
        redirect("/coordinator/ticker/$ticker_id");
    }
}
```

**DB Schema:**

```sql
CREATE TABLE ticker_messages (
    id SERIAL PRIMARY KEY,
    ticker_id INT NOT NULL REFERENCES tickers(id) ON DELETE CASCADE,
    tag_id INT REFERENCES ticker_tags(id) ON DELETE SET NULL,
    message VARCHAR(280) NOT NULL,
    timestamp VARCHAR(5) NOT NULL, -- Format: "HH:MM"
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### Pattern 3: Auto-Reload Script (Conditional on Status)

**What:** Vanilla `setTimeout()` rendered only when ticker status is `'active'`. Closed tickers display static content.

**When to use:** Polling feature that should disable based on business logic (active vs. closed state).

**Example:**

```html
<!-- In coordinator/ticker_detail.php or public/ticker_detail.php -->

<div class="ticker-container">
    <h2><?php echo e($ticker['name']); ?></h2>
    
    <?php if ($ticker['status'] === 'active'): ?>
        <p class="text-muted small">Wird automatisch aktualisiert…</p>
    <?php else: ?>
        <p class="alert alert-info">Dieser Ticker ist geschlossen.</p>
    <?php endif; ?>
    
    <!-- Message list -->
    <div id="messages">
        <?php foreach ($messages as $msg): ?>
            <div class="card mb-2">
                <div class="card-body">
                    <p class="mb-1"><strong><?php echo e($msg['timestamp']); ?></strong>
                    <?php if ($msg['tag_id']): ?>
                        <span class="badge <?php echo e('bg-' . $tag_colors[$msg['tag_id']]); ?>">
                            <?php echo e($msg['tag_label']); ?>
                        </span>
                    <?php endif; ?>
                    </p>
                    <p class="mb-0"><?php echo e($msg['message']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($ticker['status'] === 'active'): ?>
    <script>
        // Reload entire page every 5 seconds (includes latest messages)
        setTimeout(() => {
            location.reload();
        }, 5000);
    </script>
<?php endif; ?>
```

### Pattern 4: CSRF Protection on Ticker Forms

**What:** All POST forms (create, post message, close, delete) include CSRF token. Validation via `validate_csrf_token()` (existing utility).

**When to use:** All state-changing operations (POST, PUT, DELETE).

**Example:**

```html
<!-- Form: Post new message to ticker -->
<form method="POST">
    <?php csrf_field(); ?>
    
    <div class="mb-3">
        <label for="message" class="form-label">Nachricht (max. 280 Zeichen)</label>
        <textarea 
            name="message" 
            id="message" 
            class="form-control" 
            maxlength="280" 
            required
            oninput="updateCounter()">
        </textarea>
        <small class="form-text text-muted"><span id="charCount">0</span>/280</small>
    </div>
    
    <div class="mb-3">
        <label for="timestamp" class="form-label">Uhrzeit</label>
        <input type="time" name="timestamp" id="timestamp" class="form-control" value="<?php echo date('H:i'); ?>" />
    </div>
    
    <div class="mb-3">
        <label for="tag_id" class="form-label">Tag/Kategorie (optional)</label>
        <select name="tag_id" id="tag_id" class="form-select">
            <option value="">Kein Tag</option>
            <?php foreach ($tags as $tag): ?>
                <option value="<?php echo $tag['id']; ?>">
                    <?php echo e($tag['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <button type="submit" class="btn btn-primary">Posten</button>
</form>

<script>
function updateCounter() {
    const textarea = document.getElementById('message');
    const count = textarea.value.length;
    document.getElementById('charCount').textContent = count;
}
</script>
```

### Pattern 5: Two-Step Destructive Confirm (Delete Ticker)

**What:** DELETE action requires two steps: (1) click "Löschen" button, (2) confirmation page with warning, (3) final submit. No JavaScript required.

**When to use:** Destructive operations (delete ticker and all its messages).

**Example:**

Step 1: Ticker detail page includes delete button:

```html
<form method="GET" style="display:inline;">
    <button type="submit" formaction="/coordinator/ticker/<?php echo $ticker_id; ?>/delete" 
            class="btn btn-danger btn-sm">
        Löschen
    </button>
</form>
```

Step 2: Handler `ticker_delete_handler.php` (GET):

```php
<?php
// GET /coordinator/ticker/{id}/delete — show confirmation page

require_coordinator();
validate_ownership($pdo, 'tickers', $ticker_id);

$ticker = $pdo->prepare("SELECT * FROM tickers WHERE id = ? AND team_id = ?")
    ->fetch(PDO::FETCH_ASSOC);

// Render confirmation template with POST form
include 'src/templates/coordinator/ticker_delete_confirm.php';
```

Step 3: Handler processes POST:

```php
<?php
// POST /coordinator/ticker/{id}/delete — delete ticker

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    require_coordinator();
    validate_ownership($pdo, 'tickers', $ticker_id);
    
    // Delete ticker (messages cascade via FK)
    $pdo->prepare("DELETE FROM tickers WHERE id = ? AND team_id = ?")
        ->execute([$ticker_id, $_SESSION['team_id']]);
    
    redirect('/coordinator/ticker?success=deleted');
}
```

### Anti-Patterns to Avoid

- **Using meta-refresh `<meta http-equiv="refresh">`:** Resets scroll position and form state. Use vanilla `setTimeout()` + `location.reload()` instead.
- **Storing message author (username):** Decision D-06 says no author name. Don't add it later.
- **Global tag configuration:** Tags are team-scoped, not app-wide. Don't centralize in settings table.
- **AJAX message posting:** Contradicts "no AJAX" constraint. Use PRG pattern (POST → redirect → GET).
- **Complex message filtering/search:** Out of scope (Deferred Ideas). Keep message list simple.
- **Using framework like htmx for dynamic updates:** Violates "no JS framework" constraint. Vanilla JS or full page reload only.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Character limit enforcement | Custom JS + server validation | HTML5 `maxlength="280"` + PHP `strlen()` check + DB `VARCHAR(280)` | Browser-native `maxlength` provides immediate feedback; database constraint is safety net; avoids off-by-one bugs with UTF-8 encoding |
| Real-time message updates | WebSocket server, Pusher, Firebase | Vanilla `setTimeout(() => location.reload(), 5000)` | WebSocket requires persistent connection (unavailable on shared hosting); 5-second latency acceptable for sports ticker use case; Pusher/Firebase are overkill for single-team event |
| Color-coded tags | Custom CSS color palette | Bootstrap badge classes (`bg-success`, `bg-warning`, `bg-danger`, `bg-primary`, `bg-secondary`) | Bootstrap is already in use project-wide; badge classes are accessible, mobile-tested, documented; custom colors require CSS maintenance |
| Timestamp parsing | Custom datetime helper | PHP `DateTime` + `strtotime()` for validation | PHP built-in handles timezone concerns; don't reinvent date math |
| Team isolation on public endpoints | Middleware layer | `set_team_context($pdo, $team_id)` + RLS policy | Project already uses RLS from Phase 1; consistent with existing patterns; no new abstraction needed |
| Paginating message list | Infinite scroll, lazy loading | Full feed in single page (reload all messages every 5s) | Ticker feeds are typically small (< 100 messages per event); reload cost negligible; pagination adds complexity (tracking scroll offset across reloads) |

**Key insight:** The 5-second polling interval is coarse enough that simple full-page reload outweighs AJAX complexity. Shared hosting constraints make WebSocket/persistent connections infeasible. Bootstrap components eliminate custom styling burden.

---

## Common Pitfalls

### Pitfall 1: Auto-Reload Disrupts User Editing

**What goes wrong:** Coordinator starts typing a message, then page reloads (timer fires) mid-input, clearing form and losing text.

**Why it happens:** `setTimeout()` reloads regardless of form state; no interrupt logic.

**How to avoid:**
- Option A: Disable auto-reload while form has focus (add `onfocus`/`onblur` handlers to pause timer)
- Option B: Accept that form will reload; use `localStorage` to persist draft message (more user-friendly)
- Option C: Lock auto-reload while user is editing; clear lock on form submit

**Prevention strategy:** Test with rapid typing before form submit. Verify coordinator message doesn't disappear when accidental reload fires.

**Code example (Option B — draft persistence):**

```html
<form method="POST">
    <textarea name="message" id="message" oninput="saveDraft()">
        <?php echo isset($_POST['message']) ? e($_POST['message']) : (htmlspecialchars(localStorage.getItem('ticker_draft') || '') ?? ''); ?>
    </textarea>
</form>

<script>
function saveDraft() {
    const text = document.getElementById('message').value;
    localStorage.setItem('ticker_draft', text);
}

// Clear draft after successful POST (when page reloads from redirect)
// Draft persists if page reloads before submit
</script>
```

**Warning signs:** Coordinator complains "message disappeared," "form was cleared," "typed text lost after update."

### Pitfall 2: Timestamp Timezone Confusion

**What goes wrong:** Coordinator sets timestamp "14:30" intending local time (Berlin), but database or display converts to different timezone.

**Why it happens:** Naive storage of time string without timezone context; confusion between database timezone, PHP timezone, browser timezone.

**How to avoid:**
- Store `timestamp` as `VARCHAR(5)` in HH:MM format only (no date, no timezone)
- Set PHP `date_default_timezone_set('Europe/Berlin')` in `config.php` (one place for all timestamps)
- Never convert timestamp via `strtotime()` or DateTime objects; treat as opaque string
- Display as-is on public ticker (no transformation)

**Prevention strategy:** Add comment in schema.sql explaining timezone assumption. Document in CLAUDE.md or project README.

**Code example:**

```php
<?php
// config.php — ONCE at app start
date_default_timezone_set('Europe/Berlin');

// When inserting message timestamp:
$timestamp = trim($_POST['timestamp']); // User input: "14:30"
// Assume user is in Berlin timezone; store as-is
$pdo->prepare("INSERT INTO ticker_messages (..., timestamp, ...) VALUES (..., ?, ...)")
    ->execute([..., $timestamp, ...]);

// When displaying:
echo e($message['timestamp']); // Output: "14:30" (no transformation)
```

**Warning signs:** Timestamps appear "one hour off," "wrong timezone," display different on public vs. coordinator view.

### Pitfall 3: Character Encoding Issues with `strlen()`

**What goes wrong:** Coordinator pastes a message with Unicode characters (emojis, German umlauts), JavaScript counter shows "10", PHP check rejects as "12 > 280".

**Why it happens:** JavaScript `textContent.length` counts Unicode chars differently; PHP `strlen()` counts bytes.

**How to avoid:**
- Use `mb_strlen($message, 'UTF-8')` in PHP (not `strlen()`)
- In JavaScript, use `message.length` (counts Unicode code points, not bytes)
- Set `charset=utf-8` in HTTP headers + `<meta charset="utf-8">` in HTML
- Store as `VARCHAR(280)` (PostgreSQL counts characters, not bytes, in `VARCHAR(n)`)

**Prevention strategy:** Test with emoji + German umlauts. Verify counter matches on both client and server.

**Code example:**

```php
<?php
// Correct validation
$message = $_POST['message'];
if (mb_strlen($message, 'UTF-8') > 280) {
    $error = "Nachricht darf maximal 280 Zeichen lang sein.";
}

// Insert with correct length validation
$pdo->prepare("INSERT INTO ticker_messages (..., message, ...) VALUES (..., ?, ...)")
    ->execute([..., $message, ...]);
```

```html
<!-- JavaScript counter (correct) -->
<textarea name="message" oninput="updateCounter()"></textarea>
<script>
function updateCounter() {
    const length = document.getElementById('message').value.length; // Counts Unicode chars
    document.getElementById('charCount').textContent = length;
}
</script>
```

**Warning signs:** "Message rejected as too long," "emoji counts double," "umlauts count extra."

### Pitfall 4: Forgetting to Check Membership (TICKER-03)

**What goes wrong:** Member posts message to ticker they're NOT freigegeben for; authorization check skipped.

**Why it happens:** Forgot to verify `ticker_members` join table before INSERT; relied on front-end dropdown filtering only.

**How to avoid:**
- On `/member/ticker/{id}` POST, check that `(user_id, ticker_id)` exists in `ticker_members` table
- Use prepared statement with explicit check, not just form validation
- Apply same check on message edit/delete (member can only edit own messages OR all messages if Coordinator)

**Prevention strategy:** Add SQL query to verify membership; fail with 403 Forbidden if member tries direct POST to non-authorized ticker.

**Code example:**

```php
<?php
// POST /member/ticker/{id} — post message

require_member();

$ticker_id = (int)$_GET['id'];

// Check: is this member freigegeben for this ticker?
$is_freigegeben = $pdo->prepare(
    "SELECT 1 FROM ticker_members 
     WHERE ticker_id = ? AND user_id = ? AND team_id = ?"
)->fetch();

if (!$is_freigegeben) {
    http_response_code(403);
    die("Du darfst in diesem Ticker nicht posten.");
}

// Proceed with message insertion
// ...
```

**Warning signs:** "Member can post to any ticker," "freigabe checkbox ignored," "authorization not enforced."

### Pitfall 5: Auto-Reload Causes Excessive Database Queries

**What goes wrong:** Public ticker view with 1000 concurrent users reloading every 5 seconds → 200 queries/sec on `SELECT ... FROM ticker_messages`.

**Why it happens:** No caching; full message list fetched on every reload; inefficient query without indexes.

**How to avoid:**
- Add index on `(ticker_id, created_at DESC)` to speed message fetch
- Limit message list to recent N messages (e.g., last 50) to reduce payload
- Monitor query log in production; alert if `ticker_messages` SELECT > 100ms
- For true scale, consider query caching or CDN (future optimization, not Phase 7)

**Prevention strategy:** Add database indexes in schema.sql. Test with simulated concurrent users (Apache Bench / `ab` tool).

**Code example:**

```sql
-- schema.sql — indexes for efficient polling queries
CREATE INDEX idx_ticker_messages_ticker_created ON ticker_messages(ticker_id, created_at DESC);
CREATE INDEX idx_tickers_team_status ON tickers(team_id, status);
```

```php
<?php
// Fetch only recent messages (limit to 50 to avoid large payloads)
$messages = $pdo->prepare(
    "SELECT id, tag_id, message, timestamp, created_at FROM ticker_messages 
     WHERE ticker_id = ? 
     ORDER BY created_at DESC 
     LIMIT 50"
)->fetchAll();
```

**Warning signs:** "Page load slow," "database CPU high," "queries taking > 100ms."

---

## Code Examples

Verified patterns from existing codebase and CLAUDE.md:

### Message Posting with CSRF + Character Validation

```php
<?php
// src/coordinator/ticker_detail_handler.php — POST message to ticker

require_coordinator();

$ticker_id = (int)($_GET['id'] ?? 0);

// Verify ownership (triple constraint: ticker + team_id + coordinator role)
$ticker = $pdo->prepare(
    "SELECT * FROM tickers 
     WHERE id = ? AND team_id = ? 
     LIMIT 1"
)->fetch();

if (!$ticker) {
    http_response_code(404);
    include 'src/templates/404.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $message = trim($_POST['message'] ?? '');
    $timestamp = trim($_POST['timestamp'] ?? date('H:i'));
    $tag_id = !empty($_POST['tag_id']) ? (int)$_POST['tag_id'] : null;
    
    // Validation
    if (mb_strlen($message, 'UTF-8') === 0) {
        $error = "Nachricht darf nicht leer sein.";
    } elseif (mb_strlen($message, 'UTF-8') > 280) {
        $error = "Nachricht darf maximal 280 Zeichen lang sein.";
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $timestamp)) {
        $error = "Uhrzeit muss Format HH:MM haben.";
    } else {
        // Insert message
        $stmt = $pdo->prepare(
            "INSERT INTO ticker_messages (ticker_id, tag_id, message, timestamp, created_at) 
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$ticker_id, $tag_id, $message, $timestamp]);
        
        redirect("/coordinator/ticker/$ticker_id?success=message_posted");
    }
}

// GET: fetch messages for display
$messages = $pdo->prepare(
    "SELECT m.*, t.label as tag_label 
     FROM ticker_messages m 
     LEFT JOIN ticker_tags t ON m.tag_id = t.id 
     WHERE m.ticker_id = ? 
     ORDER BY m.created_at DESC"
)->fetchAll();

$tags = $pdo->prepare(
    "SELECT * FROM ticker_tags 
     WHERE team_id = ? 
     ORDER BY sort_order"
)->fetchAll();

include 'src/templates/coordinator/ticker_detail.php';
```

**Source:** Pattern combines existing CSRF validation (`require_csrf()`), character limits (`mb_strlen()`), and PDO prepared statements from Phase 1–6.

### Public Ticker Overview (No Auth)

```php
<?php
// src/public/ticker_handler.php — GET /ticker (public overview)

// NO require_coordinator() or require_member() — public endpoint

$team_id = (int)($_GET['team'] ?? 1); // Default to team 1 or infer from domain

$pdo = new PDO(
    "pgsql:host={$db_host};port={$db_port};dbname={$db_name}",
    $db_user,
    $db_pass,
    [PDO::ATTR_EMULATE_PREPARES => false]
);

// Set team context for RLS (all subsequent queries filtered by team_id)
set_team_context($pdo, $team_id, null, null);

// Fetch tickers: active first, then closed
$stmt = $pdo->prepare(
    "SELECT id, name, description, status, created_at 
     FROM tickers 
     WHERE team_id = current_setting('app.current_team_id')::int
     ORDER BY (status = 'active') DESC, created_at DESC"
);
$stmt->execute();
$tickers = $stmt->fetchAll();

// Render public template (no login required)
include 'src/templates/public/ticker_overview.php';
```

**Source:** Modeled on `src/ics_handler.php` (Phase 6). Uses `set_team_context()` for RLS isolation.

### Auto-Reload Conditional on Status

```html
<!-- src/templates/public/ticker_detail.php — public ticker feed view -->

<?php
// Loaded by public/ticker_detail_handler.php
// Variables: $ticker, $messages, $tags
?>

<div class="container mt-4">
    <div class="ticker-header mb-4">
        <h2><?php echo e($ticker['name']); ?></h2>
        <p class="text-muted"><?php echo e($ticker['description']); ?></p>
        
        <?php if ($ticker['status'] === 'active'): ?>
            <p class="text-muted small">Wird automatisch aktualisiert…</p>
        <?php else: ?>
            <div class="alert alert-info alert-sm" role="alert">
                Dieser Ticker ist geschlossen.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Message feed (newest first) -->
    <div id="messages" class="message-feed">
        <?php foreach ($messages as $msg): ?>
            <div class="card mb-2">
                <div class="card-body">
                    <div class="mb-2">
                        <strong class="text-dark"><?php echo e($msg['timestamp']); ?></strong>
                        <?php if ($msg['tag_id']): ?>
                            <?php
                            $tag = array_filter($tags, fn($t) => $t['id'] == $msg['tag_id'])[0] ?? null;
                            if ($tag):
                            ?>
                                <span class="badge bg-<?php echo e($tag['color']); ?>">
                                    <?php echo e($tag['label']); ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <p class="mb-0"><?php echo e($msg['message']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (empty($messages)): ?>
            <p class="text-muted text-center">Keine Nachrichten noch.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($ticker['status'] === 'active'): ?>
    <script>
        // Auto-reload every 5 seconds (active ticker only)
        setTimeout(() => {
            location.reload();
        }, 5000);
    </script>
<?php endif; ?>
```

**Source:** Vanilla JS polling. Conditional on `status === 'active'`. Uses `e()` for safe output.

### Character Counter Form

```html
<!-- Included in coordinator/ticker_detail.php + member/ticker_detail.php -->

<form method="POST" class="mt-4">
    <?php csrf_field(); ?>
    
    <div class="mb-3">
        <label for="message" class="form-label">Nachricht posten (max. 280 Zeichen)</label>
        <textarea
            name="message"
            id="message"
            class="form-control"
            rows="3"
            maxlength="280"
            required
            oninput="updateCounter()">
        </textarea>
        <small class="d-block mt-1 text-muted">
            <span id="charCount">0</span>/280 Zeichen
        </small>
    </div>
    
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="timestamp" class="form-label">Uhrzeit (HH:MM)</label>
            <input 
                type="time" 
                name="timestamp" 
                id="timestamp" 
                class="form-control"
                value="<?php echo date('H:i'); ?>"
                required
            />
        </div>
        <div class="col-md-6 mb-3">
            <label for="tag_id" class="form-label">Tag/Kategorie (optional)</label>
            <select name="tag_id" id="tag_id" class="form-select">
                <option value="">Kein Tag</option>
                <?php foreach ($tags as $tag): ?>
                    <option value="<?php echo $tag['id']; ?>">
                        <?php echo e($tag['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    
    <button type="submit" class="btn btn-primary">Posten</button>
</form>

<script>
function updateCounter() {
    const textarea = document.getElementById('message');
    const length = textarea.value.length;
    document.getElementById('charCount').textContent = length;
}

// Initialize counter on page load
document.addEventListener('DOMContentLoaded', updateCounter);
</script>
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| WebSocket-based real-time messages | Vanilla `setTimeout()` polling every 5s | Phase 7 (2026) | Shared hosting compat; simpler deployment; acceptable latency for sports events |
| Server-sent events (SSE) for live updates | Full-page `location.reload()` | Phase 7 (2026) | No persistent connection required; no browser compatibility concerns |
| JavaScript framework for dynamic UI | Server-side rendering + vanilla JS for form counter | Phase 7 (2026) | Aligns with project "no JS framework" constraint; reduces bundle size |
| AJAX-based message posting | POST-redirect-GET pattern + full page reload | Phase 7 (2026) | Simpler; no form state management; UX acceptable for 5s reload interval |
| Complex message filtering/search | Simple chronological feed (newest first) | Phase 7 (2026) | Deferred to future phase; ticker is for live event coverage, not archive browsing |

**Deprecated/outdated:**
- Meta-refresh for auto-reload: Use `setTimeout()` instead (preserves scroll/form state during initial load)
- Storing timestamps as `TIMESTAMP` (full datetime): Use `VARCHAR(5)` HH:MM format (simpler, no timezone confusion)

---

## Open Questions

1. **Multi-team ticker access (future)**
   - What we know: Phase 7 assumes single-team context (team_id from session or URL parameter)
   - What's unclear: If future feature requires viewing tickers across teams, schema supports it (ticker_id join to events/tournaments)
   - Recommendation: Current phase assumes single team. If cross-team becomes requirement, add `event_id` foreign key linking multiple tickers. Defer to Phase 8+.

2. **Message edit history (audit trail)**
   - What we know: Messages are editable (timestamp, tag, content)
   - What's unclear: Should we track who edited a message or when it was edited?
   - Recommendation: Phase 7 scope: no audit trail. If required, add `updated_by`, `updated_at` columns and a simple "edited" indicator on message display.

3. **Ticker permissions (future)**
   - What we know: Coordinator always can edit ticker + all messages; members need freigabe to post
   - What's unclear: Can freigegeben members close/delete ticker? Current scope says only Coordinator.
   - Recommendation: Lock to Coordinator only for close/delete. If future feature requires shared admin, extend `ticker_members` with role column (viewer/editor/admin).

---

## Database Design

### Schema Overview

Four new tables, plus modifications to existing `columns` → `settings` scope:

```sql
-- New tables for Phase 7

CREATE TABLE IF NOT EXISTS tickers (
    id SERIAL PRIMARY KEY,
    team_id INT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'closed')),
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_tickers_team_status ON tickers(team_id, status);

CREATE TABLE IF NOT EXISTS ticker_tags (
    id SERIAL PRIMARY KEY,
    team_id INT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    label VARCHAR(50) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT 'secondary', -- Bootstrap color (success, warning, danger, etc.)
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_ticker_tags_team ON ticker_tags(team_id);

CREATE TABLE IF NOT EXISTS ticker_messages (
    id SERIAL PRIMARY KEY,
    ticker_id INT NOT NULL REFERENCES tickers(id) ON DELETE CASCADE,
    tag_id INT REFERENCES ticker_tags(id) ON DELETE SET NULL,
    message VARCHAR(280) NOT NULL,
    timestamp VARCHAR(5) NOT NULL, -- Format: HH:MM (no timezone)
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_ticker_messages_ticker_created ON ticker_messages(ticker_id, created_at DESC);

CREATE TABLE IF NOT EXISTS ticker_members (
    ticker_id INT NOT NULL REFERENCES tickers(id) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    team_id INT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    PRIMARY KEY (ticker_id, user_id)
);
CREATE INDEX idx_ticker_members_user ON ticker_members(user_id, team_id);
```

### RLS Policies

```sql
-- RLS for tickers (Coordinator can see/edit own team; Member cannot edit)
ALTER TABLE tickers ENABLE ROW LEVEL SECURITY;

CREATE POLICY tickers_coordinator_all ON tickers
    FOR ALL
    TO authenticated
    USING (team_id = current_setting('app.current_team_id')::int AND current_setting('app.current_role') = 'coordinator')
    WITH CHECK (team_id = current_setting('app.current_team_id')::int);

CREATE POLICY tickers_public_read ON tickers
    FOR SELECT
    USING (status = 'active' OR status = 'closed'); -- No RLS for public ticker views

-- RLS for ticker_messages (Coordinator can edit all; Member can edit only if in ticker_members)
ALTER TABLE ticker_messages ENABLE ROW LEVEL SECURITY;

CREATE POLICY ticker_messages_coordinator_all ON ticker_messages
    FOR ALL
    TO authenticated
    USING (EXISTS (
        SELECT 1 FROM tickers WHERE tickers.id = ticker_messages.ticker_id 
        AND tickers.team_id = current_setting('app.current_team_id')::int
    ) AND current_setting('app.current_role') = 'coordinator')
    WITH CHECK (EXISTS (
        SELECT 1 FROM tickers WHERE tickers.id = ticker_messages.ticker_id 
        AND tickers.team_id = current_setting('app.current_team_id')::int
    ));

-- RLS for ticker_members (Coordinator can manage)
ALTER TABLE ticker_members ENABLE ROW LEVEL SECURITY;

CREATE POLICY ticker_members_coordinator_manage ON ticker_members
    FOR ALL
    TO authenticated
    USING (team_id = current_setting('app.current_team_id')::int AND current_setting('app.current_role') = 'coordinator')
    WITH CHECK (team_id = current_setting('app.current_team_id')::int);

-- RLS for ticker_tags (Coordinator can see all; configure on Settings page)
ALTER TABLE ticker_tags ENABLE ROW LEVEL SECURITY;

CREATE POLICY ticker_tags_coordinator_all ON ticker_tags
    FOR ALL
    TO authenticated
    USING (team_id = current_setting('app.current_team_id')::int AND current_setting('app.current_role') = 'coordinator')
    WITH CHECK (team_id = current_setting('app.current_team_id')::int);
```

---

## Sources

### Primary (HIGH confidence)

- **CLAUDE.md** (project instructions) — Stack: PHP 8.3+, PostgreSQL 14+, PDO, Bootstrap 5, mobile-first, no framework
- **CONTEXT.md (Phase 7)** — Locked decisions (D-01 to D-14), database schema outline, URL schema, integration points, code patterns
- **Existing codebase patterns** (Phases 1–6) — CSRF validation, PRG pattern, RLS via `set_team_context()`, two-step destructive confirm, public endpoint pattern (`ics_handler.php`)

### Secondary (MEDIUM confidence)

- **Bootstrap 5.3 CDN documentation** — Badge classes (`bg-success`, etc.), responsive card layout
- **PostgreSQL documentation** — Row-level security, CHECK constraints, cascading deletes
- **PHP built-in functions** — `mb_strlen()`, `htmlspecialchars()`, `strtotime()`, `date()` timezone handling

### Tertiary (reference only)

- **MDN Web Docs** — Vanilla JavaScript `setTimeout()`, `localStorage` for draft persistence, `oninput` event handling

---

## Metadata

**Confidence breakdown:**
- **Standard stack (HIGH):** PHP 8.3+, PostgreSQL, PDO, Bootstrap verified in existing codebase (Phases 1–6)
- **Architecture patterns (HIGH):** RLS, CSRF, PRG, public endpoint pattern documented in CONTEXT + CLAUDE.md
- **Database design (HIGH):** Schema structure outlined in CONTEXT.md; indexes and RLS policies follow existing Phase 1–6 patterns
- **Pitfalls (MEDIUM-HIGH):** Character encoding, timezone confusion, auto-reload disruption based on web development best practices; verified against CONTEXT constraints
- **Auto-reload implementation (HIGH):** Vanilla `setTimeout()` is simple, documented in CONTEXT.md decision D-01

**Research date:** 2026-07-26  
**Valid until:** 2026-08-09 (14 days — standard stack stable, no major framework updates expected)

**Phase requirements coverage:**
- TICKER-01 (Ticker CRUD): Database schema + four handler files documented
- TICKER-02 (Nachrichten posten): Character validation, CSRF, timestamp handling documented
- TICKER-03 (Autorisierung): ticker_members join + RLS policies documented
- TICKER-04 (öffentlich ohne Login lesbar): Public endpoint pattern + set_team_context() documented
- TICKER-05 (Auto-Reload + Hinweis): Vanilla JS conditional on status documented; static hint text approved
- TICKER-06 (öffentliche Übersichtsseite + Login-Seite-Link): Public /ticker route + login template integration documented
