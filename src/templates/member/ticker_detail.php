<?php
// src/templates/member/ticker_detail.php
// Variables: $ticker, $messages, $tags, $is_freigegeben, $error, $edit_message, $ticker_id
?>
<div class="mb-3">
    <?php if ($ticker['description']): ?>
    <p class="text-muted small mb-1"><?= e($ticker['description']) ?></p>
    <?php endif; ?>
    <?php if ($ticker['status'] === 'active'): ?>
    <span class="badge bg-success">Aktiv</span>
    <?php else: ?>
    <span class="badge bg-secondary">Geschlossen</span>
    <?php endif; ?>
</div>

<?php if ($ticker['status'] === 'closed'): ?>
<div class="alert alert-info" role="alert">Dieser Ticker ist geschlossen.</div>
<?php endif; ?>

<!-- Post/edit form — only if member is freigegeben (TICKER-03) -->
<?php if ($is_freigegeben): ?>
<?php if ($edit_message): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Nachricht bearbeiten</div>
    <div class="card-body">
        <form method="POST" action="/member/ticker/<?= (int)$ticker_id ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit_message">
            <input type="hidden" name="message_id" value="<?= (int)$edit_message['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Nachricht (max. 280 Zeichen)</label>
                <textarea name="message" class="form-control" rows="3" maxlength="280" required
                          oninput="updateCounter(this, 'editCharCount')"><?= e($edit_message['message']) ?></textarea>
                <small class="form-text text-muted"><span id="editCharCount"><?= mb_strlen($edit_message['message'], 'UTF-8') ?></span>/280 Zeichen</small>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Uhrzeit (HH:MM)</label>
                    <input type="time" name="timestamp" class="form-control" value="<?= e($edit_message['timestamp']) ?>" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Tag/Kategorie</label>
                    <select name="tag_id" class="form-select">
                        <option value="">Kein Tag</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?= (int)$tag['id'] ?>" <?= (int)$edit_message['tag_id'] === (int)$tag['id'] ? 'selected' : '' ?>>
                            <?= e($tag['label']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Speichern</button>
                <a href="/member/ticker/<?= (int)$ticker_id ?>" class="btn btn-outline-secondary">Abbrechen</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Nachricht posten</div>
    <div class="card-body">
        <form method="POST" action="/member/ticker/<?= (int)$ticker_id ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="post_message">
            <div class="mb-3">
                <label class="form-label">Nachricht (max. 280 Zeichen)</label>
                <textarea name="message" id="message" class="form-control" rows="3"
                          maxlength="280" required placeholder="Gib eine Nachricht ein…"
                          oninput="updateCounter(this, 'charCount')"></textarea>
                <small class="form-text text-muted"><span id="charCount">0</span>/280 Zeichen</small>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Uhrzeit (HH:MM)</label>
                    <input type="time" name="timestamp" class="form-control" value="<?= date('H:i') ?>" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Tag/Kategorie</label>
                    <select name="tag_id" class="form-select">
                        <option value="">Kein Tag</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?= (int)$tag['id'] ?>"><?= e($tag['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Posten</button>
        </form>
    </div>
</div>
<script>
function updateCounter(textarea, counterId) {
    document.getElementById(counterId).textContent = textarea.value.length;
}
document.addEventListener('DOMContentLoaded', function() {
    const ta = document.getElementById('message');
    if (ta) updateCounter(ta, 'charCount');
});
</script>
<?php endif; ?>
<?php endif; // end if $is_freigegeben ?>

<!-- Message feed (newest first, D-05) -->
<h4 class="fw-semibold mb-3">Nachrichten (<?= count($messages) ?>)</h4>
<?php if (empty($messages)): ?>
<p class="text-muted text-center py-4">Noch keine Nachrichten.</p>
<?php else: ?>
<?php foreach ($messages as $msg): ?>
<div class="card mb-2">
    <div class="card-body py-2 px-3">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong><?= e($msg['timestamp']) ?></strong>
                <?php if ($msg['tag_id']): ?>
                <span class="badge bg-<?= e($msg['tag_color'] ?? 'secondary') ?> ms-1">
                    <?= e($msg['tag_label'] ?? '') ?>
                </span>
                <?php endif; ?>
                <p class="mb-0 mt-1"><?= e($msg['message']) ?></p>
            </div>
            <?php if ($is_freigegeben): ?>
            <div class="d-flex gap-1 ms-3 flex-shrink-0">
                <a href="/member/ticker/<?= (int)$ticker_id ?>?edit_message_id=<?= (int)$msg['id'] ?>"
                   class="btn btn-outline-secondary btn-sm">Bearbeiten</a>
                <form method="POST" action="/member/ticker/<?= (int)$ticker_id ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="<?= (int)$msg['id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm"
                            onclick="return confirm('Nachricht löschen?')">Löschen</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
