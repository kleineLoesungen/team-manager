<?php
// src/templates/login.php — Login form template
// Included by render_login_page() in layout.php
// Variables available: $error (string), $message (string)
?>
<div class="d-flex justify-content-center align-items-center login-wrapper">
    <div class="card shadow login-card-wrap">
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
                    <label for="username" class="form-label fw-semibold">
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
                    <label for="password" class="form-label fw-semibold">
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

            <hr class="my-3">

            <a href="/ticker" class="btn btn-outline-secondary w-100 min-touch" data-guest-enter>
                <i class="bi bi-person-walking me-2"></i>Als Gast weiter
            </a>
            <p class="text-muted text-center mb-0 mt-2 text-xs">Ticker, Termine und Ressourcen ohne Anmeldung</p>
        </div>
    </div><!-- /card -->
</div><!-- /outer d-flex -->

<script>
// Gastbereich (Issue #15): Das Gerät merkt sich „Als Gast weiter“; die App startet dann dort.
// „Anmelden“ im Gastbereich (/login?member=1) hebt das wieder auf.
(function () {
    var key = 'tm-guest';
    try {
        if (/[?&]member=1/.test(location.search)) localStorage.removeItem(key);
        else if (localStorage.getItem(key) === '1' && !document.querySelector('.alert-danger')) { location.replace('/ticker'); return; }
    } catch (e) {}
    var btn = document.querySelector('[data-guest-enter]');
    if (btn) btn.addEventListener('click', function () { try { localStorage.setItem(key, '1'); } catch (e) {} });
})();
</script>
