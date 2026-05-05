<?php
// src/db/visibility.php — Visibility and ownership check helpers for Phase 3
// Used by ALL handlers that touch lists, columns, or cells.
// Application-layer authorization: RLS is defense-in-depth; these checks are authoritative.

declare(strict_types=1);

/**
 * Check if the current session user can VIEW a list (access the list detail page).
 *
 * Rules:
 * - Coaches can view any list belonging to their team (public, protected, private)
 * - Players can view public and protected lists; private lists are invisible to players
 *
 * @param int $list_id  The list to check
 * @return bool
 */
function can_view_list(int $list_id): bool {
    if (empty($_SESSION['team_id']) || empty($_SESSION['role'])) {
        return false;
    }

    $pdo = get_db();
    // Temporarily use admin context to read list metadata without RLS filtering
    reset_rls_context($pdo);
    set_admin_context($pdo);

    $stmt = $pdo->prepare(
        "SELECT visibility, team_id FROM lists WHERE id = ?"
    );
    $stmt->execute([$list_id]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);

    // Restore team context immediately
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], $_SESSION['role'], (int)$_SESSION['user_id']);

    if (!$list) {
        return false; // List not found
    }

    // Cross-team access — never allowed
    if ((int)$list['team_id'] !== (int)$_SESSION['team_id']) {
        return false;
    }

    $role = $_SESSION['role'] ?? '';

    if ($role === 'coordinator') {
        return true; // Coordinators see all lists in their team
    }

    if ($role === 'member') {
        return in_array($list['visibility'], ['public', 'protected'], true);
    }

    return false;
}

/**
 * Check if the current session user can EDIT a specific player's cells in a list.
 *
 * Rules:
 * - Coaches can edit any player's cells in public, protected, or private lists (CELL-03: full access)
 * - Players can edit only their OWN cells, and only in public lists (CELL-01)
 * - Private lists: players cannot edit; coaches can edit
 *
 * @param int $list_id    The list containing the cells
 * @param int $player_id  The player whose cells are being edited
 * @return bool
 */
function can_edit_cell(int $list_id, int $player_id): bool {
    if (empty($_SESSION['team_id']) || empty($_SESSION['role']) || empty($_SESSION['user_id'])) {
        return false;
    }

    $pdo = get_db();
    // Temporarily use admin context to read list metadata
    reset_rls_context($pdo);
    set_admin_context($pdo);

    $stmt = $pdo->prepare(
        "SELECT visibility, team_id FROM lists WHERE id = ?"
    );
    $stmt->execute([$list_id]);
    $list = $stmt->fetch(PDO::FETCH_ASSOC);

    // Restore team context immediately
    reset_rls_context($pdo);
    set_team_context($pdo, (int)$_SESSION['team_id'], $_SESSION['role'], (int)$_SESSION['user_id']);

    if (!$list) {
        return false;
    }

    // Cross-team access — never allowed
    if ((int)$list['team_id'] !== (int)$_SESSION['team_id']) {
        return false;
    }

    $role       = $_SESSION['role'] ?? '';
    $visibility = $list['visibility'];

    if ($role === 'coordinator') {
        // CELL-03: Coordinators have full read/write access to all lists in their team (public, protected, private)
        return true;
    }

    if ($role === 'member') {
        // CELL-01: players can only edit their own row in public lists
        $is_own_row = (int)$_SESSION['user_id'] === $player_id;
        $is_public  = $visibility === 'public';
        return $is_own_row && $is_public;
    }

    return false;
}
