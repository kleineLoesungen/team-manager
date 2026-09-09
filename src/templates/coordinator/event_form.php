<?php
// src/templates/coordinator/event_form.php — shared create/edit form for events
// Variables: $event (array|null — null for create), $error (string)

$is_edit   = $event !== null;
$action    = $is_edit
    ? '/coordinator/events/' . (int)$event['id'] . '/edit'
    : '/coordinator/events/create';

$v_title      = $is_edit ? $event['title']       : '';
$v_desc       = $is_edit ? ($event['description'] ?? '') : '';
$v_icon       = $is_edit ? ($event['icon'] ?? 'bi-calendar-event') : 'bi-calendar-event';
$v_date       = $is_edit ? $event['date']         : '';
$v_all_day    = $is_edit ? (bool)$event['is_all_day'] : true;
$v_time_start = $is_edit ? (substr((string)($event['time_start'] ?? ''), 0, 5)) : '';
$v_time_end   = $is_edit ? (substr((string)($event['time_end']   ?? ''), 0, 5)) : '';
$v_location   = $is_edit ? ($event['location']    ?? '') : '';
$v_hidden     = $is_edit ? (bool)$event['is_hidden'] : true;
$v_visibility = $is_edit ? $event['visibility']   : 'protected';

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

            <!-- All-day switch -->
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="is_all_day" name="is_all_day"
                           <?= $v_all_day ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_all_day">Ganztägig</label>
                </div>
            </div>

            <!-- Time fields (hidden when all-day) -->
            <div id="time_fields" <?= $v_all_day ? 'class="d-none"' : '' ?>>
                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label">Von</label>
                        <input type="time" class="form-control" name="time_start"
                               value="<?= e($v_time_start) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Bis <span class="text-muted small">(optional)</span></label>
                        <input type="time" class="form-control" name="time_end"
                               value="<?= e($v_time_end) ?>">
                    </div>
                </div>
            </div>

            <!-- Location -->
            <div class="mb-3">
                <label class="form-label">Ort <span class="text-muted small">(optional)</span></label>
                <input type="text" class="form-control" name="location"
                       value="<?= e($v_location) ?>" maxlength="255"
                       placeholder="z. B. Sportplatz, Turnhalle …">
            </div>

            <!-- Description -->
            <div class="mb-3">
                <label class="form-label">Beschreibung <span class="text-muted small">(optional)</span></label>
                <textarea class="form-control" name="description" rows="3"
                          placeholder="Hinweise, Infos …"><?= e($v_desc) ?></textarea>
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

        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary min-touch">
            <i class="bi bi-floppy me-2"></i><?= $is_edit ? 'Speichern' : 'Termin erstellen' ?>
        </button>
        <?php if ($is_edit): ?>
        <form method="POST" action="/coordinator/events/<?= (int)$event['id'] ?>/delete"
              onsubmit="return confirm('Termin «<?= e($event['title']) ?>» löschen?')">
            <?= csrf_field() ?>
            <input type="hidden" name="_back" id="js-delete-back-url" value="/coordinator/lists">
            <button type="submit" class="btn btn-outline-danger min-touch">
                <i class="bi bi-trash me-1"></i>Löschen
            </button>
        </form>
        <?php endif; ?>
    </div>
</form>

<script>
(function () {
    var allDayChk = document.getElementById('is_all_day');
    var timeFields = document.getElementById('time_fields');
    allDayChk.addEventListener('change', function () {
        timeFields.classList.toggle('d-none', this.checked);
    });

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
        var delBack = document.getElementById('js-delete-back-url');
        if (delBack) delBack.value = listsUrl;
    }
}());
</script>
