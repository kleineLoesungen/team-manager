<?php
// src/templates/components/partials.php — Shared UI partial functions
// Included once from src/templates/layout.php before any template body executes.
// Never add require_once calls for this file in individual templates.
declare(strict_types=1);

/**
 * Flash alert for PRG flow.
 * @param string $type    'success' | 'error'
 * @param string $message Human-readable message
 */
function render_flash(string $type, string $message): void {
    $icon = $type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle';
    $cls  = $type === 'success' ? 'alert-success' : 'alert-danger';
    ?>
    <div class="alert <?= $cls ?> d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="bi <?= $icon ?>"></i>
        <span><?= htmlspecialchars($message, ENT_QUOTES) ?></span>
    </div>
    <?php
}

/**
 * Content-level page header (below topbar, inside main content).
 * @param string      $title       Page heading text
 * @param string|null $back_url    If set, shows a back button linking here
 * @param string|null $action_html Optional HTML for right-aligned action slot (button/dropdown)
 */
function render_page_header(string $title, ?string $back_url = null, ?string $action_html = null): void {
    ?>
    <div class="d-flex align-items-center gap-3 mb-3">
        <?php if ($back_url): ?>
        <a href="<?= htmlspecialchars($back_url, ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
        <?php endif; ?>
        <h1 class="h2 mb-0 flex-grow-1"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
        <?php if ($action_html): echo $action_html; endif; ?>
    </div>
    <?php
}

/**
 * Empty state. Replaces <div class="text-center py-5"> patterns.
 * @param string      $icon        Bootstrap Icons name WITHOUT 'bi-' prefix, e.g. 'collection'
 * @param string      $heading     Short heading text
 * @param string      $body_text   Explanatory text
 * @param string|null $action_html Optional HTML for a call-to-action button (use mt-3 class)
 */
function render_empty(string $icon, string $heading, string $body_text, ?string $action_html = null): void {
    ?>
    <div class="tm-empty">
        <i class="bi bi-<?= htmlspecialchars($icon, ENT_QUOTES) ?>"></i>
        <p class="fw-semibold mb-1"><?= htmlspecialchars($heading, ENT_QUOTES) ?></p>
        <p class="text-body-secondary small mb-0"><?= htmlspecialchars($body_text, ENT_QUOTES) ?></p>
        <?php if ($action_html): echo $action_html; endif; ?>
    </div>
    <?php
}

/**
 * Semantic status badge.
 * @param string      $type  'ok' | 'warn' | 'bad' | 'dim' | 'info'
 * @param string      $label Badge text
 * @param string|null $icon  Optional Bootstrap icon class shown before the text (e.g. 'bi-clock')
 */
function render_badge(string $type, string $label, ?string $icon = null): void {
    $class = match($type) {
        'ok'   => 'badge-ok',
        'warn' => 'badge-warn',
        'bad'  => 'badge-bad',
        'dim'  => 'badge-dim',
        'info' => 'bg-primary-subtle text-primary-emphasis',
        default => 'badge-dim',
    };
    ?>
    <span class="badge <?= $class ?>"><?php if ($icon): ?><i class="bi <?= htmlspecialchars($icon, ENT_QUOTES) ?> me-1" aria-hidden="true"></i><?php endif; ?><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
    <?php
}

/**
 * Grouped list-group section header with hairline separator.
 * @param string   $label      Section label (displayed as tm-group-label)
 * @param callable $items_body Outputs list-group-item elements
 */
function render_collection_group(string $label, callable $items_body): void {
    ?>
    <p class="tm-group-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></p>
    <?php $items_body(); ?>
    <?php
}

/**
 * Card container for a form section. Replaces <div class="card shadow-sm"> patterns.
 * @param string      $heading     Card header heading text
 * @param callable    $fields_body Outputs form field elements
 * @param string|null $footer_html Optional card footer HTML
 */
function render_form_section(string $heading, callable $fields_body, ?string $footer_html = null): void {
    ?>
    <div class="card mb-3">
        <div class="card-header">
            <h2 class="mb-0"><?= htmlspecialchars($heading, ENT_QUOTES) ?></h2>
        </div>
        <div class="card-body">
            <?php $fields_body(); ?>
        </div>
        <?php if ($footer_html): ?>
        <div class="card-footer"><?= $footer_html ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Single form field row: label + input + optional hint text.
 * $input_html must use form-control or form-select WITHOUT -sm suffix.
 * @param string      $label      Field label text
 * @param string      $input_html Raw HTML for the input element
 * @param string|null $hint       Optional hint text below input
 */
function render_form_field(string $label, string $input_html, ?string $hint = null): void {
    ?>
    <div class="mb-3">
        <label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></label>
        <?= $input_html ?>
        <?php if ($hint): ?>
        <div class="form-text"><?= htmlspecialchars($hint, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Horizontal-scrolling matrix table for list detail views.
 * @param array         $columns      Array of column header labels (strings)
 * @param callable      $rows_body    Outputs <tr> elements for tbody
 * @param callable|null $footer_body  Optional: outputs <tr> elements for tfoot
 */
function render_matrix_table(array $columns, callable $rows_body, ?callable $footer_body = null): void {
    ?>
    <div class="tm-matrix">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <th><?= htmlspecialchars($col, ENT_QUOTES) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php $rows_body(); ?>
            </tbody>
            <?php if ($footer_body): ?>
            <tfoot>
                <?php $footer_body(); ?>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php
}

/**
 * Horizontal filter bar with pill links.
 * @param array  $pills    Array of ['label' => string, 'url' => string, 'active' => bool]
 * @param string $base_url Base URL for constructing pill links (informational, pills provide full URLs)
 */
function render_filter_pills(array $pills, string $base_url = ''): void {
    ?>
    <div class="tm-filters mb-3">
        <?php foreach ($pills as $pill): ?>
        <a href="<?= htmlspecialchars($pill['url'], ENT_QUOTES) ?>"
           class="btn btn-sm <?= $pill['active'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= htmlspecialchars($pill['label'], ENT_QUOTES) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Danger zone card. Replaces inline danger zone patterns.
 * @param string $action_label  Text for the trigger button (e.g. "Liste löschen")
 * @param string $description   Short explanation of the destructive action
 * @param string $form_html     Full form HTML (must contain btn-outline-danger submit button)
 */
function render_danger_zone(string $action_label, string $description, string $form_html): void {
    ?>
    <div class="card tm-danger-zone mt-4">
        <div class="card-header">
            <h3 class="card-title mb-0">Gefahrenzone</h3>
        </div>
        <div class="card-body">
            <p class="card-text small"><?= htmlspecialchars($description, ENT_QUOTES) ?></p>
            <?= $form_html ?>
        </div>
    </div>
    <?php
}

/**
 * Sticky primary action bar above the bottom nav.
 * Injects has-actionbar class on body via inline script.
 * @param string $label   Button label (Verb + Noun, e.g. "Liste speichern")
 * @param string $form_id ID of the form element this button submits
 */
function render_action_bar(string $label, string $form_id): void {
    ?>
    <div class="tm-actionbar">
        <button type="submit" form="<?= htmlspecialchars($form_id, ENT_QUOTES) ?>" class="btn btn-primary">
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
        </button>
    </div>
    <script>document.body.classList.add('has-actionbar');</script>
    <?php
}

/**
 * "App auf dem Startbildschirm" card for the profile pages.
 * Without JavaScript it shows the manual steps for iOS and Android. The script in
 * render_layout_foot() then adapts it: hidden when already running as installed app,
 * native install dialog where the browser offers one (Chrome/Android), otherwise the
 * button reveals the steps for the current platform. iOS has no install API at all.
 */
function render_install_app(): void {
    ?>
    <div class="card mt-4" data-install>
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-phone"></i>
            <span class="fw-semibold">App auf dem Startbildschirm</span>
        </div>
        <div class="card-body">
            <p class="text-body-secondary small mb-3">
                Speichere den Team Manager auf deinem Startbildschirm. Er öffnet sich dann
                wie eine App, ohne Adressleiste, und du bist mit einem Tipp in deinem Team.
            </p>
            <button type="button" class="btn btn-outline-primary min-touch" data-install-btn hidden
                    aria-expanded="false">
                <i class="bi bi-download me-2"></i>App installieren
            </button>
            <div data-install-steps="ios">
                <p class="small fw-semibold mt-3 mb-2">iPhone und iPad</p>
                <ol class="small mb-0">
                    <li>Tippe auf <i class="bi bi-box-arrow-up" aria-hidden="true"></i> <strong>Teilen</strong>
                        (Safari: unten, Chrome: oben rechts).</li>
                    <li>Wähle <strong>Zum Home-Bildschirm</strong> und tippe auf <strong>Hinzufügen</strong>.</li>
                </ol>
            </div>
            <div data-install-steps="android">
                <p class="small fw-semibold mt-3 mb-2">Android</p>
                <ol class="small mb-0">
                    <li>Tippe oben rechts auf <i class="bi bi-three-dots-vertical" aria-hidden="true"></i> <strong>Menü</strong>.</li>
                    <li>Wähle <strong>App installieren</strong> oder <strong>Zum Startbildschirm hinzufügen</strong>.</li>
                </ol>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Viewer count of a live ticker, plus the heartbeat hook for the layout script.
 * $counts null = public page: the tab is counted, but no number is shown.
 * Active ticker: "12 Zuschauer (max. 30)", updated live. Closed: "max. 30 Zuschauer".
 * @param array      $ticker Needs 'id' and 'status'
 * @param array|null $counts ['active' => int, 'max' => int] from ticker_viewers_counts()
 */
function render_ticker_viewers(array $ticker, ?array $counts): void {
    $is_active = ($ticker['status'] ?? '') === 'active';
    $ping = $is_active ? ' data-ticker-ping="' . (int)$ticker['id'] . '"' : '';
    if ($counts === null) {
        if ($is_active) echo '<span hidden' . $ping . '></span>';
        return;
    }
    if (!$is_active && $counts['max'] === 0) return;   // vor Einführung der Zählung geschlossen
    ?>
    <span class="text-muted small text-nowrap"<?= $ping ?> title="Zuschauer mit geöffnetem Ticker, Stand der letzten Minute">
        <i class="bi bi-eye me-1" aria-hidden="true"></i><?php if ($is_active): ?><span data-viewers-now><?= $counts['active'] ?></span> Zuschauer (max. <span data-viewers-max><?= $counts['max'] ?></span>)<?php else: ?>max. <?= $counts['max'] ?> Zuschauer<?php endif; ?>
    </span>
    <?php
}

/**
 * "Ticker abonnieren" — opt-in for push notifications of one running ticker.
 * The form works on its own for opting out; opting in first registers this device for push
 * (layout script: permission prompt, PushManager.subscribe, POST /push/subscribe), then
 * submits. Shows its own success message after the redirect.
 * @param string $vapid_public applicationServerKey from push_vapid()
 */
function render_ticker_push_toggle(array $ticker, string $role, bool $subscribed, string $vapid_public): void {
    if (($ticker['status'] ?? '') !== 'active') return;
    $flash = $_GET['success'] ?? '';
    if ($flash === 'notify_on')  render_flash('success', 'Du bekommst jetzt Benachrichtigungen zu diesem Ticker.');
    if ($flash === 'notify_off') render_flash('success', 'Benachrichtigungen zu diesem Ticker beendet.');
    ?>
    <form method="POST" action="/<?= $role === 'coordinator' ? 'coordinator' : 'member' ?>/ticker/<?= (int)$ticker['id'] ?>/notify"
          class="mb-4" data-push-form data-push-key="<?= htmlspecialchars($vapid_public, ENT_QUOTES) ?>"
          data-push-on="<?= $subscribed ? '1' : '0' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="on" value="<?= $subscribed ? '0' : '1' ?>">
        <button type="submit" class="btn <?= $subscribed ? 'btn-outline-secondary' : 'btn-outline-primary' ?>">
            <i class="bi <?= $subscribed ? 'bi-bell-slash' : 'bi-bell' ?> me-2" aria-hidden="true"></i><?= $subscribed ? 'Benachrichtigungen beenden' : 'Ticker abonnieren' ?>
        </button>
        <p class="form-text mb-0" data-push-hint>
            <?= $subscribed
                ? 'Du bekommst eine Nachricht zum Start und bei jedem neuen Eintrag.'
                : 'Bekomm eine Nachricht aufs Handy, wenn der Ticker startet, und bei jedem neuen Eintrag.' ?>
        </p>
    </form>
    <?php
}

/**
 * Recipient picker for notification forms: one switch per person, all on by default.
 * Posts recipients[] = user ids; the handler must intersect them with the allowed recipients.
 * @param array      $recipients   Rows with 'id', 'first_name', 'last_name'
 * @param array|null $selected_ids Ids to show as selected (after a failed POST), null = all
 */
function render_recipient_picker(array $recipients, ?array $selected_ids): void {
    if (empty($recipients)) return;
    ?>
    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-people" aria-hidden="true"></i>
            <span class="fw-semibold" id="recipients-label">Empfänger</span>
        </div>
        <div class="list-group list-group-flush" role="group" aria-labelledby="recipients-label">
            <?php foreach ($recipients as $r): $id = (int)$r['id']; ?>
            <label class="list-group-item list-group-item-action d-flex align-items-center gap-3" for="recipient-<?= $id ?>">
                <span class="flex-grow-1"><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name'], ENT_QUOTES) ?></span>
                <span class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="recipient-<?= $id ?>" name="recipients[]" value="<?= $id ?>"
                           <?= ($selected_ids === null || in_array($id, $selected_ids, true)) ? 'checked' : '' ?>>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <div class="card-footer small">Wen du abschaltest, der bekommt diese Benachrichtigung nicht.</div>
    </div>
    <?php
}

/**
 * Hint for a list's automatic visibility change (src/db/list_auto_visibility.php):
 * "Wird am Di 07.10. um 16:00 auf „Geschützt“ umgestellt." or, once done, when it happened.
 * @param array $list Needs auto_visibility, auto_visibility_hours, auto_visibility_done_at, date, time_start
 */
function render_auto_visibility_hint(array $list): void {
    require_once ROOT_PATH . '/src/db/list_auto_visibility.php';
    $text = list_auto_visibility_text_coordinator($list);
    if ($text === null) return;
    ?>
    <p class="small text-muted mb-2"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= htmlspecialchars($text, ENT_QUOTES) ?></p>
    <?php
}

/**
 * The same automatic visibility change, worded for members: what it means for them
 * ("Eintragen ist bis … möglich") instead of the coordinator's visibility labels.
 * @param array $list Needs visibility, auto_visibility, auto_visibility_hours, auto_visibility_done_at, date, time_start
 */
function render_auto_visibility_member_hint(array $list): void {
    require_once ROOT_PATH . '/src/db/list_auto_visibility.php';
    $text = list_auto_visibility_text_member($list);
    if ($text === null) return;
    ?>
    <p class="small text-muted mb-2"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= htmlspecialchars($text, ENT_QUOTES) ?></p>
    <?php
}

/**
 * One row of the content overview and the month view: list, document, event or ticker with
 * time/place icons, visibility, pending deadline and — for members — their own values as badges,
 * for coordinators the column totals.
 * @param array  $it     Row from dashboard_dated() / dashboard_undated()
 * @param string $role   'member' | 'coordinator'
 * @param array  $values Per list id: own values (dashboard_own_values()) or totals (dashboard_list_totals())
 */
function render_content_row(array $it, string $role, array $values): void {
    require_once ROOT_PATH . '/src/db/dashboard.php';
    require_once ROOT_PATH . '/src/db/list_auto_visibility.php';
    $is_coord = $role === 'coordinator';
    $base     = $is_coord ? '/coordinator' : '/member';
    $time     = fn(?string $t) => $t ? substr($t, 0, 5) : null;
    $url      = fn(array $it) => match ($it['type']) {
        'list'   => $base . '/lists/' . (int)$it['id'],
        'file'   => $base . '/files/' . (int)$it['id'],
        'ticker' => $base . '/ticker/' . (int)$it['id'],
        'event'  => $is_coord ? '/coordinator/events/' . (int)$it['id'] . '/edit' : null,
    };
    $icon = fn(array $it) => match ($it['type']) {
        'list'   => 'bi-table',
        'file'   => 'bi-file-earmark-text',
        'ticker' => 'bi-megaphone',
        'event'  => preg_match('/^bi-[a-z0-9-]+$/', (string)$it['icon']) ? $it['icon'] : 'bi-calendar-event',
    };
    // Sichtbarkeit: Koordinatoren sehen sie immer, Mitglieder nur, wenn sie nur lesen dürfen
    $vis_badge = function (array $it) use ($is_coord): void {
        if (!in_array($it['type'], ['list', 'file'], true)) return;
        if ($is_coord) {
            render_badge(match ($it['visibility']) { 'public' => 'ok', 'protected' => 'warn', default => 'dim' },
                         list_visibility_label($it['visibility']));
        } elseif ($it['visibility'] === 'protected') {
            render_badge('dim', 'Nur lesen', 'bi-lock');
        }
    };
    $href     = $url($it);
    $tag      = $href ? 'a' : 'div';
    // Steht nur wegen der Frist an diesem Tag: eigenes Datum als Kennzeichen, keine Uhrzeitzeile
    $later    = !empty($it['deadline_only']);
    $start    = $later ? null : $time($it['time_start']);
    $end      = $later ? null : $time($it['time_end']);
    $deadline = $it['type'] === 'list' ? list_auto_visibility_badge($it, $is_coord) : null;
    $own      = $it['type'] === 'list' ? ($values[(int)$it['id']] ?? []) : [];
    $reminder = $later ? list_auto_visibility_reminder($it, $is_coord) : null;
    $shown    = array_slice($own, 0, DASHBOARD_VALUES_SHOWN);
    $more     = count($own) - count($shown);
    if ($reminder): ?>
    <!-- Erinnerung: Sichtbarkeit einer später datierten Liste ändert sich an diesem Tag -->
    <a href="<?= e($href) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 tm-reminder tm-reminder--<?= $reminder['tone'] ?>">
        <i class="bi bi-alarm tm-reminder-icon" aria-hidden="true"></i>
        <span class="flex-grow-1 min-w-0">
            <span class="d-block fw-semibold"><?= e($reminder['title']) ?> <span class="text-nowrap"><?= e($reminder['time']) ?></span></span>
            <span class="d-flex align-items-center gap-2 flex-wrap small">
                <span class="text-break"><?= e($it['name']) ?></span>
                <?php render_badge('dim', dashboard_day_label($it['date']) . ($time($it['time_start']) ? ' ' . $time($it['time_start']) : ''), 'bi-calendar-event'); ?>
            </span>
        </span>
        <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
    </a>
    <?php return; endif; ?>
    <<?= $tag ?> <?= $href ? 'href="' . e($href) . '"' : '' ?> class="list-group-item <?= $href ? 'list-group-item-action' : '' ?> d-flex align-items-center gap-3">
        <i class="bi <?= e($icon($it)) ?> text-muted" aria-hidden="true"></i>
        <span class="flex-grow-1 min-w-0">
            <span class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-semibold"><?= e($it['name']) ?></span>
                <?php $vis_badge($it); ?>
                <?php if ($deadline) render_badge($deadline['type'], $deadline['label'], $deadline['icon']); ?>
            </span>
            <?php if ($start || $it['location']): ?>
            <span class="d-flex flex-wrap column-gap-3 small text-muted">
                <?php if ($start): ?><span class="text-nowrap"><i class="bi bi-clock me-1" aria-hidden="true"></i><?= e($start . ($end ? '–' . $end : '')) ?></span><?php endif; ?>
                <?php if ($it['location']): ?><span class="text-break"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($it['location']) ?></span><?php endif; ?>
            </span>
            <?php endif; ?>
            <?php if ($shown): ?>
            <span class="d-flex gap-1 flex-wrap mt-1">
                <?php foreach ($shown as $v):
                    if (!empty($v['total'])) {   // Koordinatoren: Summe der Spalte
                        render_badge('dim', $v['name'] . ' ' . $v['value'], $v['type'] === 'boolean' ? 'bi-check-lg' : null);
                    } elseif ($v['type'] === 'boolean') {
                        $v['yes'] ? render_badge('ok', $v['name'], 'bi-check-lg')
                                  : render_badge('dim', $v['name'], $v['set'] ? 'bi-x-lg' : 'bi-dash');
                    } else {
                        render_badge('dim', $v['name'] . ' ' . $v['value']);
                    }
                endforeach; ?>
                <?php if ($more > 0) render_badge('dim', '+' . $more); ?>
            </span>
            <?php endif; ?>
        </span>
        <?php if ($href): ?><i class="bi bi-chevron-right text-muted" aria-hidden="true"></i><?php endif; ?>
    </<?= $tag ?>>
    <?php
}

/**
 * Übersicht (Reiter "Inhalte"): Live, Nächste 7 Tage, Ohne Datum, Deine Werte
 * (src/db/dashboard.php). Kompakt: Symbole und Kennzeichen statt Sätzen.
 * @param array  $d    Result of dashboard_data()
 * @param string $role 'member' | 'coordinator'
 */
function render_dashboard(array $d, string $role): void {
    require_once ROOT_PATH . '/src/db/dashboard.php';
    require_once ROOT_PATH . '/src/db/list_auto_visibility.php';
    $is_coord = $role === 'coordinator';
    $base     = $is_coord ? '/coordinator' : '/member';
    $render_item = fn(array $it) => render_content_row($it, $role, $d['values']);
    ?>

    <?php if (!empty($d['live'])): ?>
    <section class="mb-4" aria-labelledby="dash-live">
        <div class="d-flex align-items-baseline justify-content-between mb-2">
            <h2 class="h3 mb-0" id="dash-live">Live</h2>
            <a href="/ticker" class="small">Alle Teams</a>
        </div>
        <div class="list-group">
            <?php foreach ($d['live'] as $t): ?>
            <a href="<?= e($t['url']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                <i class="bi bi-megaphone text-muted" aria-hidden="true"></i>
                <span class="flex-grow-1 min-w-0">
                    <span class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="fw-semibold"><?= e($t['name']) ?></span>
                        <?php render_badge('ok', 'Live'); ?>
                        <?php if ($t['team_name'] !== null) render_badge('dim', $t['team_name'], 'bi-people'); ?>
                    </span>
                    <?php if ($t['last_message'] !== null): ?>
                    <span class="d-block small text-muted text-truncate"><?= e(substr((string)$t['last_time'], 0, 5)) ?> <?= $t['last_tag'] ? e($t['last_tag']) . ' · ' : '' ?><?= e($t['last_message']) ?></span>
                    <?php endif; ?>
                </span>
                <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="mb-4" aria-labelledby="dash-next">
        <h2 class="h3 mb-0" id="dash-next">Nächste 7 Tage</h2>
        <?php if (empty($d['upcoming'])): ?>
            <?php render_empty('calendar3', 'Nichts in den nächsten 7 Tagen', 'Was später kommt, zeigt die Monatsansicht.'); ?>
        <?php else: ?>
            <?php foreach ($d['upcoming'] as $date => $items):
                render_collection_group(dashboard_day_label($date), function () use ($items, $render_item) { ?>
                <div class="list-group">
                    <?php foreach ($items as $it) $render_item($it); ?>
                </div>
            <?php });
            endforeach; ?>
        <?php endif; ?>
    </section>

    <?php if (!empty($d['undated'])): ?>
    <section class="mb-4" aria-labelledby="dash-undated">
        <h2 class="h3 mb-2" id="dash-undated">Ohne Datum</h2>
        <div class="list-group">
            <?php foreach ($d['undated'] as $it) $render_item($it); ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if (!empty($d['has_resources'])): ?>
    <section aria-labelledby="dash-resources">
        <h2 class="h3 mb-2" id="dash-resources">Ressourcen</h2>
        <?php render_link_tile($base . '/resources', 'bi-box-seam', 'Auslastung', 'Plätze, Hallen … aller Teams, ab heute'); ?>
    </section>
    <?php endif; ?>

    <?php if (!$is_coord && !empty($d['columns'])): ?>
    <section class="mb-4" aria-labelledby="dash-values">
        <div class="d-flex align-items-baseline justify-content-between mb-2">
            <h2 class="h3 mb-0" id="dash-values">Deine Werte <span class="small text-muted fw-normal">letzte 4 Wochen</span></h2>
            <a href="/member/stats" class="small">Statistik</a>
        </div>
        <div class="list-group">
            <?php foreach ($d['columns'] as $col):
                $n = (float)($d['totals'][(int)$col['id']]['4w'] ?? 0); ?>
            <div class="list-group-item d-flex align-items-center gap-2">
                <span class="flex-grow-1"><?= e($col['name']) ?></span>
                <span class="fw-semibold text-end tm-dash-total"><?= floor($n) == $n ? (int)$n : number_format($n, 2, ',', '.') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
    <?php
}

/**
 * Ticker status: "Live" from the start (date + time, Europe/Berlin), "Beendet" once closed.
 * Before the start "Geplant" — or, where the page shows no date ($with_start), when it begins.
 * Without a date an active ticker is live.
 * @param array $t Ticker row with status, event_date, start_time
 */
function render_ticker_status(array $t, bool $with_start = false): void {
    if ($t['status'] !== 'active') {
        render_badge('dim', 'Beendet');
        return;
    }
    $tz    = new DateTimeZone('Europe/Berlin');
    $time  = !empty($t['start_time']) ? substr((string)$t['start_time'], 0, 5) : null;
    $start = !empty($t['event_date']) ? new DateTimeImmutable($t['event_date'] . ' ' . ($time ?? '00:00'), $tz) : null;
    if ($start === null || $start <= new DateTimeImmutable('now', $tz)) {
        render_badge('ok', 'Live');
        return;
    }
    if (!$with_start) {
        render_badge('warn', 'Geplant', 'bi-clock');
        return;
    }
    require_once ROOT_PATH . '/src/db/dashboard.php';
    $day = dashboard_day_label($t['event_date']);
    $day = in_array($day, ['Heute', 'Morgen'], true) ? mb_strtolower($day) : $day;
    render_badge('warn', 'Beginnt ' . $day . ($time ? ' ' . $time : ''), 'bi-clock');
}

/**
 * Resource selection for lists and events: one switch per active resource (resource_ids[]),
 * chips when there are many. Renders nothing when the admin has not set up any resources.
 * Below it the check before saving: on every change of date, time, series or selection the
 * form asks /coordinator/resources/check and shows overlaps (warning only). Without
 * JavaScript $conflicts (saved state) is shown and the check happens after saving.
 * @param array   $resources resources_active()
 * @param int[]   $selected  Booked/posted resource ids
 * @param bool    $needs_date Show that only dated lists count (lists can be undated)
 * @param ?string $exclude   The item being edited, "list:12" / "event:3" (not its own conflict)
 * @param array   $conflicts resources_conflicts() of the saved item, shown until the first check
 */
function render_resource_picker(array $resources, array $selected, bool $needs_date = false,
                                ?string $exclude = null, array $conflicts = []): void {
    if (!$resources) return;
    $chips = count($resources) > RESOURCE_PICKER_SWITCH_MAX;   // viele Ressourcen: kompakte Chips statt Schalterliste
    ?>
    <fieldset class="mb-4">
        <legend class="form-label fw-semibold fs-6 mb-2">Ressourcen <span class="text-muted fw-normal">(optional)</span></legend>
        <?php if ($chips): ?>
        <div class="d-flex flex-wrap gap-2 mb-1">
            <?php foreach ($resources as $r): $rid = (int)$r['id']; ?>
            <input type="checkbox" class="btn-check" name="resource_ids[]" id="resource_<?= $rid ?>" value="<?= $rid ?>"
                   autocomplete="off" <?= in_array($rid, $selected, true) ? 'checked' : '' ?>>
            <label class="btn btn-sm btn-outline-secondary min-touch tm-chip" for="resource_<?= $rid ?>">
                <i class="bi bi-check-lg tm-chip-check" aria-hidden="true"></i><?= e($r['name']) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <?php foreach ($resources as $r): $rid = (int)$r['id']; ?>
        <div class="form-check form-switch d-flex align-items-center gap-2">
            <input class="form-check-input" type="checkbox" role="switch"
                   name="resource_ids[]" id="resource_<?= $rid ?>" value="<?= $rid ?>"
                   <?= in_array($rid, $selected, true) ? 'checked' : '' ?>>
            <label class="form-check-label mb-0" for="resource_<?= $rid ?>"><?= e($r['name']) ?></label>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <div class="form-text">
            Belegt die Ressource für Datum und Uhrzeit<?= $needs_date ? ' (nur mit Datum)' : '' ?>; ohne Uhrzeit den ganzen Tag.
            Überschneidungen mit anderen Teams werden angezeigt, aber nicht verhindert.
            <a href="<?= ($_SESSION['role'] ?? '') === 'coordinator' ? '/coordinator/resources' : '/member/resources' ?>">Auslastung ansehen</a>
        </div>
        <div class="mt-2" data-resource-check="<?= e((string)$exclude) ?>" aria-live="polite"><?php render_resource_conflicts($conflicts, $conflicts ? 'Speichern ist trotzdem möglich.' : null); ?></div>
    </fieldset>
    <script>
    // Prüfung vor dem Speichern: Ressourcen + Datum/Uhrzeit (+ Serie) an den Server, Hinweis anzeigen
    (function () {
        var box  = document.currentScript.previousElementSibling.querySelector('[data-resource-check]');
        var form = box && box.closest('form');
        if (!form) return;
        var fields = ['date', 'time_start', 'time_end', 'is_all_day', 'repeat', 'repeat_until'];
        var timer = null, seq = 0;
        function check() {
            var q = new URLSearchParams(), any = false;
            form.querySelectorAll('input[name="resource_ids[]"]:checked').forEach(function (i) { q.append('resource_ids[]', i.value); any = true; });
            fields.forEach(function (n) {
                var el = form.elements[n];
                if (!el || el.disabled) return;
                if (el.type === 'checkbox') { if (el.checked) q.set(n, '1'); } else if (el.value) q.set(n, el.value);
            });
            if (!any || !q.get('date')) { box.innerHTML = ''; return; }
            if (box.getAttribute('data-resource-check')) q.set('exclude', box.getAttribute('data-resource-check'));
            var mine = ++seq;
            fetch('/coordinator/resources/check?' + q.toString(), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.text() : ''; })
                .then(function (html) { if (mine === seq) box.innerHTML = html; })
                .catch(function () {});
        }
        function later() { clearTimeout(timer); timer = setTimeout(check, 300); }
        form.addEventListener('change', function (e) {
            if (e.target.name === 'resource_ids[]' || fields.indexOf(e.target.name) !== -1) later();
        });
        form.addEventListener('input', function (e) { if (fields.indexOf(e.target.name) !== -1) later(); });
        check();
    })();
    </script>
    <?php
}

/**
 * Warning for a list or event whose resources are also booked at the same time.
 * Long lists (a series) are cut after 8 entries ("und 3 weitere").
 * @param array $conflicts resources_conflicts() / resources_check()
 * @param ?string $note    Extra line below the list (e.g. "Speichern ist trotzdem möglich.")
 */
function render_resource_conflicts(array $conflicts, ?string $note = null): void {
    if (!$conflicts) return;
    $more      = max(0, count($conflicts) - 8);
    $conflicts = array_slice($conflicts, 0, 8);
    require_once ROOT_PATH . '/src/db/resources.php';
    require_once ROOT_PATH . '/src/db/dashboard.php';
    ?>
    <div class="alert alert-warning" role="status">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>Ressource zur gleichen Zeit belegt</div>
        <ul class="mb-0 ps-3 small">
            <?php foreach ($conflicts as $c): ?>
            <li>
                <span class="fw-semibold"><?= e($c['resource_name']) ?></span>:
                <?= e($c['team_name']) ?> · <?php if ($c['url']): ?><a href="<?= e($c['url']) ?>"><?= e($c['label']) ?></a><?php else: ?><?= e($c['label']) ?><?php endif; ?>
                · <?= e(dashboard_day_label($c['date'])) ?> <?= e(resources_slot_time($c)) ?>
            </li>
            <?php endforeach; ?>
            <?php if ($more): ?><li>und <?= $more ?> weitere</li><?php endif; ?>
        </ul>
        <?php if ($note): ?><div class="small mt-1"><?= e($note) ?></div><?php endif; ?>
    </div>
    <?php
}

/**
 * Place of a list or event: the location as a button that opens the device's maps/navigation
 * app (layout script [data-maps]: Apple Karten, Android geo:, otherwise Google Maps — also the
 * fallback without JavaScript), followed by the booked resources. Renders nothing when empty.
 * @param string[] $resource_names
 */
function render_place(?string $location, array $resource_names): void {
    $location = trim((string)$location);
    if ($location === '' && !$resource_names) return;
    ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <?php if ($location !== ''): ?>
        <a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($location)) ?>"
           data-maps="<?= e($location) ?>" target="_blank" rel="noopener"
           class="btn btn-sm btn-outline-secondary min-touch text-start" title="Im Navi öffnen">
            <i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($location) ?>
        </a>
        <?php endif; ?>
        <?php foreach ($resource_names as $rn) render_badge('dim', $rn, 'bi-box-seam'); ?>
    </div>
    <?php
}

/**
 * Link tile: icon, title, one line of explanation, chevron — leads to another page
 * (Ressourcen in der Übersicht, Ticker aller Teams, Admin-Einstellungen).
 */
function render_link_tile(string $href, string $icon, string $title, string $subtitle): void {
    ?>
    <div class="list-group mb-4">
        <a href="<?= e($href) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
            <i class="bi <?= e($icon) ?> text-muted" aria-hidden="true"></i>
            <span class="flex-grow-1 min-w-0">
                <span class="d-block fw-semibold"><?= e($title) ?></span>
                <span class="d-block small text-muted"><?= e($subtitle) ?></span>
            </span>
            <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
        </a>
    </div>
    <?php
}

/**
 * Group of link tiles at the end of a settings or profile page (Admin-Einstellungen, Profile):
 * heading, then per tile icon, title, one line of explanation, chevron. Then "Abmelden".
 * @param array $tiles list of [href, icon, title, line]; null entries are skipped
 */
function render_tile_group(string $label, array $tiles): void {
    $tiles = array_values(array_filter($tiles));
    render_collection_group($label, function () use ($tiles) { ?>
    <div class="list-group mb-4">
        <?php foreach ($tiles as [$href, $icon, $title, $line]): ?>
        <a href="<?= e($href) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
            <i class="bi <?= e($icon) ?> text-muted" aria-hidden="true"></i>
            <span class="flex-grow-1 min-w-0">
                <span class="d-block fw-semibold"><?= e($title) ?></span>
                <span class="d-block small text-muted text-truncate"><?= e($line) ?></span>
            </span>
            <i class="bi bi-chevron-right text-muted" aria-hidden="true"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php });
    ?>
    <div class="list-group">
        <a href="/logout" class="list-group-item list-group-item-action d-flex align-items-center gap-3 text-danger">
            <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            <span class="flex-grow-1 fw-semibold">Abmelden</span>
        </a>
    </div>
    <?php
}

/**
 * Share button. Opens the system share sheet where available (phones), otherwise copies the
 * link and confirms with "Link kopiert" (layout script, [data-share-url]).
 * @param string $label Button text, verb + object ("Ticker teilen")
 * @param string $path  Path to share; shared as absolute URL
 */
function render_share_button(string $label, string $path, string $title, string $text = ''): void {
    ?>
    <button type="button" class="btn btn-outline-secondary"
            data-share-url="<?= htmlspecialchars(absolute_url($path), ENT_QUOTES) ?>"
            data-share-title="<?= htmlspecialchars($title, ENT_QUOTES) ?>"
            data-share-text="<?= htmlspecialchars($text !== '' ? $text : $title, ENT_QUOTES) ?>">
        <i class="bi bi-share me-2" aria-hidden="true"></i><span data-share-label><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
    </button>
    <?php
}

