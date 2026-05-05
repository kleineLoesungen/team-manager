<?php
// src/coordinator/columns_create_handler.php — POST /coordinator/columns/create (LIST-02)
// Creates a GLOBAL column (list_id IS NULL). Text type NOT allowed for global columns.
// Pitfall 5 (RESEARCH.md): text type must be rejected here — global columns feed statistics aggregation.

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/coordinator/columns');
}

require_csrf();

$pdo       = get_db();
$name      = trim($_POST['name'] ?? '');
$data_type = $_POST['data_type'] ?? '';

// Global columns only allow boolean or number (NOT text — protects Phase 4 statistics)
if (empty($name)) {
    redirect('/coordinator/columns?error=' . urlencode('Name ist erforderlich.'));
}
if (!in_array($data_type, ['boolean', 'number'])) {
    redirect('/coordinator/columns?error=' . urlencode('Ungültiger Typ. Globale Spalten erlauben nur Ja/Nein oder Zahl.'));
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO columns (team_id, list_id, name, data_type)
         VALUES (?, NULL, ?, ?)"
    );
    $stmt->execute([$_SESSION['team_id'], $name, $data_type]);
    redirect('/coordinator/columns?success=1');
} catch (PDOException $e) {
    error_log('Global column create error: ' . $e->getMessage());
    redirect('/coordinator/columns?error=' . urlencode('Ein Fehler ist aufgetreten.'));
}
