<?php
// src/templates/login.php — Login form template
// Included by render_login_page() in layout.php
// Variables available: $error (string), $message (string)
?>
<div class="d-flex justify-content-center align-items-center" style="min-height: calc(100vh - 56px);">
    <div class="card shadow" style="width: 100%; max-width: 400px; margin: 1rem;">
        <div class="card-body p-4">
            <h1 class="h4 fw-semibold mb-4 text-center">Anmelden</h1>

            <?php if ($message): ?>
            <div class="alert alert-info alert-sm mb-3" role="alert">
                <?= e($message) ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger mb-3" role="alert">
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="/login" novalidate>
                <?= csrf_field() ?>
                <?php $return_to_val = e($_GET['return_to'] ?? $_POST['return_to'] ?? ''); ?>
                <?php if ($return_to_val !== ''): ?>
                <input type="hidden" name="return_to" value="<?= $return_to_val ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold" style="font-size: 0.875rem;">
                        Benutzername
                    </label>
                    <input
                        type="text"
                        class="form-control min-touch"
                        id="username"
                        name="username"
                        placeholder="Deinen Benutzernamen eingeben"
                        value="<?= e($_POST['username'] ?? '') ?>"
                        required
                        autocomplete="username"
                    >
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold" style="font-size: 0.875rem;">
                        Passwort
                    </label>
                    <input
                        type="password"
                        class="form-control min-touch"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="btn btn-primary w-100 min-touch fw-semibold">
                    Anmelden
                </button>
            </form>
        </div>
    </div><!-- /card -->

    <!-- Public ticker access (TICKER-06) -->
    <div class="mt-3 text-center">
        <p class="text-muted small mb-1">Öffentliche Ticker</p>
        <a href="/ticker" class="text-muted small">
            <i class="bi bi-megaphone me-1"></i>Ticker-Übersicht anzeigen
        </a>
        <p class="text-muted" style="font-size: 0.75rem; margin-top: 0.25rem;">Verfolge Live-Events ohne Anmeldung</p>
    </div>
</div><!-- /outer d-flex -->
