<?php
// src/db/member_stats.php — Kennzahlen eines Mitglieds über alle Listen
// Gemeinsam genutzt von der Statistikseite (src/member/stats_handler.php) und der Übersicht
// (src/db/dashboard.php), damit beide garantiert dieselben Zahlen zeigen.
// Nur öffentliche und geschützte Listen; Systemspalten brauchen den Admin-Kontext — die
// Aufrufer setzen ihn (set_admin_context) und stellen danach den Teamkontext wieder her.

declare(strict_types=1);

/** Global columns of a team (team-scoped + system), in display order. */
function member_stats_global_columns(PDO $pdo, int $team_id): array {
    $stmt = $pdo->prepare(
        "SELECT id, name, data_type FROM columns
         WHERE (team_id = ? OR is_system = TRUE) AND list_id IS NULL AND is_active = TRUE
         ORDER BY is_system DESC, sort_order, id"
    );
    $stmt->execute([$team_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Own totals per global column: Gesamt (up to today) and the 4-week windows.
 * @return array<int, array{all: float, 4w: float, 4_8w: float, 8_12w: float}> keyed by column id
 */
function member_stats_totals(PDO $pdo, int $team_id, int $member_id): array {
    // Aggregation: own row, public + protected lists, 4 time windows.
    // LEFT JOIN cells restricted to this member; LEFT JOIN lists restricted to public/protected.
    // WHERE (cells.id IS NULL OR lists.id IS NOT NULL) keeps columns without values but drops
    // cells of private lists (lists.id IS NULL means the visibility filter rejected them).
    $agg_sql = "
        SELECT
            c.id        AS column_id,
            c.name      AS column_name,
            c.data_type,

            -- Gesamt: all public/protected cells up to today (dated ≤ today or undated)
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN cells.id IS NOT NULL AND (lists.date IS NULL OR lists.date <= CURRENT_DATE) THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN cells.id IS NOT NULL AND (lists.date IS NULL OR lists.date <= CURRENT_DATE) AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_all,

            -- Letzte 4 Wochen: lists.date within last 28 days
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '28 days' AND lists.date <= CURRENT_DATE THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '28 days' AND lists.date <= CURRENT_DATE AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_4w,

            -- 4–8 Wochen
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '56 days' AND lists.date < CURRENT_DATE - INTERVAL '28 days' THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '56 days' AND lists.date < CURRENT_DATE - INTERVAL '28 days' AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_4_8w,

            -- 8–12 Wochen
            COALESCE(
                CASE
                    WHEN c.data_type = 'number'  THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '84 days' AND lists.date < CURRENT_DATE - INTERVAL '56 days' THEN CAST(cells.value AS NUMERIC) ELSE 0 END)
                    WHEN c.data_type = 'boolean' THEN SUM(CASE WHEN lists.date IS NOT NULL AND lists.date >= CURRENT_DATE - INTERVAL '84 days' AND lists.date < CURRENT_DATE - INTERVAL '56 days' AND cells.value IN ('true','1') THEN 1 ELSE 0 END)
                END, 0
            ) AS sum_8_12w

        FROM (
            SELECT id, name, data_type, sort_order
            FROM columns
            WHERE (team_id = ? OR is_system = TRUE) AND list_id IS NULL AND is_active = TRUE
        ) c
        LEFT JOIN cells ON cells.column_id = c.id
                       AND cells.member_id = ?
                       AND EXISTS (
                           SELECT 1 FROM list_global_columns lgc
                           WHERE lgc.list_id = cells.list_id AND lgc.column_id = c.id
                       )
        LEFT JOIN lists ON cells.list_id = lists.id
                       AND lists.visibility IN ('public', 'protected')
        WHERE (cells.id IS NULL OR lists.id IS NOT NULL)
        GROUP BY c.id, c.name, c.data_type, c.sort_order
        ORDER BY c.sort_order, c.id
    ";

    $stmt = $pdo->prepare($agg_sql);
    $stmt->execute([$team_id, $member_id]);
    $totals = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $totals[(int)$row['column_id']] = [
            'all'   => (float)$row['sum_all'],
            '4w'    => (float)$row['sum_4w'],
            '4_8w'  => (float)$row['sum_4_8w'],
            '8_12w' => (float)$row['sum_8_12w'],
        ];
    }
    return $totals;
}
