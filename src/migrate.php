<?php
// src/migrate.php — POST /migrate — spielt fehlende Migrationen ein (database/migrations/*.sql)
//
// Aufgerufen nach jedem Deployment: von GitHub Actions (.github/workflows/deploy.yml) und von
// deploy.sh. Geschützt durch MIGRATE_TOKEN aus den Einstellungen der Instanz, übergeben im
// Header X-Migrate-Token. Ohne MIGRATE_TOKEN ist der Endpunkt aus (404).
//
// Welche Migrationen fehlen, weiß die Datenbank selbst: Jede Migration schreibt am Ende ihren
// Namen in settings 'db_migration' (src/utils/updates.php, db_pending_migrations()). Die
// Dateien laufen in Dateinamen-Reihenfolge, jede in ihrer eigenen Transaktion (BEGIN … COMMIT
// steht in der Datei); der Platzhalter SCHEMA_EINTRAGEN wird durch DB_SCHEMA ersetzt.
// Die erste fehlgeschlagene Migration bricht ab (Antwort 500), spätere laufen dann nicht.
//
// Zugang: MIGRATE_DB_USER / MIGRATE_DB_PASS, wenn gesetzt (Eigentümer der Tabellen), sonst der
// Benutzer der App (DB_USER). Der App-Benutzer sollte möglichst KEIN Eigentümer sein — dann kann
// eine Lücke in der App keine Tabellen ändern; Migrationen brauchen aber Eigentümer-Rechte.
// Bei einer leeren Datenbank legt die App das Schema beim ersten Aufruf an.

declare(strict_types=1);

require_once ROOT_PATH . '/src/utils/updates.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$token = defined('MIGRATE_TOKEN') ? (string)MIGRATE_TOKEN : '';
if ($token === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'MIGRATE_TOKEN ist nicht gesetzt']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || strlen($token) < 32
    || !hash_equals($token, (string)($_SERVER['HTTP_X_MIGRATE_TOKEN'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Kein Zugriff']);
    exit;
}

set_time_limit(600);
get_db();   // legt bei einer leeren Datenbank das Schema an (connection.php)
$schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA);
$pdo    = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME),
    MIGRATE_DB_USER !== '' ? MIGRATE_DB_USER : DB_USER,
    MIGRATE_DB_USER !== '' ? MIGRATE_DB_PASS : DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec("SET search_path TO {$schema}, public");
$done   = [];

try {
    foreach (as_admin($pdo, fn() => db_pending_migrations($pdo)) as $name) {
        $file = ROOT_PATH . '/database/migrations/' . $name . '.sql';
        $sql  = str_replace('SCHEMA_EINTRAGEN', $schema, (string)file_get_contents($file));
        $pdo->exec($sql);
        // Die Migration muss sich selbst eingetragen haben — sonst liefe sie beim nächsten Mal wieder
        if (in_array($name, as_admin($pdo, fn() => db_pending_migrations($pdo)), true)) {
            throw new RuntimeException("$name hat settings.db_migration nicht gesetzt");
        }
        $done[] = $name;
    }
    $result = ['ok' => true, 'ausgefuehrt' => $done, 'stand' => as_admin($pdo, fn() => db_migration_state($pdo)) ?: null];
} catch (Throwable $e) {
    try { $pdo->exec('ROLLBACK'); } catch (Throwable) {}   // falls die Datei mitten in BEGIN … COMMIT abbrach
    error_log('migrate: ' . $e->getMessage());
    http_response_code(500);
    $result = ['ok' => false, 'error' => 'Migration fehlgeschlagen', 'bei' => $name ?? null,
               'bereits_ausgefuehrt' => $done];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
exit;
