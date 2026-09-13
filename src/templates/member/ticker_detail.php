<?php
// src/templates/member/ticker_detail.php
// Variables: $ticker, $messages, $tags, $is_freigegeben, $error, $edit_message, $ticker_id
?>
<?php if (isset($_GET['success'])): render_flash('success', 'Gespeichert.'); endif; ?>
<?php if (!empty($error)): render_flash('error', $error); endif; ?>

<div class="mb-3">
    <a href="/member/ticker" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück
    </a>
</div>

<?php if ($ticker['description']): ?>
<p class="text-muted small mb-3"><?= e($ticker['description']) ?></p>
<?php endif; ?>

<div class="mb-4">
    <?php $ticker['status'] === 'active' ? render_badge('ok', 'Aktiv') : render_badge('dim', 'Geschlossen'); ?>
</div>

<script>
function updateCounter(textarea, counterId) {
    document.getElementById(counterId).textContent = textarea.value.length;
}
document.addEventListener('DOMContentLoaded', function() {
    var ta = document.getElementById('message');
    if (ta) updateCounter(ta, 'charCount');
});
</script>

<!-- Post / edit form — only if member is freigegeben -->
<?php if ($is_freigegeben): ?>
<?php if ($edit_message): ?>
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-semibold">Nachricht bearbeiten</span>
    </div>
    <div class="card-body">
        <form method="POST" action="/member/ticker/<?= (int)$ticker_id ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_message">
            <input type="hidden" name="message_id" value="<?= (int)$edit_message['id'] ?>">
            <div class="mb-3">
                <textarea name="message" class="form-control" rows="3" maxlength="280" required
                          oninput="updateCounter(this,'editCharCount')"><?= e($edit_message['message']) ?></textarea>
                <div class="form-text"><span id="editCharCount"><?= mb_strlen($edit_message['message'], 'UTF-8') ?></span>/280</div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <input type="time" name="timestamp" class="form-control"
                           value="<?= e($edit_message['timestamp']) ?>" required>
                </div>
                <div class="col-6">
                    <select name="tag_id" class="form-select">
                        <option value="">Kein Tag</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?= (int)$tag['id'] ?>"
                                <?= (int)$edit_message['tag_id'] === (int)$tag['id'] ? 'selected' : '' ?>>
                            <?= e($tag['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Nachricht speichern</button>
                <a href="/member/ticker/<?= (int)$ticker_id ?>"
                   class="btn btn-outline-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card mb-4">
    <div class="card-header">
        <span class="fw-semibold">Neue Nachricht</span>
    </div>
    <div class="card-body">
        <form method="POST" action="/member/ticker/<?= (int)$ticker_id ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="post_message">
            <div class="mb-3">
                <textarea name="message" id="message" class="form-control" rows="3"
                          maxlength="280" required placeholder="Nachricht eingeben…"
                          oninput="updateCounter(this,'charCount')"></textarea>
                <div class="form-text"><span id="charCount">0</span>/280</div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <input type="time" name="timestamp" class="form-control"
                           value="<?= date('H:i') ?>" required>
                </div>
                <div class="col-6">
                    <select name="tag_id" class="form-select">
                        <option value="">Kein Tag</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?= (int)$tag['id'] ?>"><?= e($tag['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send me-1"></i>Nachricht posten
            </button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Message feed (newest first) -->
<p class="text-muted small mb-2">
    <?= count($messages) ?> <?= count($messages) === 1 ? 'Nachricht' : 'Nachrichten' ?>
</p>

<?php if (empty($messages)): ?>
<?php render_empty('chat-dots', 'Noch keine Nachrichten', 'Nachrichten erscheinen hier, sobald jemand postet.'); ?>
<?php else: ?>
<div class="list-group mb-4">
    <?php foreach ($messages as $msg): ?>
    <div class="list-group-item">
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="text-muted small fw-semibold"><?= e(substr($msg['timestamp'], 0, 5)) ?></span>
            <?php if ($msg['tag_id']): ?>
            <span class="badge bg-<?= e($msg['tag_color'] ?? 'secondary') ?>">
                <?= e($msg['tag_label'] ?? '') ?>
            </span>
            <?php endif; ?>
        </div>
        <div class="d-flex justify-content-between align-items-start gap-2">
            <p class="mb-0"><?= e($msg['message']) ?></p>
            <?php if ($is_freigegeben): ?>
            <div class="d-flex gap-1 flex-shrink-0">
                <a href="/member/ticker/<?= (int)$ticker_id ?>?edit_message_id=<?= (int)$msg['id'] ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil"></i>
                </a>
                <form method="POST" action="/member/ticker/<?= (int)$ticker_id ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('Nachricht löschen?')">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
