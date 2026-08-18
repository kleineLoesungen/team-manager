<?php
// src/templates/coordinator/members.php — Merged member + player list
// Variables: $members, $player_attr_visible, $player_attr_hidden, $error, $success
declare(strict_types=1);

$active_members   = array_values(array_filter($members, fn($m) => $m['is_active']));
$inactive_members = array_values(array_filter($members, fn($m) => !$m['is_active']));
?>
<?php if ($error):   ?><div class="alert alert-danger mb-3"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success mb-3"><?= $success ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_members) ?> aktive Mitglieder</span>
    <a href="/coordinator/members/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Neues Mitglied
    </a>
</div>


<!-- Active members -->
<?php if (empty($active_members) && empty($inactive_members)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Mitglieder im Team</p>
    <p class="text-muted">Lege das erste Mitglied an.</p>
</div>
<?php else: ?>

<!-- Info mode switcher -->
<div class="d-flex gap-1 flex-wrap mb-3">
    <button class="btn btn-sm" data-mode-btn="club">Verein</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="contact">Kontakt</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="description">Beschreibung</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="attr-visible">Attribute (sichtbar)</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="attr-hidden">Attribute (verborgen)</button>
</div>

<div class="list-group mb-4">
    <?php foreach ($active_members as $m): ?>
    <?php $pid = (int)$m['player_id']; ?>
    <div class="list-group-item px-3 py-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold">
                    <?= e($m['last_name'] . ', ' . $m['first_name']) ?>
                    <?php if (empty($m['confirmed_at'])): ?>
                    <span class="badge bg-warning text-dark ms-1" title="Hat Profil noch nicht bestätigt">
                        <i class="bi bi-exclamation-triangle me-1"></i>Nicht bestätigt
                    </span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small"><code>@<?= e($m['username']) ?></code></div>

                <!-- Info panels (JS switches visibility) -->
                <div class="info-mode mt-1" data-mode="club">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">
                        <i class="bi bi-person-vcard me-1"></i><?= e($m['last_name'] . ', ' . $m['first_name']) ?>
                        <?php if (!empty($m['club_name'])): ?>
                        <span class="opacity-75 fw-normal"> · <?= e($m['club_name']) ?></span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="contact">
                    <?php
                    // Canonical email is players.email (stored as player_email)
                    $member_emails = array_filter([$m['player_email'] ?? null]);
                    ?>
                    <?php foreach ($member_emails as $addr): ?>
                    <div class="text-muted small"><i class="bi bi-envelope me-1"></i><?= e($addr) ?></div>
                    <?php endforeach; ?>
                    <?php if (!empty($m['player_phone'])): ?>
                    <div class="text-muted small"><i class="bi bi-telephone me-1"></i><a href="tel:<?= e($m['player_phone']) ?>"><?= e($m['player_phone']) ?></a></div>
                    <?php endif; ?>
                    <?php if (!empty($m['contact_name']) || !empty($m['contact_phone']) || !empty($m['contact_email'])): ?>
                    <div class="text-muted small">
                        <i class="bi bi-person-lines-fill me-1"></i>
                        <?= e($m['contact_name'] ?? '') ?>
                        <?php if (!empty($m['contact_phone'])): ?>
                        <?php if (!empty($m['contact_name'])): ?>&nbsp;·&nbsp;<?php endif; ?>
                        <a href="tel:<?= e($m['contact_phone']) ?>"><?= e($m['contact_phone']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($m['contact_email'])): ?>
                        <?php if (!empty($m['contact_name']) || !empty($m['contact_phone'])): ?>&nbsp;·&nbsp;<?php endif; ?>
                        <?= e($m['contact_email']) ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (empty($member_emails) && empty($m['player_phone']) && empty($m['contact_name']) && empty($m['contact_phone']) && empty($m['contact_email'])): ?>
                    <span class="text-muted small">Keine Kontaktdaten</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="description">
                    <?php if (!empty($m['description'])): ?>
                    <span class="text-muted small"><?= e($m['description']) ?></span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="attr-visible">
                    <?php $attrs = $player_attr_visible[$pid] ?? []; ?>
                    <?php if (!empty($attrs)): ?>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($attrs as $a): ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">
                            <?= e($a['name']) ?>: <?= e($a['value']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="attr-hidden">
                    <?php $attrs = $player_attr_hidden[$pid] ?? []; ?>
                    <?php if (!empty($attrs)): ?>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($attrs as $a): ?>
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle">
                            <?= e($a['name']) ?>: <?= e($a['value']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

            </div>
            <span class="badge bg-success flex-shrink-0">Aktiv</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/reset-password"
                  onsubmit="return confirm('Das Passwort wird zurückgesetzt und einmalig angezeigt.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-primary min-touch">
                    <i class="bi bi-key me-1"></i>Passwort
                </button>
            </form>
            <a href="/coordinator/players/<?= (int)$m['player_id'] ?>" data-save-scroll
               class="btn btn-sm btn-outline-secondary min-touch">
                <i class="bi bi-pencil me-1"></i>Profil
            </a>
            <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/deactivate"
                  onsubmit="return confirm('Mitglied deaktivieren?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger min-touch">
                    Deaktivieren
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Inactive members -->
<?php if (!empty($inactive_members)): ?>
<details class="mt-2">
    <summary class="text-muted small mb-3" style="cursor:pointer;list-style:none;">
        <i class="bi bi-chevron-right me-1"></i>Inaktive Mitglieder (<?= count($inactive_members) ?>)
    </summary>
    <div class="list-group mt-2">
        <?php foreach ($inactive_members as $m): ?>
        <div class="list-group-item px-3 py-3 opacity-75">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-muted"><?= e($m['last_name'] . ', ' . $m['first_name']) ?></div>
                    <div class="text-muted small"><code>@<?= e($m['username']) ?></code></div>
                    <div class="mt-1">
                        <a href="/coordinator/players/<?= (int)$m['player_id'] ?>"
                           class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle text-decoration-none">
                            <i class="bi bi-person-vcard me-1"></i><?= e($m['last_name'] . ', ' . $m['first_name']) ?>
                        </a>
                    </div>
                </div>
                <span class="badge bg-secondary flex-shrink-0">Inaktiv</span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/reactivate"
                      onsubmit="return confirm('Mitglied reaktivieren?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-success min-touch">
                        Reaktivieren
                    </button>
                </form>
                <a href="/coordinator/players/<?= (int)$m['player_id'] ?>" data-save-scroll
                   class="btn btn-sm btn-outline-secondary min-touch">
                    <i class="bi bi-person-vcard me-1"></i>Profil
                </a>
                <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/delete"
                      onsubmit="return confirm('<?= e('Benutzerkonto ' . $m['username'] . ' endgültig löschen? Das Spielerprofil bleibt erhalten.') ?>')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger min-touch">
                        <i class="bi bi-trash me-1"></i>Löschen
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php endif; ?>

<script>
(function () {
    var STORAGE_KEY = 'members-info-mode';
    var current = sessionStorage.getItem(STORAGE_KEY) || 'club';

    function setMode(mode) {
        current = mode;
        sessionStorage.setItem(STORAGE_KEY, mode);
        document.querySelectorAll('[data-mode-btn]').forEach(function (btn) {
            var active = btn.dataset.modeBtn === mode;
            btn.classList.toggle('btn-primary', active);
            btn.classList.toggle('btn-outline-secondary', !active);
        });
        document.querySelectorAll('.info-mode').forEach(function (el) {
            el.classList.toggle('d-none', el.dataset.mode !== mode);
        });
    }

    document.querySelectorAll('[data-mode-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () { setMode(this.dataset.modeBtn); });
    });

    setMode(current);
}());
</script>
