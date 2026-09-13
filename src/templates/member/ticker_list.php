<?php
// src/templates/member/ticker_list.php
// Variables: $tickers (array), $other_tickers (array)
$other_tickers ??= [];
?>
<?php if (isset($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>

<?php if (empty($tickers)): ?>
<?php render_empty('megaphone', 'Keine Ticker verfügbar', 'Noch keine Ticker in deinem Team.'); ?>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($tickers as $t): ?>
    <a href="/member/ticker/<?= (int)$t['id'] ?>"
       class="list-group-item list-group-item-action text-decoration-none">
        <div class="d-flex justify-content-between align-items-center">
            <div class="flex-grow-1 me-2 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="fw-semibold text-body"><?= e($t['name']) ?></span>
                    <?php $t['status'] === 'active' ? render_badge('ok', 'Aktiv') : render_badge('dim', 'Geschlossen'); ?>
                </div>
                <?php if ($t['description']): ?>
                <p class="mb-0 text-muted small text-truncate"><?= e($t['description']) ?></p>
                <?php endif; ?>
            </div>
            <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($other_tickers)): ?>
<h2 class="h6 fw-semibold text-muted mt-4 mb-2">Weitere Teams</h2>
<div class="list-group mb-4">
    <?php foreach ($other_tickers as $t): ?>
    <a href="/member/ticker/<?= (int)$t['id'] ?>"
       class="list-group-item list-group-item-action text-decoration-none">
        <div class="d-flex justify-content-between align-items-center">
            <div class="flex-grow-1 me-2 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="fw-semibold text-body"><?= e($t['name']) ?></span>
                    <?php $t['status'] === 'active' ? render_badge('ok', 'Aktiv') : render_badge('dim', 'Geschlossen'); ?>
                </div>
                <p class="mb-1 text-muted small"><?= e($t['team_name']) ?></p>
                <?php if ($t['description']): ?>
                <p class="mb-0 text-muted small text-truncate"><?= e($t['description']) ?></p>
                <?php endif; ?>
            </div>
            <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
