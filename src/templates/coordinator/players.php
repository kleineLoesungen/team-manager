<?php
// src/templates/coordinator/players.php
// Variables: $players, $unlinked_members, $linkable_players, $error
declare(strict_types=1);
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if (!empty($unlinked_members) && !empty($linkable_players)): ?>
<!-- Add link form -->
<div class="card mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-link-45deg me-1"></i>Spieler verknüpfen
    </div>
    <div class="card-body">
        <form id="link-player-form" method="POST" action="#">
            <?= csrf_field() ?>
            <input type="hidden" name="_back" value="/coordinator/players">
            <div class="mb-3">
                <label class="form-label fw-medium mb-1">Mitglied</label>
                <select name="user_id" id="link-member-sel" class="form-select" required>
                    <option value="">Mitglied wählen …</option>
                    <?php foreach ($unlinked_members as $m): ?>
                    <option value="<?= (int)$m['id'] ?>">
                        <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                        <? /* username in parens for disambiguation */ ?>
                        (<?= e($m['username']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-medium mb-1">Spieler</label>
                <select id="link-player-sel" class="form-select" required>
                    <option value="">Spieler wählen …</option>
                    <?php foreach ($linkable_players as $lp): ?>
                    <option value="<?= (int)$lp['id'] ?>">
                        <?= e($lp['last_name'] . ', ' . $lp['first_name']) ?>
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
    var form      = document.getElementById('link-player-form');
    var memberSel = document.getElementById('link-member-sel');
    var playerSel = document.getElementById('link-player-sel');
    var submitBtn = document.getElementById('link-submit-btn');

    function update() {
        var pid = playerSel.value;
        var ready = memberSel.value !== '' && pid !== '';
        submitBtn.disabled = !ready;
        form.action = ready
            ? '/coordinator/players/' + pid + '/link-user'
            : '#';
    }

    memberSel.addEventListener('change', update);
    playerSel.addEventListener('change', update);
}());
</script>
<?php elseif (!empty($unlinked_members) && empty($linkable_players)): ?>
<div class="alert alert-info mb-4">
    Alle verfügbaren Spieler sind bereits verknüpft.
    Der Admin kann weitere Spieler anlegen.
</div>
<?php endif; ?>

<!-- Linked players -->
<?php if (empty($players)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Keine verknüpften Spieler</p>
    <p class="text-muted small">Verknüpfe ein Mitglied mit einem Spieler über das Formular oben.</p>
</div>
<?php else: ?>
<p class="text-muted small mb-3"><?= count($players) ?> Spieler in diesem Team</p>
<div class="list-group">
    <?php foreach ($players as $p):
        $user_active = (bool)$p['user_active'];
    ?>
    <div class="list-group-item px-3 py-3">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= e($p['last_name'] . ', ' . $p['first_name']) ?></div>
                <?php if (!empty($p['club_name'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-building me-1"></i><?= e($p['club_name']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['phone'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-telephone me-1"></i><?= e($p['phone']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['contact_name'])): ?>
                <div class="text-muted small">
                    <i class="bi bi-person-lines-fill me-1"></i><?= e($p['contact_name']) ?>
                </div>
                <?php endif; ?>
                <div class="mt-1">
                    <span class="badge <?= $user_active ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle' ?>">
                        <i class="bi bi-person me-1"></i><?= e($p['linked_username']) ?>
                        <?= $user_active ? '' : '<span class="opacity-75">(inaktiv)</span>' ?>
                    </span>
                </div>
            </div>
            <!-- Profile link -->
            <a href="/coordinator/players/<?= (int)$p['id'] ?>"
               class="btn btn-sm btn-outline-secondary flex-shrink-0 align-self-start">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
