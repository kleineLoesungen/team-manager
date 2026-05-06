<?php
// src/templates/member/file_detail.php — member file view (+ edit if public)
// Variables: $file (array)
?>

<div class="mb-3">
    <a href="/member/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Übersicht
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text me-2"></i><?= e($file['name']) ?></span>
        <?php if ($file['visibility'] === 'protected'): ?>
            <span class="badge bg-secondary">Nur lesen</span>
        <?php else: ?>
            <span class="badge bg-success">Öffentlich</span>
        <?php endif; ?>
    </div>
    <div class="card-body">

        <?php if ($file['visibility'] === 'public'): ?>
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
                    Anzeigen
                </button>
            </li>
        </ul>

        <form method="POST" action="/member/files/<?= (int)$file['id'] ?>">
            <?= csrf_field() ?>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="edit-pane" role="tabpanel">
                    <textarea id="content-editor" name="content" class="form-control font-monospace"
                              rows="16" style="resize: vertical;"><?= e($file['content']) ?></textarea>
                </div>
                <div class="tab-pane fade" id="preview-pane" role="tabpanel">
                    <div id="preview-output" class="border rounded p-3 bg-white"
                         style="min-height: 150px;"></div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary min-touch">
                    <i class="bi bi-save me-1"></i>Speichern
                </button>
            </div>
        </form>

        <?php else: ?>
        <div id="rendered-content"></div>
        <div id="markdown-source" class="d-none"><?= e($file['content']) ?></div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
<?php if ($file['visibility'] === 'public'): ?>
function renderPreview() {
    var content = document.getElementById('content-editor').value;
    document.getElementById('preview-output').innerHTML = marked.parse(content);
}
<?php else: ?>
document.addEventListener('DOMContentLoaded', function() {
    var raw = document.getElementById('markdown-source').textContent;
    document.getElementById('rendered-content').innerHTML = marked.parse(raw);
});
<?php endif; ?>
</script>
