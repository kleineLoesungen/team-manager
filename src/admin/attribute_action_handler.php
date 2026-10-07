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
    $data_type  = in_array($_POST['data_type'] ?? '', ['text', 'date']) ? $_POST['data_type'] : 'text';
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Attributname erforderlich (max. 100 Zeichen).'));
    }
    $pdo->prepare(
        "INSERT INTO member_attributes (group_id, name, data_type, visible_to_player, editable_by_player, sort_order)
         VALUES (?, ?, ?, ?, ?, ?)"
    )->execute([$group_id, $name, $data_type, $visible ? 'true' : 'false', $editable ? 'true' : 'false', $sort_order]);
    redirect('/admin/attributes');

} elseif ($action === 'edit') {
    if ($attr_id <= 0) redirect('/admin/attributes');
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $visible    = !empty($_POST['visible_to_player']);
    $editable   = !empty($_POST['editable_by_player']);
    $data_type  = in_array($_POST['data_type'] ?? '', ['text', 'date']) ? $_POST['data_type'] : 'text';
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Attributname erforderlich.'));
    }
    $pdo->prepare(
        "UPDATE member_attributes SET name=?, data_type=?, visible_to_player=?, editable_by_player=?, sort_order=?
         WHERE id=?"
    )->execute([$name, $data_type, $visible ? 'true' : 'false', $editable ? 'true' : 'false', $sort_order, $attr_id]);
    redirect('/admin/attributes');

} elseif ($action === 'delete') {
    // Zwei Schritte: Bestätigungsseite mit den Folgen, dann (confirm=1) löschen.
    // ON DELETE CASCADE entfernt die gespeicherten Werte dieses Attributs.
    $stmt = $pdo->prepare(
        "SELECT a.name, g.name AS group_name,
                (SELECT COUNT(*) FROM member_attribute_values v WHERE v.attribute_id = a.id AND v.value <> '') AS values_count
         FROM member_attributes a JOIN member_attribute_groups g ON g.id = a.group_id
         WHERE a.id = ? AND a.group_id = ?"
    );
    $stmt->execute([$attr_id, $group_id]);
    $attr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$attr) redirect('/admin/attributes');

    if ((int)($_POST['confirm'] ?? 0) !== 1) {
        require ROOT_PATH . '/src/templates/admin/layout.php';
        render_admin_page('Attribut löschen', 'settings', function () use ($attr, $attr_id, $group_id) {
            $n = (int)$attr['values_count'];
            render_delete_confirmation(
                'Attribut löschen?',
                'Attribut „' . $attr['name'] . '“ (Gruppe „' . $attr['group_name'] . '“) wirklich löschen?',
                $n ? [$n . ' gespeicherte' . ($n === 1 ? 'r Wert' : ' Werte') . ' bei Mitgliedern'] : [],
                '/admin/attributes/' . $group_id . '/attributes/' . $attr_id . '/delete',
                'Attribut endgültig löschen',
                '/admin/attributes'
            );
        });
        return;
    }
    $pdo->prepare("DELETE FROM member_attributes WHERE id=?")->execute([$attr_id]);
    redirect('/admin/attributes?success=' . urlencode('Attribut „' . $attr['name'] . '“ gelöscht.'));

} else {
    redirect('/admin/attributes');
}
