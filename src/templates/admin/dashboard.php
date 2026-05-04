<?php
// src/templates/admin/dashboard.php — Admin teams dashboard
// Variables: $teams (array of team rows), $coaches_by_team (array keyed by team_id)

$active_teams   = array_filter($teams, fn($t) => $t['is_active']);
$inactive_teams = array_filter($teams, fn($t) => !$t['is_active']);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_teams) ?> aktive(s) Team(s)</span>
    <button type="button" class="btn btn-primary min-touch"
            data-bs-toggle="modal" data-bs-target="#createTeamModal">
        <i class="bi bi-plus-lg me-1"></i>Team erstellen
    </button>
</div>

<?php if (empty($teams)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Teams</p>
    <p class="text-muted">Erstelle ein neues Team, um zu beginnen.</p>
</div>
<?php else: ?>

<?php if (!empty($active_teams)): ?>
<div class="row g-3">
    <?php foreach ($active_teams as $team): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="h5 fw-semibold mb-1"><?= e($team['name']) ?></h2>
                        <p class="text-muted small mb-0">
                            <?php
                            $count = count($coaches_by_team[$team['id']] ?? []);
                            echo $count === 0
                                ? 'Keine Moderatoren zugewiesen'
                                : $count . ' Moderatoren zugewiesen';
                            ?>
                        </p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal"
                                data-bs-target="#editTeamModal<?= $team['id'] ?>">
                            Bearbeiten
                        </button>
                        <form method="POST"
                              action="/admin/teams/<?= $team['id'] ?>/deactivate"
                              onsubmit="return confirm('<?= e('Das Team wird deaktiviert. Alle Moderatoren und Mitglieder bleiben im System, können sich aber nicht anmelden.') ?>')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                Team deaktivieren
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Coaches list for this team -->
                <?php if (!empty($coaches_by_team[$team['id']])): ?>
                <ul class="list-unstyled mt-2 mb-0">
                    <?php foreach ($coaches_by_team[$team['id']] as $coach): ?>
                    <li class="small text-muted">
                        <i class="bi bi-person me-1"></i>
                        <?= e($coach['first_name'] . ' ' . $coach['last_name']) ?>
                        <code class="ms-1">(<?= e($coach['username']) ?>)</code>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Team Modal -->
        <div class="modal fade" id="editTeamModal<?= $team['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-semibold">Team bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="/admin/teams/<?= $team['id'] ?>/edit">
                        <?= csrf_field() ?>
                        <div class="modal-body">
                            <label for="team_name_<?= $team['id'] ?>" class="form-label fw-semibold small">
                                Teamname
                            </label>
                            <input type="text"
                                   class="form-control min-touch"
                                   id="team_name_<?= $team['id'] ?>"
                                   name="team_name"
                                   value="<?= e($team['name']) ?>"
                                   required
                                   maxlength="100">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                Abbrechen
                            </button>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($inactive_teams)): ?>
<div class="mt-4">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#inactiveTeams"
            aria-expanded="false">
        <i class="bi bi-chevron-down"></i>
        Inaktiv (<?= count($inactive_teams) ?>)
    </button>
    <div class="collapse mt-2" id="inactiveTeams">
        <div class="row g-3">
            <?php foreach ($inactive_teams as $team): ?>
            <div class="col-12">
                <div class="card border-secondary opacity-75">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="h5 fw-semibold mb-1 text-muted"><?= e($team['name']) ?></h2>
                                <span class="badge bg-secondary">Deaktiviert</span>
                                <p class="text-muted small mb-0 mt-1">
                                    <?php
                                    $count = count($coaches_by_team[$team['id']] ?? []);
                                    echo $count === 0
                                        ? 'Keine Trainer zugewiesen'
                                        : $count . ' Trainer zugewiesen';
                                    ?>
                                </p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                <form method="POST"
                                      action="/admin/teams/<?= $team['id'] ?>/reactivate"
                                      onsubmit="return confirm('<?= e('Das Team wird reaktiviert. Moderatoren können sich wieder anmelden.') ?>')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reaktivieren
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Create Team Modal -->
<div class="modal fade" id="createTeamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">Team erstellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/admin/teams/create">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <label for="new_team_name" class="form-label fw-semibold small">Teamname</label>
                    <input type="text"
                           class="form-control min-touch"
                           id="new_team_name"
                           name="team_name"
                           required
                           maxlength="100"
                           placeholder="z.B. U17 Herren">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-primary">Team erstellen</button>
                </div>
            </form>
        </div>
    </div>
</div>
