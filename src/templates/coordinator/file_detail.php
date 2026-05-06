<?php
// src/templates/coordinator/file_detail.php — coordinator file detail/edit
// Variables: $file (array)

$date_val = $file['date'] ? (new DateTime($file['date']))->format('Y-m-d') : '';
?>

<div class="mb-3">
    <a href="/coordinator/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Übersicht
    </a>
</div>

<!-- Content editor -->
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text me-2"></i><?= e($file['name']) ?></span>
        <span class="badge <?= match($file['visibility']) {
            'public'    => 'bg-success',
            'protected' => 'bg-warning text-dark',
            'private'   => 'bg-secondary',
            default     => 'bg-secondary',
        } ?>"><?= match($file['visibility']) {
            'public'    => 'Öffentlich',
            'protected' => 'Geschützt',
            'private'   => 'Privat',
            default     => e($file['visibility']),
        } ?></span>
    </div>
    <div class="card-body">

        <ul class="nav nav-tabs mb-3" id="editorTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="edit-tab" data-bs-toggle="tab"
                        data-bs-target="#edit-pane" type="button" role="tab">
                    Bearbeiten
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="preview-tab" data-bs-toggle="tab"
                        data-bs-target="#preview-pane" type="button" role="tab"
                        onclick="renderPreview()">
                    Vorschau
                </button>
            </li>
        </ul>

        <form method="POST" action="/coordinator/files/<?= (int)$file['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="save_content">

            <div class="tab-content">
                <div class="tab-pane fade show active" id="edit-pane" role="tabpanel">
                    <textarea id="content-editor" name="content" class="form-control font-monospace"
                              rows="20" style="resize: vertical;"><?= e($file['content']) ?></textarea>
                </div>
                <div class="tab-pane fade" id="preview-pane" role="tabpanel">
                    <div id="preview-output" class="border rounded p-3 bg-white"
                         style="min-height: 200px;"></div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary min-touch">
                    <i class="bi bi-save me-1"></i>Inhalt speichern
                </button>
            </div>
        </form>

    </div>
</div>

<!-- Settings card -->
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Einstellungen</div>
    <div class="card-body">
        <form method="POST" action="/coordinator/files/<?= (int)$file['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_action" value="save_settings">

            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Name</label>
                <input type="text" id="name" name="name" class="form-control"
                       maxlength="255" required value="<?= e($file['name']) ?>">
            </div>

            <div class="mb-3">
                <label for="date" class="form-label fw-semibold">Datum <span class="text-muted fw-normal">(optional)</span></label>
                <input type="date" id="date" name="date" class="form-control"
                       value="<?= e($date_val) ?>">
            </div>

            <div class="mb-3">
                <label for="visibility" class="form-label fw-semibold">Sichtbarkeit</label>
                <select id="visibility" name="visibility" class="form-select">
                    <option value="public"    <?= $file['visibility'] === 'public'    ? 'selected' : '' ?>>Öffentlich — Mitglieder können lesen und bearbeiten</option>
                    <option value="protected" <?= $file['visibility'] === 'protected' ? 'selected' : '' ?>>Geschützt — Mitglieder können nur lesen</option>
                    <option value="private"   <?= $file['visibility'] === 'private'   ? 'selected' : '' ?>>Privat — Nur Koordinator</option>
                </select>
            </div>

            <div class="mb-4 form-check form-switch d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input" id="is_hidden" name="is_hidden"
                       style="width:3em;height:1.75em;cursor:pointer;"
                       <?= $file['is_hidden'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_hidden">Versteckt (in Übersicht einklappen)</label>
            </div>

            <button type="submit" class="btn btn-primary min-touch">
                <i class="bi bi-save me-1"></i>Einstellungen speichern
            </button>
        </form>
    </div>
</div>

<!-- Gefahrenzone -->
<div class="card border-danger mb-4">
    <div class="card-header text-danger fw-semibold">Gefahrenzone</div>
    <div class="card-body">
        <p class="text-muted mb-3">Löscht diese Datei unwiderruflich.</p>
        <form method="POST" action="/coordinator/files/<?= (int)$file['id'] ?>/delete">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-outline-danger min-touch">
                <i class="bi bi-trash me-1"></i>Datei löschen
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
function renderPreview() {
    var content = document.getElementById('content-editor').value;
    document.getElementById('preview-output').innerHTML = marked.parse(content);
}
</script>
