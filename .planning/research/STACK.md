# Technology Stack

**Project:** Team Manager (PHP/PostgreSQL Mobile-First Web App)
**Researched:** 2026-04-28
**Confidence Level:** HIGH (all recommendations verified against PHP 8.4+ official documentation)

## Recommended Stack

### Core Runtime & Language
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PHP | 8.3+ (8.4+ preferred) | Server-side application logic | Current stable version with security patches; 8.4 adds improved JSON support and performance |
| PostgreSQL | 14+ (15+ preferred) | Relational database | ACID compliance, excellent JSON support, superior to MySQL for complex queries and team data |

### Web Server
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| nginx or Apache 2.4+ | Latest stable | HTTP server | nginx is lighter and faster for PHP-FPM; Apache is simpler for shared hosting. Either works for team manager scale. |
| PHP-FPM | Bundled with PHP | FastCGI process manager | Standard approach for separating web server from PHP runtime; essential for performance and security |

### Database Access (Abstraction)
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PDO (PHP Data Objects) | Built-in since PHP 5.1 | Database access layer | Ships with PHP; provides consistent interface across databases; supports prepared statements (SQL injection prevention); lighter weight than ORMs |
| PDO_PGSQL driver | Built-in with PHP | PostgreSQL-specific driver | Official driver for PostgreSQL; required to use PDO with Postgres |

### Authentication & Security
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| password_hash() / password_verify() | Built-in (PHP 5.5+) | Password hashing | Official PHP recommendation; uses bcrypt (PASSWORD_DEFAULT) with automatic salt generation; configurable cost parameter |
| session_start() with options | Built-in (PHP 7.1+ with enhancements in 8.x) | Session management | Native PHP sessions with security best practices: `cookie_secure`, `cookie_httponly`, `cookie_samesite`, `use_strict_mode` |
| CSRF token generation | Built-in via $_SERVER superglobal | CSRF protection | Use random tokens from `random_bytes()` and validate via `hash()` or `bin2hex()` |

### Input Validation & Sanitization
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| filter_var() / filter_input() | Built-in (PHP 5.2+) | Input validation & sanitization | Official approach for validating emails, URLs, integers; prevents invalid data in database |
| htmlspecialchars() | Built-in | Output escaping | Prevents XSS attacks; required when outputting user data to HTML |

### Frontend CSS Framework
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Bootstrap | 5.3+ (via CDN) | Mobile-first CSS framework | Pre-built components (forms, tables, cards); mobile-first design; no build step required via CDN; excellent documentation; widespread browser support |
| Pure CSS with Flexbox/Grid | CSS3 (native browser support) | Layout & responsive design | Modern CSS handles mobile-first without framework; use `@media` queries for breakpoints; Flexbox for forms and lists; Grid for complex layouts |

**Note:** Do NOT use Tailwind CSS for this project. Tailwind requires a Node.js build pipeline (npm, PostCSS), which contradicts the "keep it simple, no heavy JS framework" requirement. Bootstrap via CDN provides immediate mobile-first styling without build tooling.

### Form & Template Handling
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Native PHP templating | N/A | HTML output & form rendering | For small, simple projects, native PHP (heredoc, nowdoc, interpolation) is sufficient; no dependency overhead; direct control over HTML output |
| No separate template engine required | N/A | Keep build complexity low | Twig/Blade add unnecessary complexity; this project has predictable, German-language UI without dynamic template inheritance needs |

### JSON Handling
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| json_encode() / json_decode() | Built-in (PHP 5.2+) | JSON serialization | For API responses, configuration, data export; built-in with excellent performance |

### Development Tools
| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Composer | 2.5+ (optional) | PHP package manager | Only if external dependencies needed; for this stack, all core features use built-in PHP |
| Git | 2.30+ | Version control | Standard for all projects |

---

## Installation

### Initial Setup

```bash
# PostgreSQL (macOS/Homebrew example)
brew install postgresql@15
brew services start postgresql@15
createdb team_manager

# PHP with required extensions (macOS/Homebrew)
brew install php@8.3
# Enable PHP-FPM
brew services start php@8.3

# Verify installations
php -v
psql --version
php -m | grep -E "pdo|pgsql"

# Create .env or config file
cat > config.php << 'EOF'
<?php
// Database connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'team_manager');
define('DB_USER', 'postgres');
define('DB_PASS', '');  // Set from environment
define('DB_PORT', 5432);

// Admin credentials (single admin in config)
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('change_me_now', PASSWORD_DEFAULT));

// Session security settings
define('SESSION_LIFETIME', 86400);  // 24 hours

// UI Language
define('APP_LOCALE', 'de_DE');
EOF
```

### Database Initialization

```php
<?php
// bootstrap.php - Connect and initialize
try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}
?>
```

### Session Configuration (Secure)

```php
<?php
// session.php - Set before any output
session_cache_limiter('');
session_start([
    'name' => '__Secure-SessionId',
    'cookie_lifetime' => SESSION_LIFETIME,
    'cookie_path' => '/',
    'cookie_secure' => true,              // HTTPS only
    'cookie_httponly' => true,            // No JavaScript access
    'cookie_samesite' => 'Strict',        // CSRF protection
    'sid_length' => 96,                   // Strong randomness
    'sid_bits_per_character' => 5,        // More entropy
    'use_strict_mode' => true,            // Reject invalid IDs
]);
// Immediately release lock for concurrent requests
session_write_close();
?>
```

### HTML Template Boilerplate

```html
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Manager</title>
    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Mobile-first custom styles */
        body {
            font-size: 1rem;
            line-height: 1.5;
        }
        @media (min-width: 768px) {
            body {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-3">
        <h1>Team Manager</h1>
        <!-- Content here -->
    </div>
    
    <!-- Bootstrap JS Bundle (required for dropdowns, modals, etc.) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

### Form Handling with CSRF Protection

```php
<?php
// Generate CSRF token
function get_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// In HTML form:
echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token()) . '">';

// When processing POST:
if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('CSRF token invalid');
}
?>
```

### Password Management

```php
<?php
// Hash a password (for admin setup or password reset)
$plain_password = 'user_password_here';
$hash = password_hash($plain_password, PASSWORD_DEFAULT, ['cost' => 13]);
// Store $hash in database

// Verify a password (during login)
$stored_hash = '...';  // From database
$provided_password = $_POST['password'];
if (password_verify($provided_password, $stored_hash)) {
    // Password correct
    $_SESSION['user_id'] = $user_id;
    session_write_close();
    header('Location: /dashboard');
} else {
    // Password incorrect
    echo 'Invalid credentials';
}
?>
```

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Framework | None (vanilla PHP) | Laravel, Symfony | Adds complexity for simple CRUD app; slows onboarding; overkill for single admin, fixed features |
| Database abstraction | PDO | Doctrine ORM, Eloquent | ORMs add overhead; PDO prepared statements sufficient for straightforward queries; simpler to debug |
| CSS framework | Bootstrap (CDN) | Tailwind CSS | Tailwind requires Node.js build pipeline; contradicts "keep it simple" requirement and no heavy JS framework constraint |
| Frontend JS | None (progressive enhancement) | React, Vue, Alpine | Not needed for team manager workflow; HTMX/htmx could add interactivity without framework overhead if required later |
| Session storage | Native PHP $_SESSION | Redis, Memcached | PHP file-based sessions sufficient for single-admin, small team scale; Redis adds infrastructure burden |
| Template engine | Native PHP | Twig, Blade | Native PHP is simpler and faster for straightforward HTML; no inheritance complexity needed |
| Password hashing | password_hash() | bcrypt directly, custom | Official PHP recommendation; PASSWORD_DEFAULT evolves as algorithms improve |

---

## Stack Rationale Summary

This stack prioritizes **simplicity, security, and zero additional infrastructure**:

- **PHP 8.3+**: Modern language features, built-in security, stable, widely hosted
- **PostgreSQL**: Superior JSON/JSONB support (for flexible column types), ACID guarantee, better for team data integrity
- **PDO**: Lightweight, prevents SQL injection via prepared statements, no ORM overhead
- **Native Sessions**: Built-in, secure with proper configuration, no external dependencies
- **Bootstrap via CDN**: Mobile-first, no build step, immediate styling, extensive docs
- **No framework**: All business logic in simple PHP functions/classes; clear request → process → respond flow

**Why NOT heavy tools here:**
- No Node.js build pipeline → No npm, no webpack, no asset compilation
- No ORM → Direct SQL queries with PDO parameterization; easier to understand and optimize
- No JS framework → HTML/CSS/minimal JS; forms work without JavaScript; progressive enhancement

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| PHP version (8.3+) | HIGH | Official docs confirm; security updates current; widely deployed |
| PDO for database access | HIGH | Official PHP recommendation; prepares statements natively; PostgreSQL driver built-in |
| password_hash() / session_start() | HIGH | Built-in, documented, widely tested; PASSWORD_DEFAULT evolves safely |
| Bootstrap 5 via CDN | HIGH | Stable, mobile-first, widely supported; no build dependency |
| No framework | HIGH | Verified against project constraints: simple CRUD, fixed UI, no heavy JS |
| Native sessions configuration | MEDIUM-HIGH | Security options verified; requires careful configuration but no external service needed |
| PostgreSQL 14+ | HIGH | Stable, excellent team data support, ACID, JSON columns for future flexibility |

---

## Next Steps for Phase 1

1. **Set up local dev environment**: PHP 8.3, PostgreSQL 15, Git
2. **Create config.php**: Database credentials, admin hash (see boilerplate above)
3. **Initialize database schema**: Teams, Coaches, Players, Lists, Columns, Rows, Statistics
4. **Build authentication flow**: Login form (German UI), session management, password reset for coaches/players
5. **Implement roster management**: CRUD for coaches/players, list creation with column definitions
6. **Add responsive UI**: Bootstrap grid, mobile-optimized forms, German labels

---

## Sources

- [PHP Documentation](https://www.php.net/manual/en/) - Official PHP reference, password hashing, PDO, sessions, filtering
- [PostgreSQL Official Docs](https://www.postgresql.org/docs/) - Database documentation
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.3/) - Mobile-first CSS framework
- [MDN Web Docs - Responsive Design](https://developer.mozilla.org/en-US/docs/Learn/CSS/) - CSS best practices, mobile-first approach
- [OWASP PHP Security](https://owasp.org/www-community/) - Security best practices for session, CSRF, input validation
