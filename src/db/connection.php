<?php
// src/db/connection.php — PDO connection factory

require_once dirname(__DIR__, 2) . '/config.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        DB_HOST, DB_PORT, DB_NAME
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,  // CRITICAL for PostgreSQL — never set to true
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Isolate this app's tables from other apps sharing the same database.
    // DB_SCHEMA defaults to 'team_manager'; override via DB_SCHEMA env var.
    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA); // sanitize identifier
    $pdo->exec("SET search_path TO {$schema}, public");

    maybe_init_db($pdo);

    return $pdo;
}

/**
 * Initialize database schema on first boot.
 * Runs schema.sql and rls_policies.sql if the teams table does not exist yet.
 * Safe to call on every request — exits immediately if tables already exist.
 */
function maybe_init_db(PDO $pdo): void {
    $schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA);
    $exists = $pdo->query("SELECT to_regclass('{$schema}.teams')")->fetchColumn();
    if ($exists !== null) return;

    // Schema creation may fail on shared hosting where the DB user lacks CREATE privilege.
    // Attempt it separately so a permission error here doesn't abort table creation.
    try {
        $pdo->exec("CREATE SCHEMA IF NOT EXISTS {$schema}");
    } catch (PDOException $e) {
        error_log('team-manager: schema creation skipped (' . $e->getMessage() . ')');
    }

    $schema_sql = file_get_contents(ROOT_PATH . '/database/schema.sql');
    $rls_sql    = file_get_contents(ROOT_PATH . '/database/rls_policies.sql');

    if ($schema_sql === false || $rls_sql === false) {
        error_log('team-manager: cannot read SQL files from ' . ROOT_PATH . '/database/');
        throw new RuntimeException('Database SQL files not found. Check ROOT_PATH configuration.');
    }

    // Strip the CREATE SCHEMA line — already handled above
    $schema_sql = preg_replace('/^\s*CREATE SCHEMA\b.*$/mi', '', $schema_sql);

    $pdo->exec($schema_sql);
    $pdo->exec($rls_sql);
}

/**
 * Set PostgreSQL session context for RLS team isolation.
 * Must be called on every request after the team_id is known (coach/player sessions).
 *
 * @param int         $team_id  The team the current user belongs to.
 * @param string|null $role     'coach' or 'player' — required for Phase 3 visibility RLS.
 * @param int|null    $user_id  The current user's id — required for player cell ownership RLS.
 */
function set_team_context(PDO $pdo, int $team_id, ?string $role = null, ?int $user_id = null): void {
    $pdo->exec(
        "SELECT set_config('app.current_team_id', " . $pdo->quote((string)$team_id) . ", false)" .
        ", set_config('app.current_role', " . $pdo->quote((string)($role ?? '')) . ", false)" .
        ", set_config('app.current_user_id', " . $pdo->quote((string)($user_id ?? '')) . ", false)"
    );
}

/**
 * Grant admin bypass for RLS policies on this connection.
 * Called automatically by require_admin() — do not call manually.
 */
function set_admin_context(PDO $pdo): void {
    $pdo->exec("SELECT set_config('app.is_admin', 'true', false)");
}

/**
 * Reset all RLS context GUCs to empty state.
 * Must be called after any temporary admin bypass (e.g. login lookup) and at the
 * start of every coach/player request — PHP-FPM reuses connections across requests,
 * so a prior request's GUC values would otherwise leak.
 */
function reset_rls_context(PDO $pdo): void {
    $pdo->exec(
        "SELECT set_config('app.is_admin', '', false)" .
        ", set_config('app.current_team_id', '', false)" .
        ", set_config('app.current_role', '', false)" .
        ", set_config('app.current_user_id', '', false)"
    );
}
