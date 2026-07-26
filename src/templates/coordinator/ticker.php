<?php
// src/templates/coordinator/ticker.php — coordinator ticker list
// Variables: $tickers (array)
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($tickers) ?> Ticker</span>
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
    <a href="/coordinator/ticker/<?= (int)$t['id'] ?>"
       class="list-group-item list-group-item-action py-3 text-decoration-none">
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
