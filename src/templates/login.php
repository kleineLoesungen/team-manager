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

                <div class="mb-3">
                    <label for="username" class="form-label fw-semibold" style="font-size: 0.875rem;">
                        Benutzername
                    </label>
                    <input
                        type="text"
                        class="form-control min-touch"
                        id="username"
                        name="username"
                        placeholder="Geben Sie Ihren Benutzernamen ein"
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
    </div>
</div>
