<?php
// src/admin/attribute_edit_handler.php — GET+POST /admin/attributes/{group_id}/attributes/{id}/edit

declare(strict_types=1);

require_admin();

$group_id = (int)($_REQUEST['group_id'] ?? 0);
$attr_id  = (int)($_REQUEST['attr_id']  ?? 0);
$pdo      = get_db();

if ($group_id <= 0 || $attr_id <= 0) redirect('/admin/attributes');

$stmt = $pdo->prepare(
    "SELECT ma.*, mag.name AS group_name
     FROM member_attributes ma
     JOIN member_attribute_groups mag ON mag.id = ma.group_id
     WHERE ma.id = ? AND ma.group_id = ?"
);
$stmt->execute([$attr_id, $group_id]);
$attr = $stmt->fetch();
if (!$attr) redirect('/admin/attributes');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM member_attributes WHERE id = ?")->execute([$attr_id]);
        redirect('/admin/attributes?success=1');
    }

    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $visible    = !empty($_POST['visible_to_player']);
    $editable   = !empty($_POST['editable_by_player']);
    $data_type  = in_array($_POST['data_type'] ?? '', ['text', 'date']) ? $_POST['data_type'] : 'text';

    if (empty($name) || mb_strlen($name) > 100) {
        $error = 'Attributname ist erforderlich (max. 100 Zeichen).';
    } else {
        $pdo->prepare(
            "UPDATE member_attributes
             SET name=?, data_type=?, visible_to_player=?, editable_by_player=?, sort_order=?
             WHERE id=?"
        )->execute([$name, $data_type, $visible ? 'true' : 'false', $editable ? 'true' : 'false', $sort_order, $attr_id]);
        redirect('/admin/attributes?success=1');
    }

    $attr = array_merge($attr, [
        'name'              => $_POST['name'] ?? '',
        'data_type'         => $data_type,
        'sort_order'        => $sort_order,
        'visible_to_player' => $visible,
        'editable_by_player'=> $editable,
    ]);
}

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Attribut bearbeiten', 'settings', function() use ($attr, $group_id, $error) {
    require ROOT_PATH . '/src/templates/admin/attribute_edit.php';
});
