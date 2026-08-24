<?php
// src/member/profile_attributes_handler.php — POST /member/profile/attributes/save
// Saves editable_by_player attribute values for the member's own player record.

declare(strict_types=1);

require_member();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/member/profile');
require_csrf();

$pdo     = get_db();
$user_id = (int)$_SESSION['user_id'];

// Resolve the member's linked profile record — do not trust POST input
set_admin_context($pdo);
$link_stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$link_stmt->execute([$user_id]);
$member_profile_id = (int)($link_stmt->fetchColumn() ?: 0);

if ($member_profile_id <= 0) redirect('/member/profile');

// Only save attributes that are both visible_to_player AND editable_by_player
$allowed_stmt = $pdo->prepare(
    "SELECT id FROM member_attributes WHERE visible_to_player = TRUE AND editable_by_player = TRUE"
);
$allowed_stmt->execute();
$allowed_ids = array_flip(array_column($allowed_stmt->fetchAll(), 'id'));

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
    $upsert->execute([$member_profile_id, $attr_id, (string)$value]);
}

reset_rls_context($pdo);
set_team_context($pdo, (int)$_SESSION['team_id'], 'member', $user_id);

redirect('/member/profile?success=1');
