<?php
// src/templates/member/ticker_list.php
// Variables: $tickers (array)
?>
<?php if (empty($tickers)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Keine Ticker verfügbar</p>
    <p class="text-muted">Du hast noch keinen Zugriff auf einen Ticker.</p>
</div>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($tickers as $t): ?>
    <a href="/member/ticker/<?= (int)$t['id'] ?>"
       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="fw-semibold"><?= e($t['name']) ?></span>
                <?php if ($t['status'] === 'active'): ?>
                    <span class="badge bg-success">Aktiv</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Geschlossen</span>
                <?php endif; ?>
            </div>
            <?php if ($t['description']): ?>
            <p class="mb-0 text-muted small"><?= e($t['description']) ?></p>
            <?php endif; ?>
        </div>
        <i class="bi bi-chevron-right text-muted"></i>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
