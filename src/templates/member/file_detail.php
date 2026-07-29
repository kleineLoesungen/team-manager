<?php
// src/templates/member/file_detail.php — member file view (+ edit if public)
// Variables: $file (array)
?>
<?php
$_share_url  = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST']
             . strtok($_SERVER['REQUEST_URI'], '?');
$_share_text = '[' . ($_SESSION['team_name'] ?? 'Team') . '] '
             . ($file['name'] ?? '')
             . ' - ' . $_share_url;
?>

<div class="mb-3 d-flex gap-2 flex-wrap">
    <a id="back-to-lists" href="/member/lists" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zur Übersicht
    </a>
    <script>(function(){var s=sessionStorage.getItem('member_lists_url');if(s)document.getElementById('back-to-lists').href=s;})();</script>
    <button type="button"
            class="btn btn-sm btn-outline-secondary min-touch"
            data-share="<?= htmlspecialchars($_share_text, ENT_QUOTES) ?>"
            onclick="shareItem(this)">
        <i class="bi bi-share me-1"></i>Teilen
    </button>
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
                <button class="nav-link active" id="preview-tab" data-bs-toggle="tab"
                        data-bs-target="#preview-pane" type="button" role="tab">
                    Anzeigen
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="edit-tab" data-bs-toggle="tab"
                        data-bs-target="#edit-pane" type="button" role="tab">
                    Bearbeiten
                </button>
            </li>
        </ul>

        <form method="POST" action="/member/files/<?= (int)$file['id'] ?>">
            <?= csrf_field() ?>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="preview-pane" role="tabpanel">
                    <div id="preview-output" class="border rounded p-3 bg-white"
                         style="min-height: 150px;"></div>
                </div>
                <div class="tab-pane fade" id="edit-pane" role="tabpanel">
                    <textarea id="content-editor" name="content" class="form-control font-monospace"
                              rows="16" style="resize: vertical;"><?= e($file['content']) ?></textarea>
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
function shareItem(btn) {
    var text = btn.getAttribute('data-share');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            btn.textContent = 'Kopiert!';
            setTimeout(function(){ btn.innerHTML = '<i class="bi bi-share me-1"></i>Teilen'; }, 2000);
        }).catch(function(){ shareFallback(text, btn); });
    } else { shareFallback(text, btn); }
}
function shareFallback(text, btn) {
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta); ta.focus(); ta.select();
    try { document.execCommand('copy'); btn.textContent = 'Kopiert!';
          setTimeout(function(){ btn.innerHTML = '<i class="bi bi-share me-1"></i>Teilen'; }, 2000); }
    catch(e) { prompt('Link kopieren:', text); }
    document.body.removeChild(ta);
}

<?php if ($file['visibility'] === 'public'): ?>
document.addEventListener('DOMContentLoaded', function() {
    var editor = document.getElementById('content-editor');
    document.getElementById('preview-output').innerHTML = marked.parse(editor.value);
});
function renderPreviewFromEditor() {
    var editor = document.getElementById('content-editor');
    document.getElementById('preview-output').innerHTML = marked.parse(editor.value);
}
document.getElementById('preview-tab') && document.getElementById('preview-tab').addEventListener('show.bs.tab', renderPreviewFromEditor);
<?php else: ?>
document.addEventListener('DOMContentLoaded', function() {
    var raw = document.getElementById('markdown-source').textContent;
    document.getElementById('rendered-content').innerHTML = marked.parse(raw);
});
<?php endif; ?>
</script>
