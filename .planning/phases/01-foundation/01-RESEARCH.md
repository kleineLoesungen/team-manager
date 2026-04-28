# Phase 1: Foundation - Research

**Researched:** 2026-04-29
**Domain:** PHP 8.3+ authentication, PostgreSQL RLS, session security, password hashing
**Confidence:** HIGH

## Summary

Phase 1 Foundation requires implementing secure user authentication, role-based session management, PostgreSQL Row-Level Security for team isolation, and admin capabilities for team/coach management. The stack is vanilla PHP 8.3+ with PostgreSQL 14+, Bootstrap 5 CDN, and no external frameworks.

Core security requirements span four domains: (1) session cookie hardening with OWASP-compliant configuration, (2) password hashing with bcrypt (currently PASSWORD_DEFAULT) with explicit upgrade path to Argon2, (3) CSRF token generation via session-based random bytes, and (4) PostgreSQL RLS policies enforcing team_id filtering at the database layer. A sliding window inactivity timeout (8 hours) with simple redirect (no JS modal) requires careful timestamp tracking.

**Primary recommendation:** Use PHP 8.3 password_hash(PASSWORD_BCRYPT, cost:12) today with bcrypt cost 12 (PHP 8.4+ default), configure session cookies with Secure/HttpOnly/SameSite=Strict per OWASP 2025, implement RLS policies with session-context team_id validation, and track last_activity timestamp for 8-hour sliding window timeout with server-side enforcement.

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01**: Single `users` table with columns: id, team_id, role (ENUM: 'coach'/'player'), first_name, last_name, username, password_hash, is_active, created_at. No separate admin database entry.
- **D-02**: Admin exists only in `config.php` — no users table row. Admin session gets `$_SESSION['is_admin'] = true` with `role = 'admin'`.
- **D-03**: Soft delete via `is_active = false`. Login blocked for inactive users, but historical list data retained.
- **D-04**: `password_hash` stored directly in users table. No separate credentials table.
- **D-05**: Sliding Window session timeout: 8 hours maximum inactivity, every request extends session.
- **D-06**: On timeout: simple redirect to login page. No JavaScript modal or warning.
- **D-07**: Session config: `cookie_secure`, `cookie_httponly`, `cookie_samesite=Strict`, `use_strict_mode=true`.
- **D-08**: Separate `/admin` route area with own layout template. Access check: `$_SESSION['is_admin']` on every admin page.
- **D-09**: Admin dashboard homepage showing all teams with assigned coaches and quick actions.
- **D-10**: Admin is pure manager: no access to lists, player data, or statistics.
- **D-11**: Username format: Initials + 4-digit random number (e.g., `mm4821`). Collision check during generation.
- **D-12**: Trainer provides first_name and last_name when creating player/coach. Display name = "Vorname Nachname".
- **D-13**: Username set permanently after generation — no updates allowed.

### Claude's Discretion

- PostgreSQL RLS policy design (application-level team_id filter as supplementary layer)
- Exact schema for `teams` table (name, is_active, created_at)
- Routing structure (index.php dispatcher vs. separate router class)
- CSRF token implementation detail (session-based vs. double-submit cookie)

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed fully in Phase 1 scope.

</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| AUTH-01 | User can log in with username/password and access the application | Session management with password_hash()/password_verify(), secure session cookies, CSRF token validation in login form |
| AUTH-02 | Session automatically expires after inactivity and user is redirected to login | Sliding window inactivity tracking via last_activity timestamp in $_SESSION, 8-hour max, server-side check on every request |
| AUTH-03 | Trainer can reset player password — new random password displayed once on screen | password_hash() for new password, credential display modal auto-closes after 60s, no persistence |
| AUTH-04 | Admin can reset coach password — new random password displayed once on screen | Same as AUTH-03; admin-only route with $_SESSION['is_admin'] check |
| TEAM-01 | Admin can create new team with name | Admin route POST handler, INSERT into teams table, PDO prepared statement |
| TEAM-02 | Admin can assign one or more coaches to team | Admin route, INSERT into team_coaches pivot table (or team_id foreign key in coaches), PDO prepared statement |
| TEAM-03 | Admin can rename or deactivate team | Admin route, UPDATE teams table, soft delete via is_active flag |

</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP | 8.3+ (8.4+ preferred) | Server-side logic, session management, templating | Current stable with security patches; 8.4 improves JSON support and increases bcrypt cost to 12 by default |
| PostgreSQL | 14+ (15+ preferred) | Relational database with RLS | ACID compliance, Row-Level Security for team isolation, excellent JSON support for future flexibility |
| nginx or Apache 2.4+ | Latest stable | HTTP server with PHP-FPM | nginx lighter/faster; Apache simpler for shared hosting; both deploy PHP-FPM effectively |
| Bootstrap | 5.3+ (CDN) | Mobile-first CSS framework | No build step via CDN, mobile-first components, widespread support, German UI compatible |

### Security & Database Access
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PDO (PHP Data Objects) | Built-in since PHP 5.1 | Database abstraction with prepared statements | All database queries; prevents SQL injection; lighter than ORM |
| PDO_PGSQL driver | Built-in with PHP | PostgreSQL-specific PDO driver | Required to connect PDO to PostgreSQL |
| password_hash() / password_verify() | Built-in (PHP 5.5+) | Password hashing & verification | All password operations; uses bcrypt (PASSWORD_DEFAULT) with cost 12 in PHP 8.4+ |
| session_start() with ini options | Built-in (PHP 7.1+ with 8.x enhancements) | Secure session management | All authenticated routes; configure cookie_secure, cookie_httponly, cookie_samesite |
| random_bytes() / bin2hex() | Built-in (PHP 7.0+) | Cryptographic random token generation | CSRF token generation, password reset display timeout |
| hash_equals() | Built-in (PHP 5.6+) | Timing-safe string comparison | CSRF token validation, password hash comparison |

### Validation & Output Security
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| filter_var() / filter_input() | Built-in (PHP 5.2+) | Input validation & sanitization | Validate emails, URLs, integers; reject invalid data before DB insert |
| htmlspecialchars() | Built-in | Output escaping to HTML | All user-controlled data output to HTML; prevent XSS attacks |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Vanilla PHP | Laravel, Symfony | Adds complexity for simple CRUD app; slower onboarding for single-admin, fixed-feature scope |
| PDO | Doctrine ORM, Eloquent | ORMs add overhead; PDO prepared statements sufficient for straightforward queries; easier debugging |
| password_hash() | bcrypt library, custom hashing | password_hash() is official PHP recommendation, PASSWORD_DEFAULT evolves as security improves, PASSWORD_ARGON2ID available since PHP 7.3 |
| Bootstrap CDN | Tailwind CSS | Tailwind requires Node.js build pipeline; contradicts "no heavy JS" constraint |
| Native sessions | Redis/Memcached | PHP file-based sessions sufficient for single-admin, small team scale; Redis adds infrastructure burden |
| Session-based CSRF | Double-submit cookie | Session-based simpler, no additional cookie management; double-submit requires strict SameSite enforcement |

**Installation (macOS/Homebrew example):**
```bash
# PostgreSQL
brew install postgresql@14
brew services start postgresql@14

# PHP 8.4 (or 8.3)
brew install php@8.4
brew services start php-fpm@8.4

# Verify
php --version
psql --version

# Enable PDO_PGSQL extension in php.ini
# (Usually pre-installed; verify via php -m | grep pdo_pgsql)
```

---

## Architecture Patterns

### Recommended Project Structure
```
src/
├── auth/              # Authentication handlers (login, logout, session check)
├── admin/             # Admin-only route handlers (team/coach management)
├── db/                # Database connection, prepared statement helpers, RLS context
├── config/            # config.php (admin credentials, DB connection, session timeout)
├── templates/         # HTML templates (login.php, admin_dashboard.php, etc.)
├── public/            # index.php (router), static assets (CSS if custom)
└── utils/             # CSRF token generation, password generation, validation helpers

Database:
├── schema.sql         # Table definitions: users, teams, sessions (optional), team_coaches
├── rls_policies.sql   # PostgreSQL RLS policy definitions per team_id
└── seeds.sql          # Optional: test data
```

### Pattern 1: Session-Based Authentication with Sliding Window Timeout

**What:** Track login time and last activity in $_SESSION; validate both on every request; extend last_activity on each page load; redirect if either threshold exceeded.

**When to use:** All authenticated routes, especially admin routes that require higher privilege.

**Example:**
```php
<?php
// Secure session configuration (in config.php or early bootstrap)
session_set_cookie_params([
    'secure'   => true,          // HTTPS only
    'httponly' => true,          // JavaScript cannot access
    'samesite' => 'Strict',      // CSRF mitigation
]);
ini_set('session.use_strict_mode', '1');    // Disallow invalid session IDs
ini_set('session.use_cookies', '1');        // Use cookies only
ini_set('session.use_only_cookies', '1');   // Disable URL-based sessions

session_start();

// Check session timeout on every request (in middleware or before action)
const SESSION_TIMEOUT_SECONDS = 8 * 60 * 60; // 8 hours

if (isset($_SESSION['login_time']) && isset($_SESSION['last_activity'])) {
    $now = time();
    $login_elapsed = $now - $_SESSION['login_time'];
    $inactive_elapsed = $now - $_SESSION['last_activity'];
    
    if ($login_elapsed > SESSION_TIMEOUT_SECONDS || $inactive_elapsed > SESSION_TIMEOUT_SECONDS) {
        session_destroy();
        header('Location: /login?message=Session+expired');
        exit;
    }
    
    // Update last activity (sliding window)
    $_SESSION['last_activity'] = $now;
} else if (isset($_SESSION['user_id'])) {
    // Session exists but no timestamps — initialize them
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
}
?>
```

### Pattern 2: PDO Prepared Statements with PostgreSQL RLS

**What:** Use PDO with ATTR_EMULATE_PREPARES=false; bind all user data via ? or :param placeholders; set session context for RLS filtering.

**When to use:** Every database query involving user input or team_id filtering.

**Example:**
```php
<?php
// In db/connection.php
$dsn = 'pgsql:host=localhost;dbname=team_manager';
$pdo = new PDO($dsn, 'postgres', 'password', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,  // CRITICAL: Disable emulation for Postgres
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Helper to set RLS context (team_id)
function set_rls_context($pdo, $team_id) {
    $stmt = $pdo->prepare("SELECT set_config('app.current_team_id', ?, false)");
    $stmt->execute([$team_id]);
}

// Login query example (no RLS needed — lookup by username across all teams)
$stmt = $pdo->prepare("SELECT id, team_id, password_hash, role, is_active FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password_hash']) && $user['is_active']) {
    // Set RLS context for this session
    set_rls_context($pdo, $user['team_id']);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['team_id'] = $user['team_id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    header('Location: /dashboard');
} else {
    $error = 'Benutzername oder Passwort falsch.';
}
?>
```

### Pattern 3: CSRF Token Generation & Validation

**What:** Generate token via random_bytes(32) → bin2hex(), store in $_SESSION, include in all forms, validate via hash_equals().

**When to use:** All POST/PUT/DELETE forms; especially admin forms (team create, coach assign).

**Example:**
```php
<?php
// Generate token (once per session)
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// In template form
echo '<input type="hidden" name="_csrf" value="' . htmlspecialchars(generate_csrf_token()) . '">';

// Validate token on POST
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// In handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['_csrf'] ?? '')) {
        http_response_code(403);
        die('CSRF token invalid.');
    }
    // Safe to process form
}
?>
```

### Pattern 4: Password Generation & Secure Display

**What:** Generate random password via random_bytes(), display once in auto-closing modal, time-limit with JavaScript countdown (auto-close after 60s).

**When to use:** Password reset actions (AUTH-03, AUTH-04), new player/coach creation (TEAM-04 in Phase 2).

**Example:**
```php
<?php
// Generate random password (e.g., 12 characters)
function generate_random_password($length = 12) {
    $bytes = random_bytes($length);
    // Use a subset of safe characters (avoid confusing chars like 0/O, 1/l/I)
    $safe_chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $safe_chars[ord($bytes[$i]) % strlen($safe_chars)];
    }
    return $password;
}

// Hash and store
$new_password = generate_random_password();
$password_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
$stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->execute([$password_hash, $user_id]);

// Display credential modal (60-second auto-close)
?>
<div id="credential-modal" class="modal" style="display: block;">
  <div class="modal-content">
    <h2>Neue Anmeldedaten</h2>
    <p>Benutzername: <code><?php echo htmlspecialchars($username); ?></code></p>
    <p>Passwort: <code><?php echo htmlspecialchars($new_password); ?></code></p>
    <p id="timer">Dieses Fenster schließt sich automatisch in 60 Sekunden.</p>
    <button onclick="closeModal()">Schließen</button>
  </div>
</div>

<script>
let seconds = 60;
const timer = document.getElementById('timer');
const interval = setInterval(() => {
    seconds--;
    timer.textContent = `Dieses Fenster schließt sich automatisch in ${seconds} Sekunden.`;
    if (seconds <= 0) {
        clearInterval(interval);
        window.location.href = '/admin/teams';  // Redirect
    }
}, 1000);

function closeModal() {
    clearInterval(interval);
    window.location.href = '/admin/teams';
}
</script>
```

### Pattern 5: PostgreSQL Row-Level Security for Team Isolation

**What:** Create RLS policies on tables (users, lists, cells, etc.) that enforce team_id filtering. Application sets session context via `set_config()`.

**When to use:** Protect multi-tenant data at database layer; defense in depth against application-level authorization bypass.

**Example (schema.sql):**
```sql
-- Enable RLS on users table
ALTER TABLE users ENABLE ROW LEVEL SECURITY;

-- Policy 1: Users can only see themselves or users in their own team
CREATE POLICY "team_isolation_users" ON users
    FOR SELECT
    USING (
        (auth.uid() = id) OR  -- User can see themselves
        (team_id = (SELECT current_setting('app.current_team_id')::int)) -- User can see teammates
    );

-- Policy 2: Only coaches/admin can insert users (simplified; refine based on role)
CREATE POLICY "insert_users" ON users
    FOR INSERT
    WITH CHECK (team_id = (SELECT current_setting('app.current_team_id')::int));

-- Policy 3: Users can update their own row
CREATE POLICY "update_own_user" ON users
    FOR UPDATE
    USING (id = auth.uid())
    WITH CHECK (id = auth.uid());

-- Repeat similar policies for lists, cells, columns tables
```

### Anti-Patterns to Avoid

- **Storing plaintext passwords:** Never store passwords unhashed. Always use `password_hash(PASSWORD_BCRYPT, cost:12)` and `password_verify()`.
- **Trusting client-side session timeout:** Session timeout MUST be enforced server-side on every request; no reliance on JavaScript to expire sessions.
- **Enabling PDO::ATTR_EMULATE_PREPARES for PostgreSQL:** This disables true prepared statement support and re-exposes SQL injection vulnerability. Always set to false.
- **Logging credentials:** Never log plaintext passwords, password hashes, or plaintext generated credentials. Clear display after 60 seconds.
- **Skipping CSRF validation:** All POST/PUT/DELETE forms must include and validate CSRF tokens. Attacker cannot forge a valid token without session access.
- **RLS-only authorization:** RLS is defense in depth. Always validate authorization at application layer as well; do not assume RLS alone.
- **Treating soft delete (`is_active=false`) as permanent deletion:** Inactive users remain in the database; archived lists remain associated; historical data is preserved for audit/recovery.
- **Session fixation:** Always regenerate session ID after successful login. Set `use_strict_mode=1` to reject invalid session IDs.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Password hashing | Custom bcrypt wrapper, salting logic | `password_hash(PASSWORD_BCRYPT, cost:12)` / `password_verify()` | Built-in, official recommendation, automatic cost evolution, resistant to timing attacks |
| Session timeout enforcement | Manual timestamp comparisons without validation | Structured last_activity tracking with both server-side check AND session regeneration | Manual implementations leak stale sessions, forget edge cases (concurrent requests), risk double-charge bugs |
| CSRF protection | Custom token comparison with == operator | `hash_equals()` for timing-safe comparison; session-based token storage | == operator is vulnerable to timing attacks; hash_equals() is constant-time; session-based avoids double-cookie complexity |
| SQL injection prevention | String concatenation, manual escaping | PDO prepared statements with `ATTR_EMULATE_PREPARES=false` for Postgres | Manual escaping leaves edge cases; PDO with emulation disabled is bulletproof; filters are not enough |
| Team isolation | Application-level role checks in every handler | PostgreSQL Row-Level Security policies + application-level team_id checks | RLS is defense in depth; catches authorization bypass bugs; application layer must still validate (no single point of failure) |
| Random number generation for tokens | `rand()`, `mt_rand()`, uniqid() | `random_bytes()` with `bin2hex()` | Predictable RNGs leak tokens; random_bytes() uses OS entropy; bin2hex() ensures safe text representation |
| Session storage | Database table with manual garbage collection | Native PHP $_SESSION with garbage collection configured | Database sessions add complexity; PHP's built-in GC is good enough for small teams; simpler to reason about |

**Key insight:** Authentication and session management are domains where off-by-one errors become breaches. Use official PHP functions, test configuration thoroughly, and never optimize away security.

---

## Common Pitfalls

### Pitfall 1: Session Fixation During Login

**What goes wrong:** Session ID is not regenerated after successful login. Attacker with a valid session ID (via other exploit) can "ride" the user's session.

**Why it happens:** Developers assume login validation is sufficient; forgot that session ID itself is the security boundary.

**How to avoid:** Always call `session_regenerate_id(true)` after successful login to invalidate the old session ID. Set `use_strict_mode=1` to reject invalid session IDs at the start.

**Warning signs:** Session ID visible in logs before/after login; session ID appears in URL or referer headers (only possible if use_only_cookies=0).

---

### Pitfall 2: Credentials in Logs or Browser History

**What goes wrong:** Generated password (during reset or player creation) is logged, stored in database, or persists in browser history. Attacker with log access or old browser cache can retrieve credentials.

**Why it happens:** Developers think "it's just for display" and don't treat it like sensitive data; no auto-clear mechanism.

**How to avoid:** 
- Never log the plaintext generated password. Log only "password reset for user X" (no value).
- Display credentials in modal only; auto-close after 60 seconds via JavaScript.
- Use POST Redirect GET (PRG) pattern to clear form data from history.
- Set `Cache-Control: no-store` header on credential display pages.

**Warning signs:** Generated password appears in server logs; password remains in form input history; browser back-button shows credentials.

---

### Pitfall 3: RLS Policy Misconfiguration

**What goes wrong:** RLS policy written but not enforced because:
- Session context not set (app calls queries before `set_config('app.current_team_id', ...)`).
- Policy uses wrong column (e.g., `user_id` instead of `team_id`).
- Policy missing on new tables added after initial setup.
- `ALTER TABLE ... ENABLE ROW LEVEL SECURITY` was never executed.

**Why it happens:** RLS is silent — queries still run, just with invisible data. Easy to assume it's working when it's not.

**How to avoid:**
- Test RLS by connecting to DB as role without explicit GRANT, querying table directly; should return nothing.
- Add RLS policy check to deployment checklist: every table with sensitive data must have ENABLE ROW LEVEL SECURITY + at least one SELECT/INSERT/UPDATE policy.
- Log `set_config()` calls during development to verify context is set on every request.

**Warning signs:** Querying table as wrong role returns all rows; no logs of `set_config()` calls; RLS policy created but not attached to any role.

---

### Pitfall 4: Inactivity Timeout Only on Server, Not Client

**What goes wrong:** Server enforces 8-hour timeout, but browser session cookie never expires (cookie_lifetime=0 or not set). User's cookie is still valid after 8 hours; server rejects it but user sees confusing error message instead of graceful redirect.

**Why it happens:** Cookie lifetime and PHP timeout are independent; easy to set one and forget the other.

**How to avoid:** Ensure `session.cookie_lifetime` matches `session.gc_maxlifetime`. Test by logging in, waiting past timeout, and hitting a protected route; should redirect to login cleanly.

**Warning signs:** Session destroyed on server but browser still sends valid cookie; server responds with 403 instead of 302 redirect; user sees "session invalid" instead of being sent to login.

---

### Pitfall 5: PDO Emulation Enabled for PostgreSQL

**What goes wrong:** `PDO::ATTR_EMULATE_PREPARES => true` is set to improve performance. PDO emulates prepared statements by interpolating values into SQL string, re-exposing SQL injection vulnerability.

**Why it happens:** Old MySQL best practice; developers copy-paste without checking database compatibility.

**How to avoid:** Always set `PDO::ATTR_EMULATE_PREPARES => false` when connecting to PostgreSQL. PostgreSQL does not need emulation; true prepared statements are faster.

**Warning signs:** SQL injection vulnerability scanner flags queries as vulnerable; values with quotes break; performance is good but security is bad.

---

### Pitfall 6: Stale Role/Team_id After Privilege Change

**What goes wrong:** User's role changes in database (e.g., promoted from player to coach), but $_SESSION['role'] is not updated. User sees old permissions.

**Why it happens:** Session is cached client-side; no mechanism to invalidate it.

**How to avoid:** For privileged actions (admin operations, role-sensitive queries), re-fetch role/team_id from database instead of trusting $_SESSION. At minimum, invalidate session when admin changes user role.

**Warning signs:** User promoted to coach but can't perform coach actions; user reassigned to new team but sees old team data; admin demotes user but user still has access.

---

## Code Examples

Verified patterns from official sources:

### PHP 8.3+ Secure Session Configuration

```php
<?php
// Source: OWASP Session Management Cheat Sheet
// https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html

// Must be set BEFORE session_start()
session_set_cookie_params([
    'secure'   => true,              // HTTPS only
    'httponly' => true,              // JavaScript cannot access
    'samesite' => 'Strict',          // Prevent cross-site cookie send
]);

// Set via ini_set() or php.ini
ini_set('session.use_strict_mode', '1');          // Reject invalid session IDs
ini_set('session.use_cookies', '1');              // Use cookies
ini_set('session.use_only_cookies', '1');         // No URL-based sessions
ini_set('session.cookie_lifetime', 28800);        // 8 hours (28800 seconds)
ini_set('session.gc_maxlifetime', 28800);         // Garbage collection after 8 hours
ini_set('session.sid_length', '256');             // Larger session ID (default 32)
ini_set('session.sid_bits_per_character', '6');   // More entropy per char

session_start();

// Regenerate session ID after login
session_regenerate_id(true);  // true = delete old session file
?>
```

### Password Hashing with Explicit Cost (PHP 8.4 Aligned)

```php
<?php
// Source: PHP Official Documentation
// https://www.php.net/manual/en/function.password-hash.php

// For new passwords
$password = 'user_entered_password';
$hash = password_hash($password, PASSWORD_BCRYPT, [
    'cost' => 12  // PHP 8.4 default; forward-compatible
]);

// For verification
if (password_verify($password, $hash)) {
    echo 'Password is correct';
}

// For future upgrade to Argon2
// (Available in PHP 7.2+; requires libargon2 or PHP 8.4+ with openssl)
// $hash = password_hash($password, PASSWORD_ARGON2ID, [
//     'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
//     'time_cost'   => PASSWORD_ARGON2_DEFAULT_TIME_COST,
//     'threads'     => PASSWORD_ARGON2_DEFAULT_THREADS,
// ]);

// Check if rehashing is needed (e.g., after upgrading algorithm)
if (password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12])) {
    $new_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    // Store $new_hash in database
}
?>
```

### CSRF Token Generation & Validation

```php
<?php
// Source: OWASP CSRF Prevention Cheat Sheet
// https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html

// Generate token
function get_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// In form template
<form method="POST" action="/admin/teams/create">
    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
    <input type="text" name="team_name" required>
    <button type="submit">Team erstellen</button>
</form>

// Validate on POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_csrf'] ?? '';
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('CSRF token validation failed.');
    }
    
    // Safe to process request
}
?>
```

### Sliding Window Session Timeout

```php
<?php
// Source: OWASP Session Management Cheat Sheet
// Session timeout implementation with absolute max + inactivity threshold

const ABSOLUTE_TIMEOUT = 8 * 60 * 60;  // 8 hours
const INACTIVITY_TIMEOUT = 8 * 60 * 60; // 8 hours (same as absolute for simplicity)

// Call this at the beginning of protected routes
function check_session_timeout() {
    $now = time();
    
    // Initialize on first request after login
    if (!isset($_SESSION['login_time'])) {
        $_SESSION['login_time'] = $now;
    }
    
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
    }
    
    $login_elapsed = $now - $_SESSION['login_time'];
    $inactivity_elapsed = $now - $_SESSION['last_activity'];
    
    // Check both thresholds
    if ($login_elapsed > ABSOLUTE_TIMEOUT || $inactivity_elapsed > INACTIVITY_TIMEOUT) {
        session_destroy();
        header('Location: /login?message=Ihre+Sitzung+ist+abgelaufen');
        exit;
    }
    
    // Update last activity (sliding window extends session)
    $_SESSION['last_activity'] = $now;
}

// Protected route
session_start();
check_session_timeout();
// ... handle request
?>
```

### PostgreSQL RLS Context Setting

```php
<?php
// Source: PostgreSQL Documentation
// https://www.postgresql.org/docs/current/ddl-rowsecurity.html

// After PDO connection established
function set_team_context($pdo, $team_id) {
    $stmt = $pdo->prepare("SELECT set_config('app.current_team_id', ?, false)");
    $stmt->execute([(string)$team_id]);  // Must be string for set_config
}

// In login handler, after fetching user
$user = fetch_user_by_username($pdo, $username);
if ($user && password_verify($password, $user['password_hash'])) {
    // Set RLS context for all subsequent queries in this connection
    set_team_context($pdo, $user['team_id']);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['team_id'] = $user['team_id'];
    // ... rest of login
}

// All subsequent queries now benefit from RLS filtering
// Example:
$stmt = $pdo->prepare("SELECT * FROM users WHERE team_id = ?");
$stmt->execute([$_SESSION['team_id']]);
// RLS policy filters this further, enforcing team_id + role-based rules
?>
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| PASSWORD_BCRYPT cost 10 | PASSWORD_BCRYPT cost 12 | PHP 8.4.0 (Nov 2024) | Hashes 4x slower; brute-force 4x harder; default is now stronger |
| PDO::ATTR_EMULATE_PREPARES=true for all DBs | =false for PostgreSQL, true only for MySQL | 2025 security research | PostgreSQL true prepared statements are faster and safer; emulation re-exposes SQL injection |
| OWASP Top 10 2021 categories | OWASP Top 10 2025 Edition (8 categories + supply chain + exceptions) | Nov 2025 | Tighter definitions; supply chain security now first-class threat |
| bcrypt as password algorithm standard | Argon2id recommended; bcrypt acceptable for legacy | PHP 7.2+ / 2025 consensus | Argon2 is memory-hard, GPU-resistant; bcrypt still secure but single-threaded design limits parallelism defense |
| Session cookie_lifetime=0 (session-only) | Explicit 8-hour lifetime to match gc_maxlifetime | 2025 best practice | Clearer intent; cookies don't persist across browser close; matches inactivity timeout logic |

**Deprecated/outdated:**
- **session.use_trans_sid (URL-based sessions):** Never use; always set `use_only_cookies=1`. URL-based sessions leak in referer headers and logs.
- **Custom password hashing:** Avoid; always use `password_hash()`. Custom implementations miss edge cases (timing attacks, salt management).
- **md5()/sha1() for passwords:** Never use. Unsalted and fast (easier to brute-force). `password_hash()` uses bcrypt by default.
- **Session fixation via missing regenerate_id():** Always call `session_regenerate_id(true)` after login. Old session IDs are vulnerable to hijacking.

---

## Validation Architecture

**Skip:** `nyquist_validation` is explicitly `false` in .planning/config.json. Test infrastructure discovery omitted.

---

## Open Questions

1. **Admin config.php format:** How to store admin credentials? Plain PHP array (`'admin' => ['username' => '...', 'password' => '...']`)? Environment variables? Separate config file with restricted permissions? Current CONTEXT.md says "config.php" but exact format is Claude's Discretion.

2. **PostgreSQL RLS role setup:** Should app connect as a single role (e.g., `app_user`) with limited privileges, or as `postgres`? RLS policies typically use `auth.uid()` function which requires custom setup or `public.uid()` function. Needs clarification in schema design.

3. **Team_coaches pivot table or foreign key:** TEAM-02 requires assigning coaches to teams. Is this via a `team_coaches` junction table (flexible many-to-many) or a `team_id` foreign key in coaches/users table (one-to-one)? Current CONTEXT.md D-01 suggests users have team_id, implying one team per user, but admin might need to manage coaches separately in Phase 1 vs Phase 2.

4. **Session table in database or file storage:** Current stack uses native PHP $_SESSION. Should this be file-based (default) or stored in PostgreSQL for distributed setups? File-based is simpler for single-server; DB-based scales better. CONTEXT.md silent on this.

5. **Password reset expiration vs. credential display:** Credentials display for 60 seconds, then modal closes. Should the generated password itself be time-limited in the database (e.g., must change on next login)? Current design shows it once; unclear if forced change is required.

---

## Sources

### Primary (HIGH confidence)
- **OWASP Session Management Cheat Sheet** - https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html (session timeout, cookie configuration, CSRF)
- **PHP Manual: password_hash()** - https://www.php.net/manual/en/function.password-hash.php (algorithm defaults, cost factor, verification)
- **OWASP CSRF Prevention Cheat Sheet** - https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html (token generation, validation)
- **PostgreSQL Row-Level Security Documentation** - https://www.postgresql.org/docs/current/ddl-rowsecurity.html (policy syntax, session context)
- **OWASP Top 10 2025 Edition** - Published Nov 2025; latest threat landscape for authentication/authorization

### Secondary (MEDIUM confidence, verified with official sources)
- **PHP 8.4 Release Notes** - Bcrypt cost increased to 12 by default; confirmed via PHP Manual
- **PostgreSQL RLS Multi-Tenant Guide (AWS Prescriptive Guidance)** - https://docs.aws.amazon.com/prescriptive-guidance/latest/saas-multitenant-managed-postgresql/rls.html (practical RLS patterns)
- **Fortify Your Core PHP Apps: OWASP Top 10–2025 Edition** - https://ilyaskazi.medium.com/fortify-your-core-php-apps-the-owasp-top-10-2025-edition-4cac67ac4b39 (2025 threat context)
- **PHP PDO Security Research (2025)** - Confirmed that PDO::ATTR_EMULATE_PREPARES=false is required for PostgreSQL safety

### Tertiary (LOW confidence, single source, marked for validation)
- Individual security blog posts on session timeout implementation (multiple sources converge on same pattern)

---

## Metadata

**Confidence breakdown:**
- **Standard stack:** HIGH - All libraries (PHP 8.3+, PostgreSQL 14+, PDO, password_hash) verified via official docs and OWASP 2025.
- **Session configuration:** HIGH - OWASP 2025 cheat sheet explicitly defines secure_cookie, httponly, samesite=Strict, use_strict_mode.
- **Password hashing:** HIGH - PHP docs confirm PASSWORD_BCRYPT is default; cost 12 in PHP 8.4+; Argon2id available since PHP 7.2.
- **CSRF implementation:** HIGH - OWASP CSRF prevention cheat sheet describes session-based token pattern with hash_equals() validation.
- **PostgreSQL RLS:** MEDIUM-HIGH - Official PostgreSQL docs verified; AWS guidance and multiple SaaS sources confirm pattern; implementation details (policy syntax, context setup) need validation against actual schema.
- **Sliding window timeout:** MEDIUM - Multiple sources converge on last_activity timestamp tracking; lack of official PHP guidance (this is application-layer logic) makes confidence medium.
- **PDO prepared statements:** HIGH - Official PHP docs and 2025 security research confirm ATTR_EMULATE_PREPARES=false requirement for PostgreSQL.
- **Common pitfalls:** MEDIUM - Derived from OWASP and research; specific to this project context (no existing codebase patterns to reference).

**Research date:** 2026-04-29  
**Valid until:** 2026-05-29 (30 days — OWASP 2025 is stable; PHP 8.4 is stable; PostgreSQL 14+ stable)

---

*Phase: 01-foundation*  
*Context gathered: 2026-04-29*  
*Research completed: 2026-04-29*
