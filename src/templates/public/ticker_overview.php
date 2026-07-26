<?php
// src/templates/public/ticker_overview.php
// Variables: $teams_with_tickers (array of {team, tickers[]}), $app_title (string)
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Live-Ticker – <?= e($app_title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .collapse-icon { transition: transform .2s; }
        .collapsed .collapse-icon { transform: rotate(0deg); }
        [aria-expanded="true"] .collapse-icon { transform: rotate(90deg); }
    </style>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 720px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-semibold mb-0"><i class="bi bi-megaphone me-2"></i>Live-Ticker</h1>
        <a href="/login" class="btn btn-outline-primary btn-sm">Anmelden</a>
    </div>

    <?php if (empty($teams_with_tickers)): ?>
    <div class="text-center py-5">
        <p class="h5 text-muted">Keine Ticker vorhanden</p>
        <p class="text-muted">Es sind derzeit keine Live-Ticker aktiv.</p>
    </div>
    <?php else: ?>

    <?php foreach ($teams_with_tickers as $entry):
        $team    = $entry['team'];
        $tickers = $entry['tickers'];
        $active  = array_values(array_filter($tickers, fn($t) => $t['status'] === 'active'));
        $closed  = array_values(array_filter($tickers, fn($t) => $t['status'] === 'closed'));
    ?>

    <h2 class="h5 fw-semibold mb-3 border-bottom pb-2"><?= e($team['name']) ?></h2>

    <?php if (!empty($active)): ?>
    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
        <?php foreach ($active as $t): ?>
        <div class="col">
            <div class="card shadow-sm h-100 border-success border-opacity-50">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0 fs-6 fw-semibold">
                            <a href="/ticker/<?= (int)$t['id'] ?>" class="text-decoration-none stretched-link">
                                <?= e($t['name']) ?>
                            </a>
                        </h5>
                        <span class="badge bg-success ms-2 flex-shrink-0">Live</span>
                    </div>
                    <?php if ($t['description']): ?>
                    <p class="card-text text-muted small mb-1"><?= e($t['description']) ?></p>
                    <?php endif; ?>
                    <p class="card-text text-muted small mb-0">
                        <?php if ($t['event_date']): ?>
                        <i class="bi bi-calendar3 me-1"></i><?= e(date('d.m.Y', strtotime($t['event_date']))) ?>
                        <?php if ($t['start_time']): ?>, <?= e(substr($t['start_time'], 0, 5)) ?> Uhr<?php endif; ?>
                         ·
                        <?php endif; ?>
                        <i class="bi bi-chat-dots me-1"></i><?= (int)$t['message_count'] ?> Nachrichten
                    </p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($closed)): ?>
    <?php $collapse_id = 'closed_' . (int)$team['id']; ?>
    <div class="mb-4">
        <button class="btn btn-link btn-sm text-muted text-decoration-none px-0 py-1"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#<?= $collapse_id ?>"
                aria-expanded="false">
            <i class="bi bi-chevron-right me-1 collapse-icon"></i>
            Abgeschlossen (<?= count($closed) ?>)
        </button>
        <div class="collapse" id="<?= $collapse_id ?>">
            <div class="list-group mt-2">
                <?php foreach ($closed as $t): ?>
                <a href="/ticker/<?= (int)$t['id'] ?>"
                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2">
                    <span class="text-muted">
                        <?= e($t['name']) ?>
                        <?php if ($t['event_date']): ?>
                        <small class="ms-2"><?= e(date('d.m.Y', strtotime($t['event_date']))) ?><?= $t['start_time'] ? ', ' . e(substr($t['start_time'], 0, 5)) . ' Uhr' : '' ?></small>
                        <?php endif; ?>
                    </span>
                    <span class="d-flex align-items-center gap-2">
                        <small class="text-muted"><?= (int)$t['message_count'] ?> <i class="bi bi-chat-dots"></i></small>
                        <span class="badge bg-secondary">Geschlossen</span>
                    </span>
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>
</html>
