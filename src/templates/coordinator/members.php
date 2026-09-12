<?php
// src/templates/coordinator/members.php
declare(strict_types=1);

$active_members   = array_values(array_filter($members, fn($m) => $m['is_active']));
$inactive_members = array_values(array_filter($members, fn($m) => !$m['is_active']));

$fmt_attr = function(array $a): string {
    $val = $a['value'];
    if (($a['data_type'] ?? 'text') === 'date' && $val !== '') {
        try { $val = (new DateTime($val))->format('d.m.Y'); } catch (\Exception $e) {}
    }
    return e($a['name']) . ': ' . e($val);
};
?>
<?php if ($error):   ?><div class="alert alert-danger mb-3"><?= e($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success mb-3"><?= e($success) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_members) ?> Mitglieder</span>
    <a href="/coordinator/members/create" class="btn btn-primary btn-sm min-touch">
        <i class="bi bi-plus-lg me-1"></i>Neues Mitglied
    </a>
</div>

<?php if (empty($active_members) && empty($inactive_members)): ?>
<?php render_empty('person-vcard', 'Noch keine Mitglieder', 'Lege das erste Mitglied an.', '<a href="/coordinator/members/create" class="btn btn-primary mt-3 min-touch"><i class="bi bi-plus-lg me-1"></i>Neues Mitglied anlegen</a>'); ?>
<?php else: ?>

<!-- Mode switcher -->
<div class="d-flex gap-1 flex-wrap mb-3">
    <button class="btn btn-sm" data-mode-btn="club">Verein</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="contact">Kontakt</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="description">Beschreibung</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="attr-visible">Attribute (sichtbar)</button>
    <button class="btn btn-sm btn-outline-secondary" data-mode-btn="attr-hidden">Attribute (verborgen)</button>
</div>

<div class="list-group mb-4">
    <?php foreach ($active_members as $m): ?>
    <?php $pid = (int)$m['member_profile_id']; ?>
    <div class="list-group-item px-3 py-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold d-flex align-items-center gap-2">
                    <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                    <?php if (empty($m['confirmed_at'])): ?>
                    <i class="bi bi-exclamation-circle text-muted small flex-shrink-0"
                       title="Profil noch nicht bestätigt"></i>
                    <?php endif; ?>
                </div>
                <div class="text-muted small">@<?= e($m['username']) ?></div>

                <div class="info-mode mt-1" data-mode="club">
                    <?php if (!empty($m['club_name'])): ?>
                    <span class="text-muted small"><i class="bi bi-building me-1"></i><?= e($m['club_name']) ?></span>
                    <?php else: ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="contact">
                    <?php $has_contact = false; ?>
                    <?php if (!empty($m['player_email'])): $has_contact = true; ?>
                    <div class="text-muted small"><i class="bi bi-envelope me-1"></i><?= e($m['player_email']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($m['player_phone'])): $has_contact = true; ?>
                    <div class="text-muted small">
                        <i class="bi bi-telephone me-1"></i>
                        <a href="tel:<?= e($m['player_phone']) ?>"><?= e($m['player_phone']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($m['contact_name']) || !empty($m['contact_phone']) || !empty($m['contact_email'])): $has_contact = true; ?>
                    <div class="text-muted small">
                        <i class="bi bi-person-lines-fill me-1"></i><?= e($m['contact_name'] ?? '') ?>
                        <?php if (!empty($m['contact_phone'])): ?>&nbsp;· <a href="tel:<?= e($m['contact_phone']) ?>"><?= e($m['contact_phone']) ?></a><?php endif; ?>
                        <?php if (!empty($m['contact_email'])): ?>&nbsp;· <?= e($m['contact_email']) ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!$has_contact): ?>
                    <span class="text-muted small">—</span>
                    <?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="description">
                    <span class="text-muted small"><?= !empty($m['description']) ? e($m['description']) : '—' ?></span>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="attr-visible">
                    <?php $attrs = $player_attr_visible[$pid] ?? []; ?>
                    <?php if (!empty($attrs)): ?>
                    <span class="text-muted small"><?= implode(' · ', array_map($fmt_attr, $attrs)) ?></span>
                    <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </div>

                <div class="info-mode mt-1 d-none" data-mode="attr-hidden">
                    <?php $attrs = $player_attr_hidden[$pid] ?? []; ?>
                    <?php if (!empty($attrs)): ?>
                    <span class="text-muted small"><?= implode(' · ', array_map($fmt_attr, $attrs)) ?></span>
                    <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/reset-password"
                  onsubmit="return confirm('Das Passwort wird zurückgesetzt und einmalig angezeigt.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-key me-1"></i>Passwort
                </button>
            </form>
            <a href="/coordinator/member-profiles/<?= (int)$m['member_profile_id'] ?>" data-save-scroll
               class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-person me-1"></i>Profil
            </a>
            <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/deactivate"
                  onsubmit="return confirm('Mitglied deaktivieren?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pause-circle me-1"></i>Deaktivieren
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($inactive_members)): ?>
<div class="mt-2">
    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
            type="button" data-bs-toggle="collapse" data-bs-target="#inactiveMembers" aria-expanded="false">
        <i class="bi bi-chevron-down small"></i>
        Inaktiv (<?= count($inactive_members) ?>)
    </button>
    <div class="collapse mt-2" id="inactiveMembers">
        <div class="list-group opacity-75">
            <?php foreach ($inactive_members as $m): ?>
            <div class="list-group-item px-3 py-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-semibold text-muted"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></div>
                        <div class="text-muted small">@<?= e($m['username']) ?></div>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/reactivate"
                          onsubmit="return confirm('Mitglied reaktivieren?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            Reaktivieren
                        </button>
                    </form>
                    <a href="/coordinator/member-profiles/<?= (int)$m['member_profile_id'] ?>" data-save-scroll
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-person me-1"></i>Profil
                    </a>
                    <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/delete"
                          onsubmit="return confirm('<?= e('Benutzerkonto ' . $m['username'] . ' endgültig löschen? Das Mitgliedsprofil bleibt erhalten.') ?>')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash me-1"></i>Löschen
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
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
            var on = btn.dataset.modeBtn === mode;
            btn.className = 'btn btn-sm ' + (on ? 'btn-primary' : 'btn-outline-secondary');
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

<p class="text-muted small mt-4">
    <i class="bi bi-exclamation-circle me-1"></i>Profil noch nicht bestätigt
</p>
