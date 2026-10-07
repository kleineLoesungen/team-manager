<?php
// src/member/profile_handler.php — GET+POST /member/profile
// Full player data edit page (name, email, phone, contact, description, organization).
// After first-login GDPR confirmation, this is the ongoing edit entry point.

declare(strict_types=1);

require_member();
require_once ROOT_PATH . '/src/db/departments.php';

$pdo     = get_db();
$user_id = (int)$_SESSION['user_id'];
$error   = '';

// Load linked member_id (profile record)
$link_stmt = $pdo->prepare("SELECT member_id FROM users WHERE id = ?");
$link_stmt->execute([$user_id]);
$player_id = (int)($link_stmt->fetchColumn() ?: 0);

$player      = null;
$organizations       = [];
$attr_groups = [];

if ($player_id) {
    set_admin_context($pdo);
    $p_stmt = $pdo->prepare(
        "SELECT p.*, c.name AS organization_name
         FROM members p LEFT JOIN organizations c ON c.id = p.organization_id
         WHERE p.id = ?"
    );
    $p_stmt->execute([$player_id]);
    $player = $p_stmt->fetch();

    $organizations = $pdo->query("SELECT id, name FROM organizations WHERE is_active = TRUE ORDER BY name")->fetchAll();

    // Load visible member attributes
    $attr_stmt = $pdo->prepare(
        "SELECT pag.name AS group_name, pag.sort_order AS group_order,
                pa.id AS attr_id, pa.name AS attr_name, pa.data_type, pa.sort_order AS attr_order,
                pa.editable_by_player,
                COALESCE(pav.value, '') AS value
         FROM member_attribute_groups pag
         JOIN member_attributes pa ON pa.group_id = pag.id
         LEFT JOIN member_attribute_values pav ON pav.attribute_id = pa.id AND pav.member_id = ?
         WHERE pa.visible_to_player = TRUE AND " . attribute_groups_scope_sql('pag') . "
         ORDER BY pag.sort_order ASC, pag.name ASC, pa.sort_order ASC, pa.name ASC"
    );
    // allgemeine Gruppen + Gruppen der Abteilungen aller eigenen Teams
    $attr_stmt->execute([$player_id, departments_param(departments_of_member($pdo, (int)$player_id))]);
    foreach ($attr_stmt->fetchAll() as $row) {
        $g = $row['group_name'];
        if (!isset($attr_groups[$g])) {
            $attr_groups[$g] = ['group_order' => $row['group_order'], 'attrs' => []];
        }
        $attr_groups[$g]['attrs'][] = $row;
    }

    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], 'member', $user_id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if ($player_id) {
        $first_name   = trim($_POST['first_name']   ?? '');
        $last_name    = trim($_POST['last_name']    ?? '');
        $email_raw    = trim($_POST['email']        ?? '');
        $phone        = trim($_POST['phone']        ?? '');
        $contact_name  = trim($_POST['contact_name']  ?? '');
        $contact_phone = trim($_POST['contact_phone'] ?? '');
        $contact_email = trim($_POST['contact_email'] ?? '');
        $description   = trim($_POST['description']   ?? '');
        $organization_id       = (int)($_POST['organization_id']      ?? 0);

        if (empty($first_name) || empty($last_name)) {
            $error = 'Vor- und Nachname sind erforderlich.';
        } elseif ($email_raw !== '' && !filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige E-Mail-Adresse.';
        } elseif ($contact_email !== '' && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ungültige Kontakt-E-Mail-Adresse.';
        } else {
            set_admin_context($pdo);
            $pdo->prepare(
                "UPDATE members SET first_name=?, last_name=?, email=?, phone=?,
                  contact_name=?, contact_phone=?, contact_email=?, description=?, organization_id=? WHERE id=?"
            )->execute([
                $first_name, $last_name,
                $email_raw !== '' ? $email_raw : null,
                $phone !== '' ? $phone : null,
                $contact_name !== '' ? $contact_name : null,
                $contact_phone !== '' ? $contact_phone : null,
                $contact_email !== '' ? $contact_email : null,
                $description !== '' ? $description : null,
                $organization_id > 0 ? $organization_id : null,
                $player_id,
            ]);
            reset_rls_context($pdo);
            set_team_context($pdo, (int)$_SESSION['team_id'], 'member', $user_id);

            redirect('/member/profile?success=1');
        }

        if ($error) {
            // Re-populate from submitted values for re-display
            $player = array_merge($player ?? [], [
                'first_name'   => $_POST['first_name']   ?? '',
                'last_name'    => $_POST['last_name']    ?? '',
                'email'        => $_POST['email']        ?? '',
                'phone'        => $_POST['phone']        ?? '',
                'contact_name'  => $_POST['contact_name']  ?? '',
                'contact_phone' => $_POST['contact_phone'] ?? '',
                'contact_email' => $_POST['contact_email'] ?? '',
                'description'  => $_POST['description']  ?? '',
                'organization_id'      => (int)($_POST['organization_id'] ?? 0),
            ]);
        }
    }
}

$success = !empty($_GET['success']);

require ROOT_PATH . '/src/templates/member/layout.php';

render_member_page('Mein Profil', 'profile', function() use ($player, $player_id, $organizations, $attr_groups, $error, $success) {
    require ROOT_PATH . '/src/templates/member/profile.php';
});
