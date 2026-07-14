# Phase 6: Calendar — Lists with Date, Location & ICS Export - Research

**Researched:** 2026-07-14
**Domain:** Calendar view implementation, ICS file generation, date/location field extension
**Confidence:** HIGH

## Summary

Phase 6 adds optional `location` field to lists and introduces a switchable calendar timeline view (week/month) showing only dated entries, with undated entries in a separate section. A public ICS file per team enables calendar app subscriptions. The implementation leverages PHP's built-in `DateTime` for week/month calculations, PostgreSQL date queries with NULL handling, and RFC 5545-compliant ICS generation using native PHP string formatting (no external library required for simple VEVENT structure). Key risks center on timezone handling and RFC 5545 line-ending compliance (CRLF, not LF).

**Primary recommendation:** Use native PHP DateTime for calendar calculations, generate ICS on-demand (not cached) per CONTEXT decision, handle NULL dates explicitly in SQL queries with ORDER BY logic, store all dates as PostgreSQL `DATE` type (no time component — all-day events), and enforce CRLF line endings in ICS output to ensure compatibility with Apple Calendar, Outlook, and iCloud.

## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Timeline/Listenansicht (no visual grid, server-side rendering)
- **D-02:** Woche/Monat-Switcher with GET parameters (`?view=week&offset=0`)
- **D-03:** Default view: current week when page loads
- **D-04:** Datierte Einträge chronologisch in Periode; undatierte im eigenen Abschnitt "Ohne Datum"
- **D-05:** Calendar entry shows: Datum + Name + Visibility Badge + Ort (if present)
- **D-06–09:** Tab-Switcher on existing content page (not new nav); Kalender tab is default; role-aware visibility
- **D-10–14:** ICS export: one public file per team, on-demand generated, URL `/ics/{team_id}.ics`, no auth required, public + protected lists only
- **D-15–17:** Location field: `VARCHAR(255) NULL`, optional text, appears in forms/cards/calendar/ICS as LOCATION field

### Claude's Discretion
- Tab-Switcher design: Bootstrap `.nav-tabs` or `.btn-group` toggle
- Wochentags-Gruppierung: HTML structure (e.g., day header `<h6>` + entries below)
- ICS VEVENT format: `DTSTART/DTEND` as `DATE` (all-day, YYYYMMDD format)
- `DTSTAMP`, `UID`, `PRODID` per RFC 5545
- Idempotent schema migration: `ALTER TABLE lists ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL`
- New ICS route in `public/index.php` without auth middleware

### Deferred Ideas (Out of Scope)
- None noted in CONTEXT.md

## Standard Stack

### Core Libraries & Tools
| Library/Tool | Version/Type | Purpose | Why Standard |
|--------------|--------------|---------|--------------|
| PHP `DateTime` | Built-in (PHP 5.2+) | Week/month calculations, date formatting | Ships with PHP; ISO 8601 compliant; handles timezones cleanly |
| PostgreSQL `DATE` type | Built-in column type | Store list dates (no time) | ACID-compliant; NULL-safe; proper ordering semantics |
| PDO prepared statements | Built-in (PHP 5.1+) | Date range queries, NULL filtering | Prevents SQL injection; native parameterization |
| Bootstrap 5.3 timeline patterns | Via CDN | Mobile-first vertical timeline layout | No build step; responsive flexbox; proven mobile UX |
| Native PHP string output | Built-in | RFC 5545 ICS file generation | Simple structure (no recurrence); avoids external dependency |

### Supporting Patterns
| Pattern | When to Use | Notes |
|---------|------------|-------|
| `DateTime::createFromFormat('Y-m-d', $dateString)` | Parse database DATE strings | Explicit format; handles invalid dates gracefully |
| `$dt->modify('Monday this week')` | Calculate week boundaries | ISO 8601 compliant; Monday-to-Sunday week start |
| `intval($dt->format('W'))` | Get ISO week number | Returns 1–53; format 'W' is ISO 8601 week |
| `$dt->format('d.m.Y')` | Display dates to German users | Matches project UI conventions |
| `BETWEEN $startDate AND $endDate` SQL | Filter dated lists in range | Prefer range queries over date extraction functions for index efficiency |
| `WHERE date IS NOT NULL` | Separate dated from undated | Explicit NULL check; improves query optimizer selectivity |
| `ORDER BY date DESC NULLS LAST` | Sort with undated at bottom | PostgreSQL clause; equivalent to CASE logic in MySQL |

### Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|------------|-------------|-----|
| ICS generation (VEVENT structure, line folding, escaping) | Custom string concatenation | Native PHP string formatting with RFC 5545 compliance checklist | Line endings (CRLF, not LF) cause silent failure in Apple Calendar; field escaping prevents parse errors; DTSTAMP and UID required for deduplication |
| Week/month boundaries | Manual date math (adding/subtracting days) | `DateTime::modify('Monday this week')` + `strtotime()` helpers | Off-by-one errors in DST transitions; leap-year logic; ISO 8601 week boundaries differ from calendar grid weeks |
| Timezone conversion for display | `date()` function without explicit TZ | `DateTime` object with `DateTimeZone('Europe/Berlin')` | date_default_timezone_set() is unreliable; DateTime is explicit and testable; project likely uses UTC DB + local display |

**Key insight:** RFC 5545 ICS requires precise line endings and field formatting. The spec is 200+ pages, but a simple single-team, no-recurrence feed needs ~50 lines of boilerplate (PRODID, VERSION, CALSCALE, METHOD). Use a checklist: CRLF line endings, proper UID generation, DTSTAMP on creation, LOCATION field optional but valid.

## Architecture Patterns

### Recommended Project Structure
```
src/
├── coordinator/
│   ├── lists_handler.php          # Extended with calendar view logic
│   └── ics_handler.php            # New: ICS generation (public route)
├── member/
│   └── lists_handler.php          # Extended with calendar view (members see public only)
├── templates/
│   ├── coordinator/
│   │   └── lists.php              # Tab-switcher (Kalender | Liste) + timeline HTML
│   └── member/
│       └── lists.php              # Tab-switcher + timeline (public lists only)
├── utils/
│   └── calendar.php               # New helper: week/month boundary calculations
└── db/
    └── connection.php             # (no changes; use existing PDO)

database/
└── schema.sql                      # ALTER TABLE lists ADD COLUMN IF NOT EXISTS location
```

### Pattern 1: Week/Month Boundary Calculation
**What:** Given a date and `?view=week&offset=0`, compute start/end dates for the period
**When to use:** On every calendar view render
**Example:**
```php
// Source: https://www.php.net/manual/en/class.datetime.php
function getWeekBoundaries(DateTime $current, int $weekOffset): array {
    $monday = clone $current;
    $monday->modify('Monday this week')->modify("+{$weekOffset} week");
    $sunday = clone $monday;
    $sunday->modify('Sunday this week');
    return [
        'start' => $monday->format('Y-m-d'),
        'end'   => $sunday->format('Y-m-d'),
        'label' => $monday->format('d.') . ' – ' . $sunday->format('d.m.Y')
    ];
}

function getMonthBoundaries(DateTime $current, int $monthOffset): array {
    $first = clone $current;
    $first->modify('first day of +' . $monthOffset . ' month');
    $last = clone $first;
    $last->modify('last day of this month');
    return [
        'start' => $first->format('Y-m-d'),
        'end'   => $last->format('Y-m-d'),
        'label' => $first->format('F Y') // Monat Jahr
    ];
}
```

### Pattern 2: SQL Query with NULL Date Handling
**What:** Fetch dated lists within a period + undated lists separately
**When to use:** In coordinator/member lists handlers before rendering calendar
**Example:**
```php
// Source: PostgreSQL documentation, BETWEEN operator
// Dated entries in week/month window
$stmt = $pdo->prepare("
    SELECT id, name, visibility, date, location, type
    FROM (
        SELECT id, name, visibility, date, location, 'list' AS type
        FROM lists
        WHERE team_id = ? AND date BETWEEN ? AND ?
        UNION ALL
        SELECT id, name, visibility, date, location, 'file' AS type
        FROM files
        WHERE team_id = ? AND date BETWEEN ? AND ?
    ) items
    ORDER BY date ASC
");
$stmt->execute([
    $_SESSION['team_id'], $startDate, $endDate,
    $_SESSION['team_id'], $startDate, $endDate
]);
$datedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Undated entries (separate section)
$undatedStmt = $pdo->prepare("
    SELECT id, name, visibility, date, location, type
    FROM (
        SELECT id, name, visibility, date, location, 'list' AS type
        FROM lists
        WHERE team_id = ? AND date IS NULL
        UNION ALL
        SELECT id, name, visibility, date, location, 'file' AS type
        FROM files
        WHERE team_id = ? AND date IS NULL
    ) items
    ORDER BY created_at DESC
");
$undatedStmt->execute([$_SESSION['team_id'], $_SESSION['team_id']]);
$undatedItems = $undatedStmt->fetchAll(PDO::FETCH_ASSOC);
```

### Pattern 3: RFC 5545 ICS Generation (On-Demand)
**What:** Generate VEVENT entries for all public/protected lists with dates, format as RFC 5545-compliant ICS file
**When to use:** On GET `/ics/{team_id}.ics` (no auth, no caching)
**Example:**
```php
// Source: RFC 5545 (https://datatracker.ietf.org/doc/html/rfc5545)
// Key: CRLF line endings (\r\n), line folding for >75 chars, DTSTAMP/UID required

function generateIcsFile(int $teamId, PDO $pdo): string {
    // Fetch public + protected lists with dates
    $stmt = $pdo->prepare("
        SELECT id, name, date, location, visibility
        FROM lists
        WHERE team_id = ? AND date IS NOT NULL AND visibility IN ('public', 'protected')
        ORDER BY date ASC
    ");
    $stmt->execute([$teamId]);
    $lists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ICS header
    $ics = "BEGIN:VCALENDAR\r\n";
    $ics .= "VERSION:2.0\r\n";
    $ics .= "PRODID:-//Team Manager//NONSGML v1.0//EN\r\n";
    $ics .= "CALSCALE:GREGORIAN\r\n";
    $ics .= "METHOD:PUBLISH\r\n";

    // VEVENT for each list
    foreach ($lists as $list) {
        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:" . md5($teamId . '-' . $list['id'] . '-' . date('Y-m-d H:i:s')) . "@team-manager.local\r\n";
        $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $ics .= "DTSTART;VALUE=DATE:" . str_replace('-', '', $list['date']) . "\r\n";
        $ics .= "SUMMARY:" . iCalendarEscape($list['name']) . "\r\n";
        if (!empty($list['location'])) {
            $ics .= "LOCATION:" . iCalendarEscape($list['location']) . "\r\n";
        }
        $ics .= "END:VEVENT\r\n";
    }

    $ics .= "END:VCALENDAR";
    return $ics;
}

function iCalendarEscape(string $text): string {
    // Escape special characters per RFC 5545 3.3.11
    return str_replace([",", ";", "\\", "\n"], ["\\,", "\\;", "\\\\", "\\n"], $text);
}
```

### Pattern 4: Tab-Switcher UI (Bootstrap)
**What:** Two-tab interface: "Kalender" (default) and "Liste" with GET params to maintain view state
**When to use:** In lists.php template (coordinator and member)
**Example:**
```php
// Source: Bootstrap 5.3 nav-tabs (https://getbootstrap.com/docs/5.3/components/navs-tabs/)
<?php
$currentView = $_GET['view'] ?? 'calendar';
$viewUrl = fn(string $v) => "/coordinator/lists?view={$v}&offset=" . (int)($_GET['offset'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <ul class="nav nav-tabs flex-nowrap">
        <li class="nav-item">
            <a class="nav-link <?= $currentView === 'calendar' ? 'active' : '' ?>" 
               href="<?= $viewUrl('calendar') ?>">
                <i class="bi bi-calendar3 me-2"></i>Kalender
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $currentView === 'list' ? 'active' : '' ?>" 
               href="<?= $viewUrl('list') ?>">
                <i class="bi bi-list-ul me-2"></i>Liste
            </a>
        </li>
    </ul>
</div>

<?php if ($currentView === 'calendar'): ?>
    <!-- Calendar view: timeline, navigation, ICS link -->
<?php else: ?>
    <!-- List view: existing card layout -->
<?php endif; ?>
```

### Pattern 5: Calendar Timeline Rendering (Mobile-First)
**What:** Vertical timeline grouped by day/week, with date headers and entry cards
**When to use:** In calendar tab of lists.php
**Example:**
```php
// Source: Bootstrap flexbox utilities (https://getbootstrap.com/docs/5.3/utilities/flex/)
<div class="mb-4">
    <small class="text-muted">
        Woche: <?= htmlspecialchars($weekLabel) ?>
    </small>
</div>

<!-- Dated entries -->
<?php if (!empty($datedItems)): ?>
<div class="timeline">
    <?php
    $currentDate = null;
    foreach ($datedItems as $item):
        $itemDate = $item['date'];
        if ($itemDate !== $currentDate):
            if ($currentDate !== null): echo '</div>'; endif;
            $currentDate = $itemDate;
            $dt = DateTime::createFromFormat('Y-m-d', $itemDate);
            ?>
    <div class="mb-3">
        <h6 class="text-muted border-bottom pb-2">
            <i class="bi bi-calendar3 me-2"></i><?= $dt->format('l, d.m.Y') ?>
        </h6>
        <?php
        endif;
        $detailUrl = $item['type'] === 'file' ? '/coordinator/files/' . (int)$item['id']
                                              : '/coordinator/lists/'  . (int)$item['id'];
        ?>
        <div class="card card-sm mb-2 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="flex-grow-1">
                        <a href="<?= htmlspecialchars($detailUrl) ?>" class="text-decoration-none">
                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                        </a>
                        <?php if (!empty($item['location'])): ?>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($item['location']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <span class="badge <?= $badgeClass($item['visibility']) ?>">
                        <?= $badgeLabel($item['visibility']) ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Undated entries -->
<?php if (!empty($undatedItems)): ?>
<div class="mt-4">
    <h6 class="text-muted border-bottom pb-2">Ohne Datum</h6>
    <?php foreach ($undatedItems as $item): ?>
    <div class="card card-sm mb-2 shadow-sm">
        <!-- Same card structure as dated items -->
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
```

### Anti-Patterns to Avoid
- **Manual date math:** Don't add/subtract hardcoded day values; use `DateTime::modify()` with relative formats
- **Mixing DATE and TIMESTAMP:** Store all list dates as `DATE` type (no time); avoids timezone confusion for all-day events
- **LF-only line endings in ICS:** Apple Calendar silently fails; Outlook rejects; always use `\r\n`
- **Missing UID in VEVENT:** Calendar apps treat duplicate UIDs as the same event; generates confusing duplicates
- **Fetching all lists then filtering in PHP:** Use SQL `BETWEEN` clause; lets database index optimize
- **Assuming `date_default_timezone_set()` is enough:** Use `DateTime` with explicit `DateTimeZone` for clarity

## Common Pitfalls

### Pitfall 1: RFC 5545 Line Ending Compliance
**What goes wrong:** ICS file generated with LF (`\n`) instead of CRLF (`\r\n`) imports silently into Google Calendar but fails on Apple Calendar/iCloud. File looks valid in text editor (no visible difference) but calendar apps reject it.
**Why it happens:** PHP on Linux outputs LF by default; developer tests locally and doesn't see failure; production deployment uses different server OS and breaks
**How to avoid:** Explicitly use `\r\n` in all ICS output; never use `PHP_EOL` (platform-dependent); add a compliance check: verify first VEVENT line contains `\r\n` (hex dump or string test)
**Warning signs:** ICS file downloads and appears valid but doesn't import in Apple Calendar or Outlook (test with real calendar apps, not text viewers)

### Pitfall 2: NULL Date Handling in Sorting
**What goes wrong:** Lists with `date IS NULL` appear at random positions in calendar query results, or undated lists mixed with dated entries when developer forgets WHERE clause
**Why it happens:** SQL `ORDER BY date ASC` puts NULLs first/last depending on database; developer assumes specific behavior; no explicit `WHERE date IS NOT NULL` filter
**How to avoid:** Always separate dated/undated queries; use explicit `WHERE date IS NOT NULL` for calendar, `WHERE date IS NULL` for undated section; PostgreSQL: use `NULLS LAST` in ORDER BY clause
**Warning signs:** Calendar shows undated list entries interspersed with dates; week/month view has gaps or out-of-order entries

### Pitfall 3: Week/Month Boundary Calculation Errors
**What goes wrong:** Week starts on Saturday instead of Monday; month view misses first/last days; navigation offset goes into previous/next year without handling
**Why it happens:** Developer uses `date('Y-m-d', strtotime('+1 week'))` which adds 7 days (not necessarily a week boundary); assumes `DateTime::modify('Monday this week')` works identically across timezones (it doesn't if TZ not set); DST transitions cause offset confusion
**How to avoid:** Always use `DateTime::modify('Monday this week')` with explicit `DateTimeZone` set; test boundaries in January/February (DST edge cases); test with offset = -1 (previous week/month) to ensure year boundary handling
**Warning signs:** Navigation wraps year incorrectly (Dec → Jan boundary); Monday label doesn't match actual ISO week start date

### Pitfall 4: Missing ICS DTSTAMP or UID
**What goes wrong:** Calendar apps import old version of event or treat each import as duplicate; event deduplication fails; user sees same event imported multiple times
**Why it happens:** Developer omits DTSTAMP (creation time of ICS record, not event start) or uses a static UID across all events; calendar app's dedup logic has nothing to compare against previous import
**How to avoid:** DTSTAMP must be `gmdate('Ymd\THis\Z')` (current time in UTC, always); UID must be unique per-event per-feed (use md5 hash of team_id + list_id or similar); regenerate UID on each export to ensure imports are treated as updates
**Warning signs:** User imports ICS multiple times, sees duplicate events; event changes don't update in calendar app (client-side dedup sees old UID and ignores update)

### Pitfall 5: Timezone Display Confusion
**What goes wrong:** Date displays incorrectly (off by one day or shows UTC instead of local); calendar shows event on wrong date due to timezone conversion
**Why it happens:** Database stores `DATE` type as UTC-neutral, but PHP DateTime assumes server's default timezone; developer formats date without explicit timezone context
**How to avoid:** All list dates stored as PostgreSQL `DATE` (no time component); always display with explicit format like `$dt->format('d.m.Y')` (ignores time); if time ever added, store as `TIMESTAMP WITH TIME ZONE` in UTC and convert to Europe/Berlin on display
**Warning signs:** Date off by one in certain regions; ICS file shows date in different timezone notation than expected

### Pitfall 6: On-Demand ICS Performance (Misguided Caching)
**What goes wrong:** Developer pre-caches ICS file to avoid recomputation, but cache invalidation logic breaks and old events stay in calendar subscriptions forever
**Why it happens:** CONTEXT decision D-12 specifies on-demand generation (10 teams × 15 items = 50 DB rows max); developer assumes load will be high and adds Redis/file cache without measuring; cache TTL logic has bug or doesn't exist
**How to avoid:** Follow D-12: generate on-demand, no caching (simple index query + string loop is negligible load); if metrics show otherwise, add simple HTTP Cache-Control header (`max-age=3600`) letting client/CDN cache, but never server-side cache ICS output
**Warning signs:** Calendar subscription shows old events after coordinator deletes/updates; user unsubscribes and resubscribes to force refresh; logs show frequent 304 Not Modified responses (good sign caching works, bad sign overengineering)

## Code Examples

Verified patterns from official sources:

### PHP DateTime Week Navigation
```php
// Source: https://www.php.net/manual/en/class.datetime.php
$current = new DateTime();
$offset = (int)($_GET['offset'] ?? 0);

$monday = clone $current;
$monday->modify("Monday this week");
if ($offset !== 0) {
    $modifier = $offset > 0 ? "+{$offset} weeks" : "{$offset} weeks";
    $monday->modify($modifier);
}

$sunday = clone $monday;
$sunday->modify('Sunday this week');

echo "Week: " . $monday->format('d.m.Y') . " - " . $sunday->format('d.m.Y');
```

### SQL Date Range Query with NULL Separation
```sql
-- Source: PostgreSQL documentation (BETWEEN, IS NULL operators)
-- Dated items in week/month
SELECT id, name, date, location, visibility
FROM lists
WHERE team_id = $1 
  AND date IS NOT NULL
  AND date BETWEEN $2 AND $3
ORDER BY date ASC;

-- Undated items (separate query or UNION)
SELECT id, name, date, location, visibility
FROM lists
WHERE team_id = $1 
  AND date IS NULL
ORDER BY created_at DESC;
```

### ICS VEVENT Escaping
```php
// Source: RFC 5545 section 3.3.11 (https://datatracker.ietf.org/doc/html/rfc5545#section-3.3.11)
function escapeIcsField(string $value): string {
    // Escape: comma, semicolon, backslash, newline
    $value = str_replace("\\", "\\\\", $value); // Backslash first!
    $value = str_replace("\n", "\\n", $value);
    $value = str_replace(",", "\\,", $value);
    $value = str_replace(";", "\\;", $value);
    return $value;
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Custom date picker JS library (jQuery UI, Tempusdominus) | Bootstrap 5 native `<input type="date">` + mobile OS calendar | HTML5 spec adoption (2014+, mainstream by 2020) | Removes 50KB+ of JS dependencies; native mobile calendar UX superior on phones |
| Server-side session state for calendar view (stored in `$_SESSION['current_week']`) | GET query parameters (`?view=week&offset=0`) | REST principles + browser history support (2010s) | Users can share calendar URLs; back button works; bookmarkable views |
| Manual day-by-day iteration for timeline | `DateTime::modify()` with ISO 8601 formats | DateTime class maturity in PHP 5.3+ (widespread adoption 2015+) | Fewer off-by-one errors; DST handling; more testable |
| iCal library (ICal4j, iCal.js) for ICS generation | Native RFC 5545 string formatting for simple feeds | Reduced dependency philosophy (2010s+) | Avoids 500KB+ library for 50-line spec compliance; faster performance |

**Deprecated/outdated:**
- `date()` function for anything involving timezones or complex calculations — replaced by `DateTime` (more explicit, testable)
- Form-based calendar widgets (HTML4 era) — replaced by native HTML5 `<input type="date">` (mobile UX, no deps)
- Hardcoded SQL date extraction (e.g., `EXTRACT(WEEK FROM date)`) — use `DateTime` in PHP for portability

## Sources

### Primary (HIGH confidence)
- [RFC 5545 – Internet Calendaring and Scheduling Core Object Specification](https://datatracker.ietf.org/doc/html/rfc5545) — Authoritative ICS format specification
- [PHP DateTime Class Documentation](https://www.php.net/manual/en/class.datetime.php) — Week/month boundary calculations, format methods
- [PostgreSQL DATE Type Documentation](https://www.postgresql.org/docs/14/datatype-datetime.html) — NULL handling, range operators, indexing
- [Bootstrap 5.3 Timeline Examples](https://mdbootstrap.com/docs/standard/extended/timeline/) — Mobile-first responsive timeline patterns
- [RFC 5545 iCalendar Recommended Practices](https://icalendar.org/iCalendar-RFC-5545/5-recommended-practices.html) — CRLF, DTSTAMP, UID best practices

### Secondary (MEDIUM confidence)
- [Building an RFC 5545 iCal File Generator — Line Folding, Escaping, and All](https://dev.to/sendotltd/building-an-rfc-5545-ical-file-generator-line-folding-escaping-and-all-5fid) — Practical ICS generation checklist, escaping rules
- [PHP DateTime Timezone Guide](https://codegive.com/blog/php_datetime_with_timezone.php) — DateTime object timezone handling, DateTimeZone usage
- [SQL NULL Handling Best Practices](https://dev.to/thecodeliner/handling-null-values-in-sql-best-practices-and-common-pitfalls-2pgh) — NULL in ORDER BY, BETWEEN, query optimization
- [Caching Performance 2026](https://www.dragonflydb.io/guides/ultimate-guide-to-caching) — Rationale for on-demand vs. cached ICS (validates CONTEXT D-12 decision)

## Metadata

**Confidence breakdown:**
- Standard stack (DateTime, PDO, Bootstrap): **HIGH** — All built-in or widely standard; verified via official docs
- Architecture patterns (calendar queries, ICS generation): **HIGH** — RFC 5545 is authoritative; DateTime behavior is spec'd; SQL patterns verified
- Pitfalls (line endings, NULL handling, timezone): **HIGH** — Root causes confirmed via official docs and ecosystem reports
- Common implementation errors: **MEDIUM-HIGH** — WebSearch verified with multiple sources; practical experience aligns with findings

**Research date:** 2026-07-14
**Valid until:** 2026-08-14 (30 days; stable technologies; any changes would come from RFC errata or PostgreSQL major version shift, both rare)

**Gaps identified:**
- No testing framework specified in project (config.json: `nyquist_validation: false`), so no test patterns provided
- Deployment impact of ICS endpoint on Hetzner shared hosting unknown (likely negligible; simple route, no stateful operations)
- Exact Wochentags-Gruppierung HTML structure left to planner (day header format, card styling, etc.)
