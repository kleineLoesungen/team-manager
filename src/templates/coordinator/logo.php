<?php
// src/templates/coordinator/logo.php
// Variables: $error (string), $success (bool), $deleted (bool), $current_logo (string path or '')
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success">Logo gespeichert.</div>
<?php endif; ?>
<?php if ($deleted ?? false): ?>
<div class="alert alert-success">Logo gelöscht.</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <?php if ($current_logo): ?>
        <div class="mb-4">
            <p class="fw-semibold mb-2">Aktuelles Logo</p>
            <img src="/logo?t=<?= time() ?>" alt="Team-Logo"
                 style="max-height:96px; max-width:200px; object-fit:contain;">
        </div>
        <?php endif; ?>
        <?php if (!$current_logo && $default_logo): ?>
        <div class="mb-4">
            <p class="fw-semibold mb-1">Standard-Logo (vom Admin)</p>
            <p class="text-muted small mb-2">Dieses Logo wird angezeigt, solange dein Team kein eigenes Logo hochgeladen hat.</p>
            <img src="/logo?t=<?= time() ?>" alt="Standard-Logo"
                 style="max-height:96px; max-width:200px; object-fit:contain;">
        </div>
        <?php endif; ?>
        <?php if ($current_logo): ?>
        <form method="POST" action="/coordinator/logo" class="mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_logo">
            <button type="submit" class="btn btn-outline-danger btn-sm min-touch"
                    onclick="return confirm('Logo wirklich löschen?')">
                <i class="bi bi-trash me-1"></i>Logo löschen
            </button>
        </form>
        <?php endif; ?>
        <form method="POST" action="/coordinator/logo" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="mb-4">
                <label for="team_logo" class="form-label fw-semibold">
                    <?= $current_logo ? 'Neues Logo hochladen' : 'Logo hochladen' ?>
                </label>
                <input type="file" class="form-control" id="team_logo" name="team_logo"
                       accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" required>
                <div class="form-text">PNG, JPEG, GIF, WebP oder SVG. Max. 2 MB. Das Logo wird auch als Browser-Symbol (Favicon) angezeigt.</div>
            </div>
            <button type="submit" class="btn btn-primary min-touch">Logo speichern</button>
        </form>
    </div>
</div>
