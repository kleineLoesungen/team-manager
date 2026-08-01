<?php
// src/admin/attribute_action_handler.php — POST: create, edit, delete a player attribute
// $_REQUEST['action'], $_REQUEST['group_id'], and optionally $_REQUEST['attr_id'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/attributes');
}

require_csrf();

$action   = $_REQUEST['action'] ?? '';
$group_id = (int)($_REQUEST['group_id'] ?? 0);
$attr_id  = (int)($_REQUEST['attr_id'] ?? 0);
$pdo      = get_db();

if ($action === 'create') {
    if ($group_id <= 0) redirect('/admin/attributes');
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $visible    = !empty($_POST['visible_to_player']);
    $editable   = !empty($_POST['editable_by_player']);
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Attributname erforderlich (max. 100 Zeichen).'));
    }
    $pdo->prepare(
        "INSERT INTO player_attributes (group_id, name, visible_to_player, editable_by_player, sort_order)
         VALUES (?, ?, ?, ?, ?)"
    )->execute([$group_id, $name, $visible ? 'true' : 'false', $editable ? 'true' : 'false', $sort_order]);
    redirect('/admin/attributes');

} elseif ($action === 'edit') {
    if ($attr_id <= 0) redirect('/admin/attributes');
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $visible    = !empty($_POST['visible_to_player']);
    $editable   = !empty($_POST['editable_by_player']);
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Attributname erforderlich.'));
    }
    $pdo->prepare(
        "UPDATE player_attributes SET name=?, visible_to_player=?, editable_by_player=?, sort_order=?
         WHERE id=?"
    )->execute([$name, $visible ? 'true' : 'false', $editable ? 'true' : 'false', $sort_order, $attr_id]);
    redirect('/admin/attributes');

} elseif ($action === 'delete') {
    if ($attr_id <= 0) redirect('/admin/attributes');
    // ON DELETE CASCADE removes player_attribute_values for this attribute
    $pdo->prepare("DELETE FROM player_attributes WHERE id=?")->execute([$attr_id]);
    redirect('/admin/attributes');

} else {
    redirect('/admin/attributes');
}
