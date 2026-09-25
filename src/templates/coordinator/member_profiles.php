<?php
// src/templates/coordinator/member_profiles.php
// Variables: $profiles, $unlinked_members, $linkable_profiles, $error
declare(strict_types=1);
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if (!empty($unlinked_members) && !empty($linkable_profiles)): ?>
<!-- Add link form -->
<div class="card mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-link-45deg me-1"></i>Mitglied verknüpfen
    </div>
    <div class="card-body">
        <form id="link-member-form" method="POST" action="#">
            <?= csrf_field() ?>
            <input type="hidden" name="_back" value="/coordinator/member-profiles">
            <div class="mb-3">
                <label class="form-label fw-medium mb-1">Mitglied</label>
                <select name="user_id" id="link-member-sel" class="form-select" required>
                    <option value="">Mitglied wählen …</option>
                    <?php foreach ($unlinked_members as $m): ?>
                    <option value="<?= (int)$m['id'] ?>">
                        <?= e($m['username']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium mb-1">Mitglied</label>
                <select id="link-profile-sel" class="form-select" required>
                    <option value="">Mitglied wählen …</option>
                    <?php foreach ($linkable_profiles as $lp): ?>
                    <option value="<?= (int)$lp['id'] ?>">
                        <?= e($lp['first_name'] . ' ' . $lp['last_name']) ?>
                        <?php if (!empty($lp['club_name'])): ?>
                        — <?= e($lp['club_name']) ?>
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" id="link-submit-btn" class="btn btn-primary" disabled>
                <i class="bi bi-link-45deg me-1"></i>Verknüpfen
            </button>
        </form>
    </div>
</div>
<script>
(function () {
    var form      = document.getElementById('link-member-form');
    var memberSel = document.getElementById('link-member-sel');
    var profileSel = document.getElementById('link-profile-sel');
    var submitBtn = document.getElementById('link-submit-btn');

    function update() {
        var pid = profileSel.value;
        var ready = memberSel.value !== '' && pid !== '';
        submitBtn.disabled = !ready;
        form.action = ready
            ? '/coordinator/member-profiles/' + pid + '/link-user'
            : '#';
    }

    memberSel.addEventListener('change', update);
    profileSel.addEventListener('change', update);
}());
</script>
<?php elseif (!empty($unlinked_members) && empty($linkable_profiles)): ?>
<div class="alert alert-info mb-4">
    Alle verfügbaren Mitgliederprofile sind bereits verknüpft.
    Der Admin kann weitere Mitglieder anlegen.
</div>
<?php endif; ?>

<!-- Linked profiles -->
<?php if (empty($profiles)): ?>
<?php render_empty('person-vcard', 'Keine verknüpften Mitglieder', 'Verknüpfe ein Mitglied über das Formular oben.'); ?>
<?php else: ?>
<p class="text-muted small mb-3"><?= count($profiles) ?> Mitglieder in diesem Team</p>
<div class="list-group">
    <?php foreach ($profiles as $p):
        $user_active = (bool)$p['user_active'];
    ?>
    <div class="list-group-item">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                <?php if (!empty($p['club_name'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-building me-1"></i><?= e($p['club_name']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['email'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-envelope me-1"></i><?= e($p['email']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['phone'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-telephone me-1"></i><?= e($p['phone']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['contact_name']) || !empty($p['contact_phone']) || !empty($p['contact_email'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-person-lines-fill me-1"></i>
                    Kontakt: <?= e($p['contact_name'] ?? '') ?>
                    <?php if (!empty($p['contact_phone'])): ?>
                    <?php if (!empty($p['contact_name'])): ?>, <?php endif; ?>
                    <?= e($p['contact_phone']) ?>
                    <?php endif; ?>
                    <?php if (!empty($p['contact_email'])): ?>
                    <?php if (!empty($p['contact_name']) || !empty($p['contact_phone'])): ?>, <?php endif; ?>
                    <?= e($p['contact_email']) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="mt-1">
                    <span class="badge <?= $user_active ? 'badge-ok' : 'badge-dim' ?>">
                        <i class="bi bi-person me-1"></i><?= e($p['linked_username']) ?>
                        <?= $user_active ? '' : '<span class="opacity-75">(inaktiv)</span>' ?>
                    </span>
                </div>
            </div>
            <!-- Profile link -->
            <a href="/coordinator/member-profiles/<?= (int)$p['id'] ?>"
               class="btn btn-sm btn-outline-secondary flex-shrink-0 align-self-start">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
