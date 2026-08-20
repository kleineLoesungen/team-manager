<?php
// src/templates/coordinator/ticker.php — coordinator ticker list
// Variables: $tickers (array), $other_tickers (array)
$other_tickers ??= [];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($tickers) ?> Ticker</span>
    <a href="/coordinator/ticker/new" class="btn btn-primary btn-sm min-touch">
        <i class="bi bi-plus-lg me-1"></i>Neuer Ticker
    </a>
</div>

<?php if (empty($tickers)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-megaphone d-block mb-2" style="font-size:2rem;"></i>
    <p class="mb-1">Noch keine Ticker erstellt</p>
    <p class="small mb-0">Leg einen Ticker an, um Events live zu dokumentieren.</p>
</div>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($tickers as $t): ?>
    <a href="/coordinator/ticker/<?= (int)$t['id'] ?>"
       class="list-group-item list-group-item-action text-decoration-none">
        <div class="d-flex justify-content-between align-items-center">
            <div class="flex-grow-1 me-2 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="fw-semibold text-body"><?= e($t['name']) ?></span>
                    <?php if ($t['status'] === 'active'): ?>
                        <span class="badge bg-success">Aktiv</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Geschlossen</span>
                    <?php endif; ?>
                </div>
                <?php if ($t['description']): ?>
                <p class="mb-1 text-muted small text-truncate"><?= e($t['description']) ?></p>
                <?php endif; ?>
                <p class="mb-0 text-muted small">
                    <?php if ($t['event_date']): ?>
                    <i class="bi bi-calendar3 me-1"></i><?= e(date('d.m.Y', strtotime($t['event_date']))) ?>
                    <?php if ($t['start_time']): ?>, <?= e(substr($t['start_time'], 0, 5)) ?> Uhr<?php endif; ?>
                    <?php else: ?>
                    <i class="bi bi-clock me-1"></i><?= e(date('d.m.Y', strtotime($t['created_at']))) ?>
                    <?php endif; ?>
                    · <i class="bi bi-chat-dots me-1"></i><?= (int)($t['message_count'] ?? 0) ?>
                </p>
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
    <a href="/coordinator/ticker/<?= (int)$t['id'] ?>"
       class="list-group-item list-group-item-action text-decoration-none">
        <div class="d-flex justify-content-between align-items-center">
            <div class="flex-grow-1 me-2 min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="fw-semibold text-body"><?= e($t['name']) ?></span>
                    <?php if ($t['status'] === 'active'): ?>
                        <span class="badge bg-success">Aktiv</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Geschlossen</span>
                    <?php endif; ?>
                </div>
                <p class="mb-1 text-muted small"><?= e($t['team_name']) ?></p>
                <?php if ($t['description']): ?>
                <p class="mb-1 text-muted small text-truncate"><?= e($t['description']) ?></p>
                <?php endif; ?>
                <p class="mb-0 text-muted small">
                    <?php if ($t['event_date']): ?>
                    <i class="bi bi-calendar3 me-1"></i><?= e(date('d.m.Y', strtotime($t['event_date']))) ?>
                    <?php if ($t['start_time']): ?>, <?= e(substr($t['start_time'], 0, 5)) ?> Uhr<?php endif; ?>
                    <?php else: ?>
                    <i class="bi bi-clock me-1"></i><?= e(date('d.m.Y', strtotime($t['created_at']))) ?>
                    <?php endif; ?>
                    · <i class="bi bi-chat-dots me-1"></i><?= (int)($t['message_count'] ?? 0) ?>
                </p>
            </div>
            <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
