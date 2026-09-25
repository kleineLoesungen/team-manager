<?php
// src/admin/coordinators_handler.php — Admin coordinators list page

declare(strict_types=1);

require_admin();

$pdo = get_db();

// All coordinators with full data — personal info from players (canonical person table)
$coordinators_stmt = $pdo->query(
    "SELECT u.id, p.first_name, p.last_name, u.username, u.is_active, p.email, p.phone,
            u.confirmed_at, cl.name AS club_name
     FROM users u
     JOIN members p ON p.id = u.member_id
     LEFT JOIN clubs cl ON cl.id = p.club_id
     WHERE u.role = 'coordinator'
     ORDER BY p.last_name, p.first_name"
);
$all_coordinators  = $coordinators_stmt->fetchAll();
$coordinators_by_id = array_column($all_coordinators, null, 'id');

// Team assignments: build team→[user_ids] and user→[team_ids] maps
$ct_stmt = $pdo->query(
    "SELECT ct.user_id, ct.team_id, t.name AS team_name
     FROM coordinator_teams ct
     JOIN teams t ON t.id = ct.team_id
     WHERE ct.left_at IS NULL AND t.is_active = TRUE"
);
$team_coordinator_ids  = [];  // team_id → [user_id, ...]
$coordinator_team_names = []; // user_id → [team_name, ...]
foreach ($ct_stmt->fetchAll() as $row) {
    $team_coordinator_ids[$row['team_id']][]    = (int)$row['user_id'];
    $coordinator_team_names[$row['user_id']][]  = $row['team_name'];
}

// Active teams in sort order — for grouped display
$active_teams = $pdo->query(
    "SELECT id, name FROM teams WHERE is_active = TRUE ORDER BY sort_order ASC, name ASC"
)->fetchAll();

$error   = !empty($_GET['error'])   ? e($_GET['error'])   : '';
$success = !empty($_GET['success']) ? e($_GET['success']) : '';

$active_coordinators   = array_filter($all_coordinators, fn($c) =>  $c['is_active']);
$inactive_coordinators = array_filter($all_coordinators, fn($c) => !$c['is_active']);

// Active coordinators with no team assignment
$active_no_team = array_values(array_filter(
    $active_coordinators,
    fn($c) => empty($team_coordinator_ids) || !array_reduce(
        $active_teams,
        fn($carry, $t) => $carry || in_array((int)$c['id'], $team_coordinator_ids[$t['id']] ?? []),
        false
    )
));

require ROOT_PATH . '/src/templates/admin/layout.php';

render_admin_page('Koordinatoren verwalten', 'coordinators', function() use (
    $active_coordinators, $inactive_coordinators, $active_no_team,
    $active_teams, $team_coordinator_ids, $coordinator_team_names,
    $coordinators_by_id, $error, $success
) {
    if ($error)   echo '<div class="alert alert-danger">'  . $error   . '</div>';
    if ($success) echo '<div class="alert alert-success">' . $success . '</div>';
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <span class="text-muted"><?= count($active_coordinators) ?> aktive Koordinatoren</span>
        <a href="/admin/coordinators/create" class="btn btn-primary min-touch">
            <i class="bi bi-plus-lg me-1"></i>Koordinator hinzufügen
        </a>
    </div>

    <?php if (empty($active_coordinators) && empty($inactive_coordinators)): ?>
    <div class="text-center py-5">
        <p class="h5 text-muted">Keine Koordinatoren vorhanden</p>
        <p class="text-muted">Füge einen oder mehrere Koordinatoren hinzu.</p>
    </div>
    <?php else: ?>

    <?php
    // Reusable coordinator row renderer
    $render_coordinator = function(array $c) use ($coordinator_team_names) {
        $my_teams = $coordinator_team_names[$c['id']] ?? [];
        ?>
        <div class="list-group-item">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                <div class="fw-semibold d-flex align-items-center gap-2">
                    <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                    <?php if (empty($c['confirmed_at'])): ?>
                    <i class="bi bi-exclamation-circle text-muted small flex-shrink-0"
                       title="Profil noch nicht bestätigt"></i>
                    <?php endif; ?>
                </div>
                <div class="text-muted small">@<?= e($c['username']) ?></div>
            </div>
            <?php if (!empty($c['club_name'])): ?>
            <div class="text-muted small mt-1">
                <i class="bi bi-building me-1"></i><?= e($c['club_name']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($my_teams)): ?>
            <div class="text-muted small mt-1">
                <?= implode(' · ', array_map('htmlspecialchars', $my_teams)) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($c['email'])): ?>
            <div class="text-muted small mt-1"><i class="bi bi-envelope me-1"></i><?= e($c['email']) ?></div>
            <?php endif; ?>
            <?php if (!empty($c['phone'])): ?>
            <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= e($c['phone']) ?></div>
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <a href="/admin/coordinators/<?= $c['id'] ?>/settings" data-save-scroll
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Bearbeiten
                </a>
                <form method="POST"
                      action="/admin/coordinators/<?= $c['id'] ?>/reset-password"
                      onsubmit="return confirm('<?= e('Das Passwort wird zurückgesetzt und angezeigt. Diese Aktion kann nicht rückgängig gemacht werden.') ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-key me-1"></i>Passwort
                    </button>
                </form>
                <form method="POST"
                      action="/admin/coordinators/<?= $c['id'] ?>/deactivate"
                      onsubmit="return confirm('<?= e('Der Koordinator wird deaktiviert und kann sich nicht mehr anmelden.') ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-warning">
                        <i class="bi bi-pause-circle me-1"></i>Deaktivieren
                    </button>
                </form>
            </div>
        </div>
        <?php
    };
    ?>

    <?php if (!empty($active_coordinators)): ?>
    <?php foreach ($active_teams as $team): ?>
    <?php $team_ids = $team_coordinator_ids[$team['id']] ?? []; ?>
    <?php if (empty($team_ids)): continue; endif; ?>
    <h3 class="h6 fw-semibold text-muted mb-2 mt-4"><?= e($team['name']) ?></h3>
    <div class="list-group mb-2">
        <?php foreach ($team_ids as $uid): ?>
        <?php if (!empty($coordinators_by_id[$uid]) && $coordinators_by_id[$uid]['is_active']): ?>
        <?php $render_coordinator($coordinators_by_id[$uid]); ?>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($active_no_team)): ?>
    <h3 class="h6 fw-semibold text-muted mb-2 mt-4">Kein Team</h3>
    <div class="list-group mb-2">
        <?php foreach ($active_no_team as $c): ?>
        <?php $render_coordinator($c); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($inactive_coordinators)): ?>
    <div class="mt-4">
        <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#inactiveCoordinators"
                aria-expanded="false">
            <i class="bi bi-chevron-down"></i>
            Inaktiv (<?= count($inactive_coordinators) ?>)
        </button>
        <div class="collapse mt-2" id="inactiveCoordinators">
            <div class="list-group opacity-75">
                <?php foreach ($inactive_coordinators as $coordinator): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <div class="fw-semibold text-muted">
                            <?= e($coordinator['first_name'] . ' ' . $coordinator['last_name']) ?>
                            <span class="badge bg-secondary ms-1">Deaktiviert</span>
                        </div>
                        <code class="text-muted small flex-shrink-0"><?= e($coordinator['username']) ?></code>
                    </div>
                    <?php if (!empty($coordinator['club_name'])): ?>
                    <div class="text-muted small mb-1">
                        <i class="bi bi-building me-1"></i><?= e($coordinator['club_name']) ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 flex-wrap mt-2">
                        <form method="POST"
                              action="/admin/coordinators/<?= $coordinator['id'] ?>/reactivate"
                              onsubmit="return confirm('<?= e('Der Koordinator wird reaktiviert und kann sich wieder anmelden.') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                            </button>
                        </form>
                        <form method="POST"
                              action="/admin/coordinators/<?= $coordinator['id'] ?>/delete"
                              onsubmit="return confirm('<?= e('Koordinator ' . $coordinator['first_name'] . ' ' . $coordinator['last_name'] . ' endgültig löschen? Diese Aktion kann nicht rückgängig gemacht werden.') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash me-1"></i>Löschen
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
    <?php
});
