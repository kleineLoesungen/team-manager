<?php
// src/templates/public/ticker_overview.php
// Variables: $tickers (array), $app_title (string), $team (array)
$active_tickers = array_filter($tickers, fn($t) => $t['status'] === 'active');
$closed_tickers = array_filter($tickers, fn($t) => $t['status'] === 'closed');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Öffentliche Ticker – <?= e($app_title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 fw-semibold mb-0">Öffentliche Ticker</h1>
        <a href="/login" class="btn btn-outline-primary btn-sm">Anmelden</a>
    </div>

    <?php if (empty($tickers)): ?>
    <div class="text-center py-5">
        <p class="h5 text-muted">Keine aktiven Ticker</p>
        <p class="text-muted">Es sind derzeit keine Live-Ticker aktiv.</p>
    </div>
    <?php else: ?>

    <?php if (!empty($active_tickers)): ?>
    <h4 class="fw-semibold mb-3">Aktive Ticker</h4>
    <div class="row row-cols-1 row-cols-md-2 g-3 mb-5">
        <?php foreach ($active_tickers as $t): ?>
        <div class="col">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">
                            <a href="/ticker/<?= (int)$t['id'] ?>" class="text-decoration-none">
                                <?= e($t['name']) ?>
                            </a>
                        </h5>
                        <span class="badge bg-success ms-2 flex-shrink-0">Aktiv</span>
                    </div>
                    <?php if ($t['description']): ?>
                    <p class="card-text text-muted small"><?= e($t['description']) ?></p>
                    <?php endif; ?>
                    <p class="card-text text-muted small mb-0">
                        <i class="bi bi-chat-dots me-1"></i><?= (int)$t['message_count'] ?> Nachrichten
                    </p>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/ticker/<?= (int)$t['id'] ?>" class="btn btn-primary btn-sm">Ticker öffnen</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($closed_tickers)): ?>
    <h4 class="fw-semibold mb-3 text-muted">Abgeschlossene Ticker</h4>
    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
        <?php foreach ($closed_tickers as $t): ?>
        <div class="col">
            <div class="card h-100 text-muted">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0 text-muted">
                            <a href="/ticker/<?= (int)$t['id'] ?>" class="text-decoration-none text-muted">
                                <?= e($t['name']) ?>
                            </a>
                        </h5>
                        <span class="badge bg-secondary ms-2 flex-shrink-0">Geschlossen</span>
                    </div>
                    <?php if ($t['description']): ?>
                    <p class="card-text small"><?= e($t['description']) ?></p>
                    <?php endif; ?>
                    <p class="card-text small mb-0">
                        <i class="bi bi-chat-dots me-1"></i><?= (int)$t['message_count'] ?> Nachrichten
                    </p>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/ticker/<?= (int)$t['id'] ?>" class="btn btn-outline-secondary btn-sm">Ansehen</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <p class="text-muted text-center small mt-4">
        <a href="/login" class="text-muted">Anmelden</a> · <?= e($app_title) ?>
    </p>
</div>
</body>
</html>
