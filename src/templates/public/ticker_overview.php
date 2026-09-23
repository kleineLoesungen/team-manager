<?php
// src/templates/public/ticker_overview.php — Public ticker list (no auth)
// Variables: $teams_with_tickers (array of {team, tickers[]}), $app_title (string)
require_once dirname(__DIR__, 2) . '/templates/layout.php';
render_page(['title' => $app_title ?? 'Live-Ticker', 'role' => 'public'], function() use ($teams_with_tickers, $app_title) {
    ?>
    <?php if (empty($teams_with_tickers)): ?>
    <?php render_empty('megaphone', 'Keine Ticker vorhanden', 'Es sind derzeit keine Live-Ticker aktiv.'); ?>
    <?php else: ?>

    <?php foreach ($teams_with_tickers as $entry):
        $team    = $entry['team'];
        $tickers = $entry['tickers'];
        $active  = array_values(array_filter($tickers, fn($t) => $t['status'] === 'active'));
        $closed  = array_values(array_filter($tickers, fn($t) => $t['status'] === 'closed'));
    ?>

    <h2 class="h6 fw-semibold text-muted mb-2 <?= $entry !== reset($teams_with_tickers) ? 'mt-4' : '' ?>"><?= e($team['name']) ?></h2>

    <?php if (!empty($active)): ?>
    <div class="list-group mb-3">
        <?php foreach ($active as $t): ?>
        <a href="/ticker/<?= (int)$t['id'] ?>"
           class="list-group-item list-group-item-action text-decoration-none">
            <div class="d-flex justify-content-between align-items-center">
                <div class="flex-grow-1 me-2 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <span class="fw-semibold"><?= e($t['name']) ?></span>
                        <?php render_badge('ok', 'Live'); ?>
                    </div>
                    <?php if ($t['description']): ?>
                    <p class="text-muted small text-truncate mb-2"><?= e($t['description']) ?></p>
                    <?php endif; ?>
                    <p class="text-muted small">
                        <?php if ($t['event_date']): ?>
                        <i class="bi bi-calendar3 me-1"></i><?= e(date('d.m.Y', strtotime($t['event_date']))) ?>
                        <?php if ($t['start_time']): ?>, <?= e(substr($t['start_time'], 0, 5)) ?> Uhr<?php endif; ?>
                        &nbsp;·&nbsp;
                        <?php endif; ?>
                        <i class="bi bi-chat-dots me-1"></i><?= (int)$t['message_count'] ?>
                    </p>
                </div>
                <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($closed)): ?>
    <?php $collapse_id = 'closed_' . (int)$team['id']; ?>
    <div class="mb-3">
        <button class="btn btn-link btn-sm text-muted d-flex align-items-center gap-2"
                type="button" data-bs-toggle="collapse"
                data-bs-target="#<?= $collapse_id ?>" aria-expanded="false">
            <i class="bi bi-chevron-right small collapse-icon"></i>
            Abgeschlossen (<?= count($closed) ?>)
        </button>
        <div class="collapse" id="<?= $collapse_id ?>">
            <div class="list-group mt-2">
                <?php foreach ($closed as $t): ?>
                <a href="/ticker/<?= (int)$t['id'] ?>"
                   class="list-group-item list-group-item-action text-decoration-none">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1 me-2 min-w-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                                <span class="fw-semibold"><?= e($t['name']) ?></span>
                                <?php render_badge('dim', 'Geschlossen'); ?>
                            </div>
                            <p class="text-muted small">
                                <?php if ($t['event_date']): ?>
                                <i class="bi bi-calendar3 me-1"></i><?= e(date('d.m.Y', strtotime($t['event_date']))) ?>
                                <?php if ($t['start_time']): ?>, <?= e(substr($t['start_time'], 0, 5)) ?> Uhr<?php endif; ?>
                                &nbsp;·&nbsp;
                                <?php endif; ?>
                                <i class="bi bi-chat-dots me-1"></i><?= (int)$t['message_count'] ?>
                            </p>
                        </div>
                        <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endforeach; ?>
    <?php endif; ?>

    <p class="text-muted text-center small mt-4">
        <a href="/login" class="text-muted">Anmelden</a> · <?= e($app_title) ?>
    </p>
    <?php
});
