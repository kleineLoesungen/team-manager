<?php
// src/coordinator/member_profile_attribute_edit_handler.php — POST /coordinator/member-profiles/{id}/attributes/save

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/db/departments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/coordinator/member-profiles');
require_csrf();

$profile_id = (int)($_REQUEST['profile_id'] ?? 0);
if ($profile_id <= 0) redirect('/coordinator/member-profiles');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];

set_admin_context($pdo);
$check = $pdo->prepare("SELECT id FROM members WHERE id = ?");
$check->execute([$profile_id]);
if (!$check->fetch()) redirect('/coordinator/member-profiles');
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

// Nur Attribute aus Gruppen, die der Koordinator sieht (allgemein oder Abteilung des Teams)
$allowed_stmt = $pdo->prepare(
    "SELECT pa.id FROM member_attributes pa JOIN member_attribute_groups pag ON pag.id = pa.group_id
     WHERE " . attribute_groups_scope_sql('pag')
);
$allowed_stmt->execute([departments_param([team_department_id($pdo, $team_id)])]);
$allowed_ids = array_flip(array_map('intval', $allowed_stmt->fetchAll(PDO::FETCH_COLUMN)));

$values = $_POST['values'] ?? [];
$upsert = $pdo->prepare(
    "INSERT INTO member_attribute_values (member_id, attribute_id, value, updated_at)
     VALUES (?, ?, ?, NOW())
     ON CONFLICT (member_id, attribute_id)
     DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
);
foreach ($values as $attr_id_raw => $value) {
    $attr_id = (int)$attr_id_raw;
    if ($attr_id <= 0 || !isset($allowed_ids[$attr_id])) continue;
    $upsert->execute([$profile_id, $attr_id, (string)$value]);
}

redirect('/coordinator/member-profiles/' . $profile_id);
