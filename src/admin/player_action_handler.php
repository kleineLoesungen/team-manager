<?php
// src/admin/player_action_handler.php — POST: edit, assign-team, or link-user for a player
// $_REQUEST['player_id'] and $_REQUEST['action'] set by router

declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/players');
}

require_csrf();

$player_id = (int)($_REQUEST['player_id'] ?? 0);
$action    = $_REQUEST['action'] ?? '';

if ($player_id <= 0) {
    redirect('/admin/players');
}

$pdo = get_db();

// Verify player exists
$check = $pdo->prepare("SELECT id, first_name, last_name FROM players WHERE id = ?");
$check->execute([$player_id]);
$player = $check->fetch();

if (!$player) {
    redirect('/admin/players');
}

if ($action === 'edit') {
    $first_name   = trim($_POST['first_name'] ?? '');
    $last_name    = trim($_POST['last_name'] ?? '');
    $club_id      = (int)($_POST['club_id'] ?? 0);
    $phone        = trim($_POST['phone'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (empty($first_name) || empty($last_name) || $club_id <= 0) {
        redirect('/admin/players?error=' . urlencode('Vor- und Nachname sowie Klub sind erforderlich.'));
    }

    $stmt = $pdo->prepare(
        "UPDATE players SET club_id = ?, first_name = ?, last_name = ?,
                            phone = ?, contact_name = ?, description = ?
         WHERE id = ?"
    );
    $stmt->execute([
        $club_id, $first_name, $last_name,
        $phone !== '' ? $phone : null,
        $contact_name !== '' ? $contact_name : null,
        $description !== '' ? $description : null,
        $player_id,
    ]);
    redirect('/admin/players');

} elseif ($action === 'assign-team') {
    $new_team_id = (int)($_POST['team_id'] ?? 0);

    if ($new_team_id <= 0) {
        redirect('/admin/players?error=' . urlencode('Kein Team ausgewählt.'));
    }

    // Verify team exists and is active
    $team_check = $pdo->prepare("SELECT id FROM teams WHERE id = ? AND is_active = TRUE");
    $team_check->execute([$new_team_id]);
    if (!$team_check->fetch()) {
        redirect('/admin/players?error=' . urlencode('Team nicht gefunden oder inaktiv.'));
    }

    try {
        // Close any existing active membership
        $pdo->prepare(
            "UPDATE team_memberships SET left_at = NOW() WHERE player_id = ? AND left_at IS NULL"
        )->execute([$player_id]);

        // Open new membership
        $pdo->prepare(
            "INSERT INTO team_memberships (player_id, team_id) VALUES (?, ?)"
        )->execute([$player_id, $new_team_id]);

        redirect('/admin/players');
    } catch (PDOException $e) {
        error_log('Player assign-team error: ' . $e->getMessage());
        redirect('/admin/players?error=' . urlencode('Ein Fehler ist aufgetreten.'));
    }

} elseif ($action === 'link-user') {
    $user_id = (int)($_POST['user_id'] ?? 0);

    try {
        if ($user_id === 0) {
            // Unlink: clear player_id from any user currently linked to this player
            $pdo->prepare(
                "UPDATE users SET player_id = NULL WHERE player_id = ?"
            )->execute([$player_id]);
        } else {
            // Verify user exists and is a member
            $user_check = $pdo->prepare(
                "SELECT id FROM users WHERE id = ? AND role = 'member'"
            );
            $user_check->execute([$user_id]);
            if (!$user_check->fetch()) {
                redirect('/admin/players?error=' . urlencode('Mitglied nicht gefunden.'));
            }

            // Clear any existing link on this player (another user might have been linked)
            $pdo->prepare(
                "UPDATE users SET player_id = NULL WHERE player_id = ?"
            )->execute([$player_id]);

            // Link the selected user to this player
            $pdo->prepare(
                "UPDATE users SET player_id = ? WHERE id = ? AND role = 'member'"
            )->execute([$player_id, $user_id]);
        }
        redirect('/admin/players');
    } catch (PDOException $e) {
        error_log('Player link-user error: ' . $e->getMessage());
        redirect('/admin/players?error=' . urlencode('Ein Fehler ist aufgetreten.'));
    }

} else {
    redirect('/admin/players');
}
