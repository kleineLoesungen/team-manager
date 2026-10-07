<?php
// src/db/departments.php — Abteilungen (z. B. Fußball, Tennis)
//
// Eine Abteilung gruppiert Teams und Ressourcen. Ein Team sieht nur die Ressourcen seiner
// Abteilung, Mitglieder in der Koordinatorenübersicht nur die Teams der Abteilung, und der
// öffentliche Ticker gruppiert nach Abteilung. Mitglieder und Organisationen gehören zu
// keiner Abteilung — dieselbe Person kann in Teams verschiedener Abteilungen sein.

declare(strict_types=1);

/** Departments (id, name, is_active), active first; $active_only for selections. */
function departments_list(PDO $pdo, bool $active_only = false): array {
    return $pdo->query(
        "SELECT id, name, is_active FROM departments"
        . ($active_only ? " WHERE is_active = TRUE" : "")
        . " ORDER BY is_active DESC, name"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/** The department of a team (teams has no RLS, works in any context). */
function team_department_id(PDO $pdo, int $team_id): ?int {
    $stmt = $pdo->prepare("SELECT department_id FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

/** Selected department filter from ?department= (admin lists); null = all. */
function department_filter(array $departments): ?int {
    $id  = (int)($_GET['department'] ?? 0);
    $ids = array_map('intval', array_column($departments, 'id'));
    return in_array($id, $ids, true) ? $id : null;
}

/** Departments of a member's active teams (all roles of that person); cross-team, admin context. */
function departments_of_member(PDO $pdo, int $member_id): array {
    return as_admin($pdo, function () use ($pdo, $member_id) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT t.department_id FROM users u
             JOIN teams t ON t.id = u.team_id AND t.is_active = TRUE
             WHERE u.member_id = ? AND u.is_active = TRUE"
        );
        $stmt->execute([$member_id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    });
}

/**
 * Attribute groups (member_attribute_groups) a viewer sees: general ones (no department) plus
 * those of the given departments. SQL condition for alias $g with one placeholder (int[]),
 * value from departments_param().
 */
function attribute_groups_scope_sql(string $g): string {
    return "($g.department_id IS NULL OR $g.department_id = ANY(CAST(? AS int[])))";
}

/** PostgreSQL int[] literal for a placeholder ('{1,2}'). */
function departments_param(array $department_ids): string {
    return '{' . implode(',', array_map('intval', $department_ids)) . '}';
}
