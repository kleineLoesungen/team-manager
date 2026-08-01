<?php
// src/templates/admin/players.php — Admin player list with filter + inline actions
// Variables: $players (array), $clubs (array), $teams (array), $filter_club_id (int), $filter_team_id (int)
?>
<?php
// Load member users for link-user dropdown
$all_members = get_db()->query(
    "SELECT id, username, first_name, last_name FROM users WHERE role = 'member' ORDER BY last_name ASC, first_name ASC"
)->fetchAll();
?>

<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-danger"><?= e($_GET['error']) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?= count($players) ?> Spieler<?= count($players) !== 1 ? '' : '' ?></span>
    <a href="/admin/players/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Spieler hinzufügen
    </a>
</div>

<!-- Filter form -->
<form method="GET" action="/admin/players" class="row g-2 mb-4">
    <div class="col-6">
        <select name="club_id" class="form-select form-select-sm">
            <option value="0">Alle Klubs</option>
            <?php foreach ($clubs as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $filter_club_id === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-4">
        <select name="team_id" class="form-select form-select-sm">
            <option value="0">Alle Teams</option>
            <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $filter_team_id === (int)$t['id'] ? 'selected' : '' ?>>
                <?= e($t['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-2">
        <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Filtern</button>
    </div>
</form>

<?php if (empty($players)): ?>
<div class="alert alert-info">
    Keine Spieler gefunden. <a href="/admin/players/create" class="alert-link">Ersten Spieler anlegen</a>.
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($players as $p): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <!-- Player name + badges -->
                <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
                    <div>
                        <h2 class="h6 fw-semibold mb-0">
                            <?= e($p['last_name']) ?>, <?= e($p['first_name']) ?>
                        </h2>
                        <div class="text-muted small mt-1">
                            <span class="me-2"><i class="bi bi-building me-1"></i><?= e($p['club_name'] ?? '—') ?></span>
                            <?php if (!empty($p['current_team_name'])): ?>
                            <span class="badge bg-success-subtle text-success-emphasis">
                                <?= e($p['current_team_name']) ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Kein Team</span>
                            <?php endif; ?>
                            <?php if (!empty($p['linked_username'])): ?>
                            <span class="ms-1 badge bg-primary-subtle text-primary-emphasis">
                                <i class="bi bi-person-check me-1"></i><?= e($p['linked_username']) ?>
                            </span>
                            <?php else: ?>
                            <span class="ms-1 badge bg-light text-muted border">Kein Account</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($p['phone'])): ?>
                        <div class="text-muted small"><?= e($p['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Inline edit fields (collapsed toggle) -->
                <div class="accordion accordion-flush" id="acc-<?= (int)$p['id'] ?>">
                    <div class="accordion-item border-0">
                        <h3 class="accordion-header">
                            <button class="accordion-button collapsed p-0 bg-transparent text-primary small fw-normal"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#edit-<?= (int)$p['id'] ?>">
                                Bearbeiten
                            </button>
                        </h3>
                        <div id="edit-<?= (int)$p['id'] ?>" class="accordion-collapse collapse"
                             data-bs-parent="#acc-<?= (int)$p['id'] ?>">
                            <div class="accordion-body px-0 pt-2 pb-0">

                                <!-- Edit basic fields -->
                                <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/edit" class="mb-3">
                                    <?= csrf_field() ?>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <input type="text" name="first_name" class="form-control form-control-sm"
                                                   value="<?= e($p['first_name']) ?>" placeholder="Vorname" required>
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="last_name" class="form-control form-control-sm"
                                                   value="<?= e($p['last_name']) ?>" placeholder="Nachname" required>
                                        </div>
                                        <div class="col-12">
                                            <select name="club_id" class="form-select form-select-sm" required>
                                                <option value="0">Klub wählen …</option>
                                                <?php foreach ($clubs as $c): ?>
                                                <option value="<?= (int)$c['id'] ?>"
                                                    <?= (int)$p['club_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                                    <?= e($c['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="phone" class="form-control form-control-sm"
                                                   value="<?= e($p['phone'] ?? '') ?>" placeholder="Telefon (optional)">
                                        </div>
                                        <div class="col-6">
                                            <input type="text" name="contact_name" class="form-control form-control-sm"
                                                   value="<?= e($p['contact_name'] ?? '') ?>" placeholder="Kontaktname (optional)">
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
                                </form>

                                <!-- Assign to team -->
                                <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/assign-team" class="mb-3">
                                    <?= csrf_field() ?>
                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                        <select name="team_id" class="form-select form-select-sm" style="min-width:160px">
                                            <option value="0">Team wählen …</option>
                                            <?php foreach ($teams as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"
                                                <?= (int)($p['current_team_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>>
                                                <?= e($t['name']) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-success">Team zuweisen</button>
                                    </div>
                                </form>

                                <!-- Link to member account -->
                                <form method="POST" action="/admin/players/<?= (int)$p['id'] ?>/link-user">
                                    <?= csrf_field() ?>
                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                        <select name="user_id" class="form-select form-select-sm" style="min-width:160px">
                                            <option value="0">— Kein Account —</option>
                                            <?php foreach ($all_members as $m): ?>
                                            <option value="<?= (int)$m['id'] ?>"
                                                <?= (int)($p['linked_user_id'] ?? 0) === (int)$m['id'] ? 'selected' : '' ?>>
                                                <?= e($m['username']) ?> (<?= e($m['last_name']) ?>, <?= e($m['first_name']) ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Account verknüpfen</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
