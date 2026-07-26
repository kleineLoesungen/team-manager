<?php
// src/templates/coordinator/ticker.php — coordinator ticker list
// Variables: $tickers (array)
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($tickers) ?> <?= count($tickers) === 1 ? 'Ticker' : 'Ticker' ?></span>
    <a href="/coordinator/ticker/new" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>Neuer Ticker
    </a>
</div>

<?php if (empty($tickers)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Ticker erstellt</p>
    <p class="text-muted">Du kannst einen neuen Ticker anlegen, um Events zu dokumentieren.</p>
    <a href="/coordinator/ticker/new" class="btn btn-primary mt-2">+ Neuer Ticker</a>
</div>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($tickers as $t): ?>
    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start py-3">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/coordinator/ticker/<?= (int)$t['id'] ?>" class="fw-semibold text-decoration-none">
                    <?= e($t['name']) ?>
                </a>
                <?php if ($t['status'] === 'active'): ?>
                    <span class="badge bg-success">Aktiv</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Geschlossen</span>
                <?php endif; ?>
            </div>
            <?php if ($t['description']): ?>
            <p class="mb-0 text-muted small"><?= e($t['description']) ?></p>
            <?php endif; ?>
            <p class="mb-0 text-muted small mt-1">
                <i class="bi bi-clock me-1"></i><?= e(date('d.m.Y H:i', strtotime($t['created_at']))) ?>
            </p>
        </div>
        <div class="d-flex gap-2 ms-3 flex-shrink-0">
            <a href="/coordinator/ticker/<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary">Ansehen</a>
            <?php if ($t['status'] === 'active'): ?>
            <form method="POST" action="/coordinator/ticker/<?= (int)$t['id'] ?>/close" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-warning">Schließen</button>
            </form>
            <?php endif; ?>
            <a href="/coordinator/ticker/<?= (int)$t['id'] ?>/delete" class="btn btn-sm btn-outline-danger">Löschen</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
