<?php
// src/templates/coordinator/player_profile.php — Player profile for coordinator
declare(strict_types=1);

// Determine active membership from history
$active_membership = null;
foreach ($history as $h) {
    if ($h['left_at'] === null) {
        $active_membership = $h;
        break;
    }
}
?>
<div class="mb-3">
    <a href="/coordinator/players" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Übersicht
    </a>
</div>

<!-- Player header card -->
<div class="card mb-4 shadow-sm">
    <div class="card-body">
        <h2 class="card-title h5 fw-bold mb-1">
            <?= e($player['first_name'] . ' ' . $player['last_name']) ?>
        </h2>
        <?php if (!empty($player['club_name'])): ?>
        <div class="text-muted mb-2">
            <i class="bi bi-building me-1"></i><?= e($player['club_name']) ?>
        </div>
        <?php endif; ?>
        <?php if ($active_membership): ?>
        <div class="text-muted small mb-2">
            <i class="bi bi-people me-1"></i>
            <strong><?= e($active_membership['team_name']) ?></strong>
            (seit <?= e(date('d.m.Y', strtotime($active_membership['joined_at']))) ?>)
        </div>
        <?php endif; ?>
        <?php if (!empty($player['phone'])): ?>
        <div class="text-muted small mb-1">
            <i class="bi bi-telephone me-1"></i>
            <a href="tel:<?= e($player['phone']) ?>"><?= e($player['phone']) ?></a>
        </div>
        <?php endif; ?>
        <?php if (!empty($player['contact_name'])): ?>
        <div class="text-muted small mb-1">
            <i class="bi bi-person-lines-fill me-1"></i>
            Kontaktperson: <?= e($player['contact_name']) ?>
        </div>
        <?php endif; ?>
        <?php if ($linked_user_id): ?>
        <div class="mt-2">
            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                <i class="bi bi-link-45deg me-1"></i>Benutzeraccount verknüpft
            </span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($player['description'])): ?>
<!-- Description -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Beschreibung</div>
    <div class="card-body">
        <p class="mb-0"><?= nl2br(e($player['description'])) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Team accounts -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Teamzugehörigkeit</div>
    <?php if (empty($history)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Kein Benutzeraccount in einem Team verknüpft.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Team</th>
                    <th>Benutzername</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e($h['team_name']) ?></td>
                    <td><code class="small"><?= e($h['username']) ?></code></td>
                    <td>
                        <?php if ($h['team_active'] && $h['is_active']): ?>
                        <span class="badge bg-success">Aktiv</span>
                        <?php elseif (!$h['team_active']): ?>
                        <span class="badge bg-secondary">Team inaktiv</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">Konto inaktiv</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Attributes -->
<?php if (!empty($attr_groups)): ?>
<form method="POST" action="/coordinator/players/<?= (int)$player_id ?>/attributes/save">
    <?= csrf_field() ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-semibold mb-0">Attribute</h3>
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-check-lg me-1"></i>Speichern
        </button>
    </div>
    <?php foreach ($attr_groups as $group_name => $group): ?>
    <div class="card mb-3">
        <div class="card-header fw-semibold"><?= e($group_name) ?></div>
        <div class="card-body">
            <?php foreach ($group['attrs'] as $attr): ?>
            <div class="mb-3">
                <label class="form-label fw-medium mb-1">
                    <?= e($attr['attr_name']) ?>
                    <?php if (!$attr['visible_to_player']): ?>
                    <span class="badge bg-secondary ms-1" style="font-size:0.65rem">Nur Koordinator</span>
                    <?php endif; ?>
                </label>
                <input type="text"
                       class="form-control form-control-sm"
                       name="values[<?= (int)$attr['attr_id'] ?>]"
                       value="<?= e($attr['value']) ?>"
                       placeholder="Kein Wert">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="mb-4">
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i>Alle Attribute speichern
        </button>
    </div>
</form>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header fw-semibold">Attribute</div>
    <div class="card-body">
        <p class="text-muted mb-0">Keine Attributgruppen konfiguriert. Der Admin kann Attribute unter Spielerattribute anlegen.</p>
    </div>
</div>
<?php endif; ?>

<!-- Cross-team statistics -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Statistiken (teamübergreifend)</div>
    <?php if ($linked_user_id === false || $linked_user_id === null): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Kein Benutzeraccount verknüpft — keine Statistiken verfügbar.</p>
    </div>
    <?php elseif (empty($cross_stats)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Noch keine Einsatzdaten für diesen Spieler.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Team</th>
                    <th>Datum</th>
                    <th>Spalte</th>
                    <th>Wert</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cross_stats as $stat): ?>
                <tr>
                    <td><?= e($stat['team_name']) ?></td>
                    <td><?= $stat['date'] ? e(date('d.m.Y', strtotime($stat['date']))) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= e($stat['col_name']) ?></td>
                    <td>
                        <?php if ($stat['data_type'] === 'boolean'): ?>
                        <?= in_array($stat['value'], ['1', 'true'], true) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?>
                        <?php else: ?>
                        <?= e($stat['value']) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
