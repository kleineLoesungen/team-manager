<?php
// src/templates/coordinator/logo.php
// Variables: $error (string), $success (bool), $current_logo (string path or '')
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success">Logo gespeichert.</div>
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
