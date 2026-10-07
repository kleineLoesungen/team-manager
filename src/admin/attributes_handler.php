<?php
// src/admin/attributes_handler.php — GET: list all player attribute groups with nested attributes

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/db/departments.php';
$pdo = get_db();

// Abteilungen: Filter (?department= zeigt deren Gruppen und die allgemeinen) und Auswahl je Gruppe
$departments = departments_list($pdo);
$department  = department_filter($departments);

// Load all groups with their attributes, ordered by group sort_order then attribute sort_order
$stmt = $pdo->prepare(
    "SELECT pag.id AS group_id, pag.name AS group_name, pag.sort_order AS group_sort,
            pag.department_id, d.name AS department_name,
            pa.id AS attr_id, pa.name AS attr_name, pa.sort_order AS attr_sort,
            pa.visible_to_player, pa.editable_by_player, pa.data_type
     FROM member_attribute_groups pag
     LEFT JOIN departments d ON d.id = pag.department_id
     LEFT JOIN member_attributes pa ON pa.group_id = pag.id
     WHERE (CAST(? AS int) IS NULL OR pag.department_id IS NULL OR pag.department_id = ?)
     ORDER BY pag.sort_order ASC, pag.name ASC, pa.sort_order ASC, pa.name ASC"
);
$stmt->execute([$department, $department]);
$rows = $stmt->fetchAll();

// Group rows by group_id for template rendering
$groups = [];
foreach ($rows as $row) {
    $gid = $row['group_id'];
    if (!isset($groups[$gid])) {
        $groups[$gid] = [
            'id'         => $gid,
            'name'       => $row['group_name'],
            'sort_order' => $row['group_sort'],
            'department_id'   => $row['department_id'] !== null ? (int)$row['department_id'] : null,
            'department_name' => $row['department_name'],
            'attributes' => [],
        ];
    }
    if ($row['attr_id'] !== null) {
        $groups[$gid]['attributes'][] = [
            'id'                 => $row['attr_id'],
            'name'               => $row['attr_name'],
            'sort_order'         => $row['attr_sort'],
            'visible_to_player'  => $row['visible_to_player'],
            'editable_by_player' => $row['editable_by_player'],
            'data_type'          => $row['data_type'] ?? 'text',
        ];
    }
}

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Mitglieds-Attribute', 'attributes', function() use ($groups, $error, $departments, $department) {
    require ROOT_PATH . '/src/templates/admin/attributes.php';
});
