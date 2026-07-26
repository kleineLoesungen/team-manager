<?php
// src/coordinator/settings_handler.php — GET/POST /coordinator/settings
// D-09: team-wide ticker tag config; D-10: Spalten renamed to Einstellungen

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = trim($_POST['action'] ?? '');

    if ($action === 'create_tag') {
        $label = trim($_POST['label'] ?? '');
        $color = trim($_POST['color'] ?? 'secondary');
        $valid_colors = ['success', 'warning', 'danger', 'primary', 'secondary'];

        if ($label === '' || mb_strlen($label, 'UTF-8') > 50) {
            redirect('/coordinator/settings?error=' . urlencode('Tag-Name ist erforderlich (max. 50 Zeichen).'));
        }
        if (!in_array($color, $valid_colors, true)) {
            redirect('/coordinator/settings?error=' . urlencode('Ungültige Farbe.'));
        }

        $stmt = $pdo->prepare(
            "INSERT INTO ticker_tags (team_id, label, color, sort_order, created_at)
             VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ticker_tags WHERE team_id = ?), NOW())"
        );
        $stmt->execute([$_SESSION['team_id'], $label, $color, $_SESSION['team_id']]);
        redirect('/coordinator/settings?success=tag_created');
    }

    if ($action === 'delete_tag') {
        $tag_id = (int)($_POST['tag_id'] ?? 0);
        if ($tag_id > 0) {
            $stmt = $pdo->prepare(
                "DELETE FROM ticker_tags WHERE id = ? AND team_id = ?"
            );
            $stmt->execute([$tag_id, $_SESSION['team_id']]);
        }
        redirect('/coordinator/settings?success=tag_deleted');
    }

    redirect('/coordinator/settings');
}

// ── GET: fetch data ───────────────────────────────────────────────────────────

// Global columns (existing functionality, preserved from columns_handler.php)
$stmt = $pdo->prepare(
    "SELECT id, name, data_type, is_active, created_at
     FROM columns
     WHERE team_id = ? AND list_id IS NULL
     ORDER BY sort_order, created_at"
);
$stmt->execute([$_SESSION['team_id']]);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ticker tags (new in Phase 7)
$stmt = $pdo->prepare(
    "SELECT id, label, color, sort_order, created_at
     FROM ticker_tags
     WHERE team_id = ?
     ORDER BY sort_order, created_at"
);
$stmt->execute([$_SESSION['team_id']]);
$ticker_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? match($_GET['success']) {
    'tag_created' => 'Tag angelegt.',
    'tag_deleted' => 'Tag gelöscht.',
    default       => '',
} : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Einstellungen', 'columns', function() use ($columns, $ticker_tags, $error, $success) {
    if ($error)   echo '<div class="alert alert-danger">'  . e($error)   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/settings.php';
});
