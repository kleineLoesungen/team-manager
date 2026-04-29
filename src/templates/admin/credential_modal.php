<?php
// src/templates/admin/credential_modal.php — 60-second auto-close credential display
// Variables: $credential_username (string), $credential_password (string),
//            $redirect_url (string) — where to go after close/timeout

// SECURITY: Never log $credential_password. Display only in this modal.
// Per design decision: 60-second auto-close, no persistence beyond this render.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<div class="modal show d-block" id="credentialModal" tabindex="-1"
     style="background: rgba(0,0,0,0.5);">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-semibold">Neue Anmeldedaten</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    Notieren Sie diese Daten. Das Fenster schließt sich automatisch.
                </p>
                <div class="credential-block mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="fw-semibold text-muted">Benutzername</small>
                        <button class="btn btn-sm btn-outline-secondary"
                                onclick="copyToClipboard('<?= e($credential_username) ?>', this)">
                            Kopieren
                        </button>
                    </div>
                    <code id="cred-username"><?= e($credential_username) ?></code>
                </div>
                <div class="credential-block">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="fw-semibold text-muted">Passwort</small>
                        <button class="btn btn-sm btn-outline-secondary"
                                onclick="copyToClipboard('<?= e($credential_password) ?>', this)">
                            Kopieren
                        </button>
                    </div>
                    <code id="cred-password"><?= e($credential_password) ?></code>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <small id="timer-text" class="text-muted">
                    Dieses Fenster schließt sich automatisch in 60 Sekunden.
                </small>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="closeCredentialModal()">
                    Schließen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const REDIRECT_URL = <?= json_encode($redirect_url) ?>;
let seconds = 60;
const timerEl = document.getElementById('timer-text');

const interval = setInterval(() => {
    seconds--;
    timerEl.textContent = `Dieses Fenster schließt sich automatisch in ${seconds} Sekunden.`;
    if (seconds <= 0) {
        clearInterval(interval);
        window.location.href = REDIRECT_URL;
    }
}, 1000);

function closeCredentialModal() {
    clearInterval(interval);
    window.location.href = REDIRECT_URL;
}

function copyToClipboard(text, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            btn.textContent = 'Kopiert!';
            setTimeout(() => { btn.textContent = 'Kopieren'; }, 2000);
        });
    }
}
</script>
