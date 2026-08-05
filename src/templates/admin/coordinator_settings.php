<?php declare(strict_types=1); ?>

<div class="mb-3">
    <a href="/admin/coordinators" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<!-- Team assignments -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Teams</div>
    <div class="card-body">
        <?php if (!empty($assigned_teams)): ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($assigned_teams as $ct): ?>
            <form method="POST" action="/admin/coordinators/<?= (int)$coordinator['id'] ?>/remove-team" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="team_id" value="<?= (int)$ct['team_id'] ?>">
                <button type="submit"
                        class="btn btn-sm badge bg-primary-subtle text-primary-emphasis border border-primary-subtle py-1 px-2 d-inline-flex align-items-center gap-1"
                        onclick="return confirm('<?= e('Koordinator aus Team ' . $ct['team_name'] . ' entfernen?') ?>')">
                    <?= e($ct['team_name']) ?><i class="bi bi-x ms-1"></i>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted small mb-3">Noch keinem Team zugewiesen.</p>
        <?php endif; ?>

        <?php if (!empty($available_teams)): ?>
        <form method="POST" action="/admin/coordinators/<?= (int)$coordinator['id'] ?>/add-team" class="d-flex align-items-center gap-2">
            <?= csrf_field() ?>
            <select name="team_id" class="form-select form-select-sm" style="max-width:220px">
                <?php foreach ($available_teams as $t): ?>
                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Hinzufügen
            </button>
        </form>
        <?php elseif (!empty($assigned_teams)): ?>
        <p class="text-muted small mb-0">Alle aktiven Teams sind bereits zugewiesen.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Club assignment -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Verein</div>
    <div class="card-body">
        <?php if (empty($clubs)): ?>
        <p class="text-muted small mb-0">
            Keine aktiven Vereine vorhanden.
            <a href="/admin/clubs">Verein anlegen</a>
        </p>
        <?php else: ?>
        <form method="POST" action="/admin/coordinators/<?= (int)$coordinator['id'] ?>/set-club">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="club_id" class="form-label">Verein</label>
                <select name="club_id" id="club_id" class="form-select">
                    <option value="">— Kein Verein —</option>
                    <?php foreach ($clubs as $club): ?>
                    <option value="<?= (int)$club['id'] ?>"
                            <?= (int)$coordinator['club_id'] === (int)$club['id'] ? 'selected' : '' ?>>
                        <?= e($club['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
        </form>
        <?php endif; ?>
    </div>
</div>
