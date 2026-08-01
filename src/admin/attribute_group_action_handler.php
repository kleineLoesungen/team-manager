<?php
// src/admin/attribute_group_action_handler.php — POST: create, edit, delete a player attribute group
// $_REQUEST['action'] and optionally $_REQUEST['group_id'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/attributes');
}

require_csrf();

$action   = $_REQUEST['action'] ?? '';
$group_id = (int)($_REQUEST['group_id'] ?? 0);
$pdo      = get_db();

if ($action === 'create') {
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Gruppenname erforderlich (max. 100 Zeichen).'));
    }
    $pdo->prepare("INSERT INTO player_attribute_groups (name, sort_order) VALUES (?, ?)")
        ->execute([$name, $sort_order]);
    redirect('/admin/attributes');

} elseif ($action === 'edit') {
    if ($group_id <= 0) redirect('/admin/attributes');
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Gruppenname erforderlich.'));
    }
    $pdo->prepare("UPDATE player_attribute_groups SET name=?, sort_order=? WHERE id=?")
        ->execute([$name, $sort_order, $group_id]);
    redirect('/admin/attributes');

} elseif ($action === 'delete') {
    if ($group_id <= 0) redirect('/admin/attributes');
    // ON DELETE CASCADE will remove child attributes and their values
    $pdo->prepare("DELETE FROM player_attribute_groups WHERE id=?")->execute([$group_id]);
    redirect('/admin/attributes');

} else {
    redirect('/admin/attributes');
}
