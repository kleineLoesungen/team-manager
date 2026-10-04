<?php
// src/coach/list_create_handler.php — GET/POST /coordinator/lists/create (LIST-01, LIST-04)

declare(strict_types=1);

require_coordinator();

$pdo   = get_db();
$error = '';

// System columns need admin context to bypass RLS (team_id = NULL not visible to coordinator)
set_admin_context($pdo);
$sys_cols_stmt = $pdo->prepare(
    "SELECT id, name, data_type FROM columns
     WHERE is_system = TRUE AND list_id IS NULL
     ORDER BY sort_order ASC, name ASC"
);
$sys_cols_stmt->execute();
$system_columns = $sys_cols_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active global columns for this team (checkbox display + default value inputs)
$cols_stmt = $pdo->prepare(
    "SELECT id, name, data_type FROM columns
     WHERE team_id = ? AND list_id IS NULL AND is_active = TRUE
     ORDER BY sort_order, created_at"
);
$cols_stmt->execute([$_SESSION['team_id']]);
$global_columns = $cols_stmt->fetchAll(PDO::FETCH_ASSOC);

require ROOT_PATH . '/src/templates/coordinator/layout.php';

$list_type = in_array($_GET['type'] ?? '', ['member', 'free']) ? $_GET['type'] : 'member';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name          = trim($_POST['name'] ?? '');
    $visibility    = $_POST['visibility'] ?? 'public';
    $show_all_rows = isset($_POST['show_all_rows']) ? 1 : 0;
    $list_type     = in_array($_POST['list_type'] ?? 'member', ['member', 'free']) ? $_POST['list_type'] : 'member';
    $selected_cols = array_map('intval', (array)($_POST['global_columns'] ?? []));
    $defaults      = (array)($_POST['defaults'] ?? []);  // [col_id => raw_value]
    $date        = trim($_POST['date'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = '';
    }
    $location = trim($_POST['location'] ?? '');
    if (mb_strlen($location) > 255) {
        $location = mb_substr($location, 0, 255);
    }

    $time_start = '';
    $time_end   = '';
    $raw_ts = trim($_POST['time_start'] ?? '');
    if (preg_match('/^\d{2}:\d{2}$/', $raw_ts)) {
        $time_start = $raw_ts . ':00';   // Store as HH:MM:SS for PostgreSQL TIME type
    }
    $raw_te = trim($_POST['time_end'] ?? '');
    if (preg_match('/^\d{2}:\d{2}$/', $raw_te)) {
        $time_end = $raw_te . ':00';
    }

    // Serie: so viele eigenständige Listen wie Termine (danach einzeln bearbeitbar)
    $repeat       = array_key_exists($_POST['repeat'] ?? '', LIST_SERIES_REPEATS) ? $_POST['repeat'] : '';
    $repeat_count = filter_var($_POST['repeat_count'] ?? '', FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 2, 'max_range' => LIST_SERIES_MAX]]);

    if (empty($name)) {
        $error = 'Name ist erforderlich.';
    } elseif (!in_array($visibility, ['public', 'protected', 'private'])) {
        $error = 'Ungültiger Sichtbarkeits-Status.';
    } elseif ($repeat !== '' && $list_type !== 'member') {
        $error = 'Serien gibt es nur für Mitgliederlisten.';
    } elseif ($repeat !== '' && $date === '') {
        $error = 'Für eine Serie braucht die Liste ein Datum — es ist der erste Termin.';
    } elseif ($repeat !== '' && $repeat_count === false) {
        $error = 'Anzahl Termine: Gib eine Zahl von 2 bis ' . LIST_SERIES_MAX . ' ein.';
    } else {
        $dates = $repeat !== '' ? list_series_dates($date, $repeat, (int)$repeat_count) : [$date];
        try {
            $pdo->beginTransaction();
            foreach ($dates as $list_date) {
                $cols = "team_id, name, visibility, list_type, show_all_rows, date, description, location, time_start, time_end";
                $vals = "?, ?, ?, ?, ?, ?, ?, ?, ?, ?";
                $params = [
                    $_SESSION['team_id'], $name, $visibility, $list_type, $show_all_rows,
                    $list_date !== '' ? $list_date : null,
                    $description !== '' ? $description : null,
                    $location !== '' ? $location : null,
                    $time_start !== '' ? $time_start : null,
                    $time_end   !== '' ? $time_end   : null,
                ];

                $stmt = $pdo->prepare("INSERT INTO lists ({$cols}) VALUES ({$vals}) RETURNING id");
                $stmt->execute($params);
                $list_id = (int)$stmt->fetchColumn();

                // Link selected global columns (D-11) — validate ownership first
                // Free lists do not support global columns
                $valid_ids = [];
                if ($list_type === 'member' && !empty($selected_cols)) {
                    $placeholders = implode(',', array_fill(0, count($selected_cols), '?'));
                    // Accept both team global columns and system columns (is_system = TRUE)
                    // Admin context was set above so system columns are visible in this query
                    $valid_stmt = $pdo->prepare(
                        "SELECT id FROM columns
                         WHERE id IN ($placeholders) AND list_id IS NULL AND is_active = TRUE
                           AND (team_id = ? OR is_system = TRUE)"
                    );
                    $valid_stmt->execute([...$selected_cols, $_SESSION['team_id']]);
                    $valid_ids = $valid_stmt->fetchAll(PDO::FETCH_COLUMN);

                    // Typen für die Standardwerte (Team- und Systemspalten)
                    $type_map = [];
                    foreach (array_merge($system_columns, $global_columns) as $gc) {
                        $type_map[(int)$gc['id']] = $gc['data_type'];
                    }
                    // Standardwert je Spalte gilt auch für später hinzukommende Mitglieder
                    // (prefill_member_cells), deshalb wird er an der Verknüpfung gespeichert.
                    $link_stmt = $pdo->prepare(
                        "INSERT INTO list_global_columns (list_id, column_id, default_value) VALUES (?, ?, ?)"
                    );
                    foreach ($valid_ids as $col_id) {
                        $col_id = (int)$col_id;
                        $link_stmt->execute([$list_id, $col_id, list_default_value($type_map[$col_id] ?? null, $defaults, $col_id)]);
                    }
                }

                // Pre-populate default cell values for all active players on this team
                if (!empty($valid_ids)) {

                    // Fetch all active players
                    $players_stmt = $pdo->prepare(
                        "SELECT id FROM users WHERE team_id = ? AND role = 'member' AND is_active = TRUE"
                    );
                    $players_stmt->execute([$_SESSION['team_id']]);
                    $player_ids = $players_stmt->fetchAll(PDO::FETCH_COLUMN);

                    $cell_stmt = $pdo->prepare(
                        "INSERT INTO cells (list_id, column_id, member_id, value)
                         VALUES (?, ?, ?, ?)
                         ON CONFLICT (list_id, column_id, member_id) DO NOTHING"
                    );

                    foreach ($valid_ids as $col_id) {
                        $col_id = (int)$col_id;
                        $value  = list_default_value($type_map[$col_id] ?? null, $defaults, $col_id);

                        if ($value === null) {
                            continue;
                        }

                        foreach ($player_ids as $pid) {
                            $cell_stmt->execute([$list_id, $col_id, (int)$pid, $value]);
                        }
                    }
                }
            }

            $pdo->commit();
            if (count($dates) > 1) {
                redirect('/coordinator/lists?view=list&success=series&count=' . count($dates));
            }
            redirect('/coordinator/lists/' . $list_id . '?success=1');

        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('List create error: ' . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }
}

$page_title = ($list_type === 'free') ? 'Neue freie Liste' : 'Neue Mitgliederliste';
render_coach_page($page_title, 'lists', function() use ($error, $global_columns, $system_columns, $list_type) {
    require ROOT_PATH . '/src/templates/coordinator/list_form.php';
});
