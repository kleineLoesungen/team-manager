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

    return $pdo;
}

/**
 * Set PostgreSQL session context for RLS team isolation.
 * Must be called on every request after the team_id is known.
 * Admin requests: do NOT call this (admin bypasses RLS at app layer).
 */
function set_team_context(PDO $pdo, int $team_id): void {
    $stmt = $pdo->prepare("SELECT set_config('app.current_team_id', ?, false)");
    $stmt->execute([(string)$team_id]);
}
