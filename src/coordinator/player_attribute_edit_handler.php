<?php
// src/coordinator/player_attribute_edit_handler.php — POST /coordinator/players/{id}/attributes/save

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/coordinator/players');
require_csrf();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
if ($player_id <= 0) redirect('/coordinator/players');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];

// Use admin context to verify the player exists — coordinator can edit attributes for any player.
set_admin_context($pdo);
$check = $pdo->prepare("SELECT id FROM players WHERE id = ?");
$check->execute([$player_id]);
if (!$check->fetch()) redirect('/coordinator/players');
reset_rls_context($pdo);
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

// Save all submitted attribute values (coordinator can set any attribute)
$values = $_POST['values'] ?? [];  // array: attribute_id => value string
$upsert = $pdo->prepare(
    "INSERT INTO player_attribute_values (player_id, attribute_id, value, updated_at)
     VALUES (?, ?, ?, NOW())
     ON CONFLICT (player_id, attribute_id)
     DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
);
foreach ($values as $attr_id_raw => $value) {
    $attr_id = (int)$attr_id_raw;
    if ($attr_id <= 0) continue;
    $upsert->execute([$player_id, $attr_id, (string)$value]);
}

redirect('/coordinator/players/' . $player_id);
