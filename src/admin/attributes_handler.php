<?php
// src/admin/attributes_handler.php — GET: list all player attribute groups with nested attributes

declare(strict_types=1);

require_admin();
$pdo = get_db();

// Load all groups with their attributes, ordered by group sort_order then attribute sort_order
$stmt = $pdo->query(
    "SELECT pag.id AS group_id, pag.name AS group_name, pag.sort_order AS group_sort,
            pa.id AS attr_id, pa.name AS attr_name, pa.sort_order AS attr_sort,
            pa.visible_to_player, pa.editable_by_player
     FROM player_attribute_groups pag
     LEFT JOIN player_attributes pa ON pa.group_id = pag.id
     ORDER BY pag.sort_order ASC, pag.name ASC, pa.sort_order ASC, pa.name ASC"
);
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
        ];
    }
}

$error = !empty($_GET['error']) ? e($_GET['error']) : '';

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Spieler-Attribute', 'attributes', function() use ($groups, $error) {
    require ROOT_PATH . '/src/templates/admin/attributes.php';
});
