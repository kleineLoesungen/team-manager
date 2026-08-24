<?php
// src/coordinator/member_profile_attribute_edit_handler.php — POST /coordinator/member-profiles/{id}/attributes/save

declare(strict_types=1);

require_coordinator();

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

$values = $_POST['values'] ?? [];
$upsert = $pdo->prepare(
    "INSERT INTO member_attribute_values (member_id, attribute_id, value, updated_at)
     VALUES (?, ?, ?, NOW())
     ON CONFLICT (member_id, attribute_id)
     DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
);
foreach ($values as $attr_id_raw => $value) {
    $attr_id = (int)$attr_id_raw;
    if ($attr_id <= 0) continue;
    $upsert->execute([$profile_id, $attr_id, (string)$value]);
}

redirect('/coordinator/member-profiles/' . $profile_id);
