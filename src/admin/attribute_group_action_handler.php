<?php
// src/admin/attribute_group_action_handler.php — POST: create, edit, delete a player attribute group
// $_REQUEST['action'] and optionally $_REQUEST['group_id'] set by router

declare(strict_types=1);

require_admin();
require_once ROOT_PATH . '/src/db/departments.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/attributes');
}

require_csrf();

$action   = $_REQUEST['action'] ?? '';
$group_id = (int)($_REQUEST['group_id'] ?? 0);
$pdo      = get_db();

// Abteilung der Gruppe: leer = alle Abteilungen; sonst eine aktive (oder die bisherige)
$department_from_post = function (?int $current = null) use ($pdo): ?int {
    $id = (int)($_POST['department_id'] ?? 0);
    if ($id === 0) return null;
    $valid = array_map('intval', array_column(departments_list($pdo, true), 'id'));
    if ($current !== null) $valid[] = $current;
    return in_array($id, $valid, true) ? $id : null;
};

if ($action === 'create') {
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Gruppenname erforderlich (max. 100 Zeichen).'));
    }
    $pdo->prepare("INSERT INTO member_attribute_groups (name, sort_order, department_id) VALUES (?, ?, ?)")
        ->execute([$name, $sort_order, $department_from_post()]);
    redirect('/admin/attributes');

} elseif ($action === 'edit') {
    if ($group_id <= 0) redirect('/admin/attributes');
    $name       = trim($_POST['name'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    if (empty($name) || mb_strlen($name) > 100) {
        redirect('/admin/attributes?error=' . urlencode('Gruppenname erforderlich.'));
    }
    $cur = $pdo->prepare("SELECT department_id FROM member_attribute_groups WHERE id = ?");
    $cur->execute([$group_id]);
    $current = $cur->fetchColumn();
    $pdo->prepare("UPDATE member_attribute_groups SET name=?, sort_order=?, department_id=? WHERE id=?")
        ->execute([$name, $sort_order, $department_from_post($current ? (int)$current : null), $group_id]);
    redirect('/admin/attributes');

} elseif ($action === 'delete') {
    // Zwei Schritte: Bestätigungsseite mit den Folgen, dann (confirm=1) löschen.
    // ON DELETE CASCADE entfernt die Attribute der Gruppe und alle gespeicherten Werte.
    $stmt = $pdo->prepare(
        "SELECT g.name,
                (SELECT COUNT(*) FROM member_attributes a WHERE a.group_id = g.id) AS attributes,
                (SELECT COUNT(*) FROM member_attribute_values v JOIN member_attributes a ON a.id = v.attribute_id
                 WHERE a.group_id = g.id AND v.value <> '') AS values_count,
                (SELECT COUNT(DISTINCT v.member_id) FROM member_attribute_values v JOIN member_attributes a ON a.id = v.attribute_id
                 WHERE a.group_id = g.id AND v.value <> '') AS members
         FROM member_attribute_groups g WHERE g.id = ?"
    );
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) redirect('/admin/attributes');

    if ((int)($_POST['confirm'] ?? 0) !== 1) {
        require ROOT_PATH . '/src/templates/admin/layout.php';
        render_admin_page('Gruppe löschen', 'settings', function () use ($group, $group_id) {
            render_delete_confirmation(
                'Gruppe löschen?',
                'Attributgruppe „' . $group['name'] . '“ wirklich löschen?',
                array_values(array_filter([
                    (int)$group['attributes'] ? (int)$group['attributes'] . ' Attribut' . ((int)$group['attributes'] === 1 ? '' : 'e') . ' dieser Gruppe' : null,
                    (int)$group['values_count'] ? (int)$group['values_count'] . ((int)$group['values_count'] === 1 ? ' gespeicherter Wert' : ' gespeicherte Werte')
                        . ' von ' . (int)$group['members'] . ' Mitglied' . ((int)$group['members'] === 1 ? '' : 'ern') : null,
                ])),
                '/admin/attributes/groups/' . $group_id . '/delete',
                'Gruppe endgültig löschen',
                '/admin/attributes'
            );
        });
        return;
    }
    $pdo->prepare("DELETE FROM member_attribute_groups WHERE id=?")->execute([$group_id]);
    redirect('/admin/attributes?success=' . urlencode('Gruppe „' . $group['name'] . '“ gelöscht.'));

} else {
    redirect('/admin/attributes');
}
