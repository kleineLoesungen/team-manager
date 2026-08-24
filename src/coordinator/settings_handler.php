<?php
// src/coordinator/settings_handler.php — GET/POST /coordinator/settings
// D-09: team-wide ticker tag config; D-10: Spalten renamed to Einstellungen

declare(strict_types=1);

require_coordinator();

$pdo = get_db();

$delete_pending_col_id = null;

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
    } elseif ($action === 'delete_tag') {
        $tag_id = (int)($_POST['tag_id'] ?? 0);
        if ($tag_id > 0) {
            $stmt = $pdo->prepare(
                "DELETE FROM ticker_tags WHERE id = ? AND team_id = ?"
            );
            $stmt->execute([$tag_id, $_SESSION['team_id']]);
        }
        redirect('/coordinator/settings?success=tag_deleted');
    } elseif ($action === 'delete_column') {
        $col_id  = (int)($_POST['column_id'] ?? 0);
        $confirm = (int)($_POST['confirm']   ?? 0);
        if ($confirm === 1 && $col_id > 0) {
            $del = $pdo->prepare(
                "DELETE FROM columns WHERE id = ? AND team_id = ? AND list_id IS NULL AND is_system = FALSE"
            );
            $del->execute([$col_id, $_SESSION['team_id']]);
            redirect('/coordinator/settings?success=column_deleted');
        } else {
            $delete_pending_col_id = $col_id; // fall through to render with confirmation UI
        }
    } else {
        redirect('/coordinator/settings');
    }
}

// ── GET: fetch data ───────────────────────────────────────────────────────────

// System columns (cross-team, admin-managed, read-only for coordinators)
// Requires admin context — system columns have team_id = NULL, invisible under coordinator RLS
set_admin_context($pdo);
$sys_stmt = $pdo->query(
    "SELECT id, name, data_type, sort_order, created_at
     FROM columns
     WHERE is_system = TRUE AND list_id IS NULL
     ORDER BY sort_order ASC, name ASC"
);
$system_columns = $sys_stmt->fetchAll(PDO::FETCH_ASSOC);

// Team-scoped global columns (coordinator-managed)
$stmt = $pdo->prepare(
    "SELECT id, name, data_type, is_active, created_at
     FROM columns
     WHERE team_id = ? AND list_id IS NULL AND is_system = FALSE
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
    'tag_created'    => 'Tag angelegt.',
    'tag_deleted'    => 'Tag gelöscht.',
    'column_deleted' => 'Spalte gelöscht.',
    default          => '',
} : '';

require ROOT_PATH . '/src/templates/coordinator/layout.php';

render_coach_page('Einstellungen', 'settings', function() use ($columns, $system_columns, $ticker_tags, $error, $success, $delete_pending_col_id) {
    if ($error)   echo '<div class="alert alert-danger">'  . e($error)   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . e($success) . '</div>';
    require ROOT_PATH . '/src/templates/coordinator/settings.php';
});
