<?php
// src/templates/public/ticker_detail.php
// Variables: $ticker (array), $messages (array), $app_title (string)
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($ticker['name']) ?> – <?= e($app_title) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 700px;">

    <!-- Back link -->
    <a href="/ticker" class="text-muted text-decoration-none small d-block mb-3">
        <i class="bi bi-arrow-left me-1"></i>Alle Ticker
    </a>

    <!-- Ticker header -->
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="fw-semibold mb-0"><?= e($ticker['name']) ?></h2>
            <?php if ($ticker['status'] === 'active'): ?>
                <span class="badge bg-success">Aktiv</span>
            <?php else: ?>
                <span class="badge bg-secondary">Geschlossen</span>
            <?php endif; ?>
        </div>
        <?php if ($ticker['description']): ?>
        <p class="text-muted mb-1"><?= e($ticker['description']) ?></p>
        <?php endif; ?>
        <?php if ($ticker['status'] === 'active'): ?>
        <!-- D-02: Silent auto-update hint in muted color, no countdown -->
        <p class="text-muted small mb-0">Wird automatisch aktualisiert…</p>
        <?php else: ?>
        <div class="alert alert-info py-2" role="alert">Dieser Ticker ist geschlossen.</div>
        <?php endif; ?>
    </div>

    <!-- Message feed (newest first, D-05) -->
    <?php if (empty($messages)): ?>
    <p class="text-muted text-center py-5">Noch keine Nachrichten.</p>
    <?php else: ?>
    <div id="messages">
        <?php foreach ($messages as $msg): ?>
        <div class="card mb-2 shadow-sm">
            <div class="card-body py-2 px-3">
                <strong><?= e($msg['timestamp']) ?></strong>
                <?php if ($msg['tag_id']): ?>
                <span class="badge bg-<?= e($msg['tag_color'] ?? 'secondary') ?> ms-1">
                    <?= e($msg['tag_label'] ?? '') ?>
                </span>
                <?php endif; ?>
                <p class="mb-0 mt-1"><?= e($msg['message']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p class="text-muted text-center small mt-4">
        <a href="/login" class="text-muted">Anmelden</a> · <?= e($app_title) ?>
    </p>
</div>

<?php if ($ticker['status'] === 'active'): ?>
<!-- D-01: Auto-reload every 5 seconds — only for active tickers (D-03) -->
<script>
    setTimeout(() => {
        location.reload();
    }, 5000);
</script>
<?php endif; ?>
</body>
</html>
