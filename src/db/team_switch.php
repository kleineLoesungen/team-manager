<?php
// src/db/team_switch.php — Teamwechsel ohne neue Anmeldung
//
// Koordinatoren: alle aktiven Teams aus coordinator_teams (ein Konto, mehrere Teams).
// Mitglieder: ein Konto pro Team; wählbar sind die Konten desselben Mitgliederprofils
// (users.member_id) in anderen aktiven Teams. Der Wechsel übernimmt dann dieses Konto.

declare(strict_types=1);

/**
 * Teams the signed-in user can switch between, including the current one.
 * @return list<array{team_id: int, team_name: string, user_id: int}>
 */
function team_switch_options(PDO $pdo): array {
    $role    = $_SESSION['role'] ?? '';
    $user_id = (int)($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0 || !in_array($role, ['coordinator', 'member'], true)) return [];

    set_admin_context($pdo);   // teamübergreifend
    if ($role === 'coordinator') {
        $stmt = $pdo->prepare(
            "SELECT ct.team_id, t.name AS team_name, ct.user_id
             FROM coordinator_teams ct JOIN teams t ON t.id = ct.team_id
             WHERE ct.user_id = ? AND ct.left_at IS NULL AND t.is_active = TRUE
             ORDER BY t.name"
        );
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT u2.team_id, t.name AS team_name, u2.id AS user_id
             FROM users u
             JOIN users u2 ON u2.member_id = u.member_id AND u2.role = 'member' AND u2.is_active = TRUE
             JOIN teams t  ON t.id = u2.team_id AND t.is_active = TRUE
             WHERE u.id = ? AND u.member_id IS NOT NULL
             ORDER BY t.name"
        );
        $stmt->execute([$user_id]);
    }
    $rows = array_map(fn($r) => [
        'team_id'   => (int)$r['team_id'],
        'team_name' => $r['team_name'],
        'user_id'   => (int)$r['user_id'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));

    reset_rls_context($pdo);
    if (!empty($_SESSION['team_id'])) {
        set_team_context($pdo, (int)$_SESSION['team_id'], $role, $user_id);
    }
    return $rows;
}

/**
 * Switch the session to one of team_switch_options(): coordinators keep their account and get
 * the other team (users.team_id kept in sync for RLS), members take over the account of the
 * same profile in that team (new session id).
 * @param array{team_id: int, team_name: string, user_id: int} $option
 */
function team_switch_apply(PDO $pdo, array $option): void {
    $role = $_SESSION['role'] ?? '';
    set_admin_context($pdo);
    if ($role === 'coordinator') {
        $pdo->prepare("UPDATE users SET team_id = ? WHERE id = ?")->execute([$option['team_id'], (int)$_SESSION['user_id']]);
        $_SESSION['team_id']   = $option['team_id'];
        $_SESSION['team_name'] = $option['team_name'];
    } else {
        $stmt = $pdo->prepare("SELECT confirmed_at FROM users WHERE id = ?");
        $stmt->execute([$option['user_id']]);
        $confirmed_at = $stmt->fetchColumn();
        session_regenerate_id(true);
        $_SESSION['user_id']      = $option['user_id'];
        $_SESSION['team_id']      = $option['team_id'];
        $_SESSION['team_name']    = $option['team_name'];
        $_SESSION['confirmed_at'] = $confirmed_at ?: null;
    }
    $_SESSION['last_activity'] = time();
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], $role, (int)$_SESSION['user_id']);
}
