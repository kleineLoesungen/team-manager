<?php
// src/templates/member/player_profile.php — Member-facing player profile
declare(strict_types=1);
?>

<?php if ($player === null): ?>
<!-- Not linked state: member has no linked player record -->
<div class="card shadow-sm">
    <div class="card-body text-center py-5">
        <i class="bi bi-person-badge display-4 text-muted mb-3 d-block"></i>
        <p class="mb-1 fw-semibold">Kein Spielerprofil verknüpft</p>
        <p class="text-muted small mb-0">
            Dein Konto ist noch nicht mit einem Spielerprofil verknüpft.
            Bitte wende dich an deinen Koordinator.
        </p>
    </div>
</div>

<?php else: ?>

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
        <?php if (!empty($player['description'])): ?>
        <p class="text-muted small mt-2 mb-0"><?= nl2br(e($player['description'])) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- Team membership history -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Teamzugehörigkeit</div>
    <?php if (empty($history)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Keine Teamzugehörigkeit erfasst.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Team</th>
                    <th>Beitritt</th>
                    <th>Abgang</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= e($h['team_name']) ?></td>
                    <td><?= e(date('d.m.Y', strtotime($h['joined_at']))) ?></td>
                    <td>
                        <?php if ($h['left_at'] === null): ?>
                        <span class="badge bg-success">Aktuell</span>
                        <?php else: ?>
                        <?= e(date('d.m.Y', strtotime($h['left_at']))) ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Attribute groups (visible_to_player only) -->
<?php if (!empty($attr_groups)): ?>
<div class="mb-2">
    <h3 class="h6 fw-semibold">Meine Attribute</h3>
</div>
<?php foreach ($attr_groups as $group_name => $group): ?>
<div class="card mb-3">
    <div class="card-header fw-semibold"><?= e($group_name) ?></div>
    <div class="card-body">
        <?php foreach ($group['attrs'] as $attr): ?>
        <div class="mb-3">
            <label class="form-label fw-medium mb-1"><?= e($attr['attr_name']) ?></label>
            <?php if ($attr['editable_by_player']): ?>
            <!-- TODO: member-editable attribute save is not in scope for this phase; shown read-only -->
            <p class="form-control-plaintext py-0 mb-0 text-<?= $attr['value'] !== '' ? 'body' : 'muted' ?>">
                <?= $attr['value'] !== '' ? e($attr['value']) : '—' ?>
            </p>
            <?php else: ?>
            <p class="form-control-plaintext py-0 mb-0 text-<?= $attr['value'] !== '' ? 'body' : 'muted' ?>">
                <?= $attr['value'] !== '' ? e($attr['value']) : '—' ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Cross-team stats -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Vergangene Einsätze</div>
    <?php if (empty($cross_stats)): ?>
    <div class="card-body">
        <p class="text-muted mb-0">Noch keine Einsatzdaten vorhanden.</p>
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
                        <?= in_array($stat['value'], ['1', 'true'], true)
                            ? '<i class="bi bi-check-circle-fill text-success"></i>'
                            : '<i class="bi bi-x-circle text-muted"></i>' ?>
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

<?php endif; ?>
