<?php
// src/templates/coordinator/event_form.php — shared create/edit form for events
// Variables: $event (array|null — null for create), $error (string),
//            $resources, $resource_selected, $resource_conflicts (src/db/resources.php)

$is_edit   = $event !== null;
$action    = $is_edit
    ? '/coordinator/events/' . (int)$event['id'] . '/edit'
    : '/coordinator/events/create';

$v_title      = $is_edit ? $event['title']       : '';
$v_desc       = $is_edit ? ($event['description'] ?? '') : '';
$v_icon       = $is_edit ? ($event['icon'] ?? 'bi-calendar-event') : 'bi-calendar-event';
$v_date       = $is_edit ? $event['date']         : '';
$v_time_start = $is_edit ? (substr((string)($event['time_start'] ?? ''), 0, 5)) : '';
$v_time_end   = $is_edit ? (substr((string)($event['time_end']   ?? ''), 0, 5)) : '';
$v_location   = $is_edit ? ($event['location']    ?? '') : '';
$v_hidden     = $is_edit ? (bool)$event['is_hidden'] : true;
$v_visibility = $is_edit ? $event['visibility']   : 'protected';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {   // nach einem Fehler: Eingaben behalten
    $v_title      = (string)($_POST['title'] ?? '');
    $v_desc       = (string)($_POST['description'] ?? '');
    $v_icon       = (string)($_POST['icon'] ?? $v_icon);
    $v_date       = (string)($_POST['date'] ?? '');
    $v_time_start = (string)($_POST['time_start'] ?? '');
    $v_time_end   = (string)($_POST['time_end'] ?? '');
    $v_location   = (string)($_POST['location'] ?? '');
    $v_hidden     = !empty($_POST['is_hidden']);
    $v_visibility = ($_POST['visibility'] ?? '') === 'private' ? 'private' : 'protected';
}

$icons = [
    'bi-calendar-event'       => 'Termin',
    'bi-people-fill'          => 'Team',
    'bi-star-fill'            => 'Veranstaltung',
    'bi-gift-fill'            => 'Geburtstag',
    'bi-chat-dots-fill'       => 'Besprechung',
    'bi-flag-fill'            => 'Wichtig',
    'bi-question-circle-fill' => 'Vorläufig',
];
?>

<?php if ($_GET['success'] ?? null): render_flash('success', 'Gespeichert.'); endif; ?>

<div class="mb-3">
    <a href="/coordinator/lists" id="js-back-btn" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<?php if ($is_edit) render_place($event['location'] ?? null, $resource_names); ?>

<form method="POST" action="<?= e($action) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="_back" id="js-back-url" value="/coordinator/lists">

    <div class="card mb-3">
        <div class="card-header fw-semibold"><?= $is_edit ? 'Termin bearbeiten' : 'Neuer Termin' ?></div>
        <div class="card-body">

            <!-- Title -->
            <div class="mb-3">
                <label class="form-label">Titel <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="title"
                       value="<?= e($v_title) ?>" maxlength="200" required
                       placeholder="z. B. Training, Heimspiel vs. …">
            </div>

            <!-- Description -->
            <div class="mb-3">
                <label class="form-label">Beschreibung <span class="text-muted small">(optional)</span></label>
                <textarea class="form-control" name="description" rows="3"
                          placeholder="Hinweise, Infos …"><?= e($v_desc) ?></textarea>
            </div>

            <!-- Icon -->
            <div class="mb-3">
                <label class="form-label">Icon</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($icons as $cls => $label): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input visually-hidden" type="radio"
                               name="icon" id="icon_<?= $cls ?>" value="<?= e($cls) ?>"
                               <?= $v_icon === $cls ? 'checked' : '' ?>>
                        <label class="form-check-label btn btn-sm <?= $v_icon === $cls ? 'btn-primary' : 'btn-outline-secondary' ?> js-icon-btn"
                               for="icon_<?= $cls ?>">
                            <i class="bi <?= e($cls) ?> me-1"></i><?= e($label) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Date -->
            <div class="mb-3">
                <label class="form-label">Datum <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="date"
                       value="<?= e($v_date) ?>" required>
            </div>

            <!-- Uhrzeit: ohne Beginn ganztägig -->
            <div class="mb-3">
                <label class="form-label">Uhrzeit <span class="text-muted small">(optional)</span></label>
                <div class="d-flex align-items-center gap-2">
                    <div>
                        <label for="event_time_start" class="form-label small text-muted mb-1">Beginn</label>
                        <input type="time" id="event_time_start" class="form-control" name="time_start" value="<?= e($v_time_start) ?>">
                    </div>
                    <div class="pt-3 text-muted">–</div>
                    <div>
                        <label for="event_time_end" class="form-label small text-muted mb-1">Ende</label>
                        <input type="time" id="event_time_end" class="form-control" name="time_end" value="<?= e($v_time_end) ?>">
                    </div>
                </div>
                <div class="form-text">Ohne Beginn ganztägig. Ohne Ende: Kalender zeigt 1 Stunde Dauer an.</div>
            </div>

            <?php if (!$is_edit): ?>
            <!-- Serie: mehrere eigenständige Termine bis zu einem Enddatum -->
            <?php render_series_fields('Termine', 'Termin anlegen'); ?>
            <?php endif; ?>

            <!-- Location -->
            <div class="mb-3">
                <label class="form-label">Ort <span class="text-muted small">(optional)</span></label>
                <input type="text" class="form-control" name="location"
                       value="<?= e($v_location) ?>" maxlength="255"
                       placeholder="z. B. Sportplatz, Turnhalle …">
            </div>

            <!-- Ressourcen (Platz, Halle …) -->
            <?php render_resource_picker($resources, $resource_selected, false, $is_edit ? 'event:' . (int)$event['id'] : null, $resource_conflicts); ?>

            <!-- Visibility -->
            <div class="mb-3">
                <label class="form-label">Sichtbarkeit</label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="visibility"
                               id="vis_protected" value="protected"
                               <?= $v_visibility === 'protected' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="vis_protected">
                            <i class="bi bi-people me-1 text-warning"></i>Mitglieder
                            <div class="text-muted small">Alle Mitglieder sehen diesen Termin</div>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="visibility"
                               id="vis_private" value="private"
                               <?= $v_visibility === 'private' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="vis_private">
                            <i class="bi bi-lock me-1 text-secondary"></i>Nur Koordinatoren
                            <div class="text-muted small">Nur für Koordinatoren sichtbar</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Hidden in list view -->
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="is_hidden" name="is_hidden" value="1"
                           <?= $v_hidden ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_hidden">In Listenansicht verstecken</label>
                </div>
            </div>

        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch" data-series-submit><?= $is_edit ? 'Termin speichern' : 'Termin anlegen' ?></button>
        <a href="/coordinator/lists" class="btn btn-outline-secondary min-touch" data-back-link>Abbrechen</a>
    </div>
</form>

<?php if ($is_edit):
    ob_start(); ?>
    <form method="POST" action="/coordinator/events/<?= (int)$event['id'] ?>/delete">
        <?= csrf_field() ?>
        <input type="hidden" name="_back" id="js-delete-back-url" value="/coordinator/lists">
        <button type="submit" class="btn btn-outline-danger min-touch">Termin löschen</button>
    </form>
    <?php render_danger_zone('Termin löschen', 'Löscht diesen Termin und seine Ressourcen-Belegung unwiderruflich. Du bestätigst auf der nächsten Seite.', ob_get_clean());
endif; ?>

<script>
(function () {
    document.querySelectorAll('.js-icon-btn').forEach(function (lbl) {
        lbl.addEventListener('click', function () {
            document.querySelectorAll('.js-icon-btn').forEach(function (b) {
                b.classList.remove('btn-primary');
                b.classList.add('btn-outline-secondary');
            });
            lbl.classList.remove('btn-outline-secondary');
            lbl.classList.add('btn-primary');
        });
    });

    // Restore lists view state (view, offset, scroll) via sessionStorage
    var listsUrl = sessionStorage.getItem('coordinator_lists_url');
    if (listsUrl) {
        document.getElementById('js-back-btn').href = listsUrl;
        document.getElementById('js-back-url').value = listsUrl;
        document.querySelectorAll('[data-back-link]').forEach(function (a) { a.href = listsUrl; });
        var delBack = document.getElementById('js-delete-back-url');
        if (delBack) delBack.value = listsUrl;
    }
}());
</script>
