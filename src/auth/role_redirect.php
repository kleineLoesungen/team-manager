<?php
// src/auth/role_redirect.php — angemeldet, aber mit der "falschen" Rolle unterwegs
//
// Typischer Fall: Ein Koordinator teilt einen Link (/coordinator/lists/5), ein Mitglied öffnet
// ihn. Statt zum Login (das ergab eine Weiterleitungsschleife, weil der Login angemeldete
// Nutzer sofort zurückschickt) geht es zur passenden Seite der eigenen Rolle — sofern die
// Rolle den Inhalt sehen darf. Sonst: Seite "Kein Zugriff" mit dem Grund.
// Umgekehrt landen Koordinatoren von Mitglieder-Links auf ihrer Ansicht.
// Formulare (POST) werden nie umgeleitet, sondern abgelehnt.

declare(strict_types=1);

/** Called by require_coordinator() / require_member() when the signed-in role does not match. */
function role_mismatch(string $required_role): never {
    $role = $_SESSION['role'] ?? '';
    $path = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        role_forbidden('Diese Aktion ist nur für ' . ($required_role === 'coordinator' ? 'Koordinatoren' : 'Mitglieder') . ' möglich.');
    }

    if ($role === 'member') {
        role_member_from_coordinator_path($path);
    }
    if ($role === 'coordinator') {
        role_coordinator_from_member_path($path);
    }
    role_forbidden('Diese Seite gibt es für deine Rolle nicht.');
}

/** Member opened a coordinator URL: same content in the member area, if the member may see it. */
function role_member_from_coordinator_path(string $path): never {
    $simple = [
        '/coordinator'         => '/member/lists',
        '/coordinator/lists'   => '/member/lists',
        '/coordinator/stats'   => '/member/stats',
        '/coordinator/ticker'  => '/member/ticker',
        '/coordinator/profile' => '/member/profile',
        '/coordinator/resources' => '/member/resources',
    ];
    if (isset($simple[$path])) redirect($simple[$path]);

    // Termin (Bearbeiten-Link eines Koordinators): Ansicht für Mitglieder; private bleiben verborgen
    if (preg_match('#^/coordinator/events/(\d+)(/.*)?$#', $path, $m)) {
        redirect('/member/events/' . (int)$m[1]);
    }
    if (!preg_match('#^/coordinator/(lists|files|ticker)/(\d+)(/.*)?$#', $path, $m)) {
        role_forbidden('Diese Seite ist nur für Koordinatoren.');
    }
    [$kind, $id] = [$m[1], (int)$m[2]];
    $pdo     = get_db();
    $team_id = (int)$_SESSION['team_id'];

    set_admin_context($pdo);   // Inhalt nachschlagen, bevor klar ist, ob er zum Team gehört
    $table = ['lists' => 'lists', 'files' => 'files', 'ticker' => 'tickers'][$kind];
    $vis   = $kind === 'ticker' ? "'public'" : 'visibility';
    $stmt  = $pdo->prepare("SELECT team_id, $vis AS visibility FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $team_active = false;
    if ($row) {
        $t = $pdo->prepare("SELECT is_active FROM teams WHERE id = ?");
        $t->execute([(int)$row['team_id']]);
        $team_active = in_array($t->fetchColumn(), [true, 1, '1', 't', 'true'], true);
    }
    reset_rls_context($pdo);
    set_team_context($pdo, $team_id, 'member', (int)$_SESSION['user_id']);

    $label = ['lists' => 'Diese Liste', 'files' => 'Dieses Dokument', 'ticker' => 'Dieser Ticker'][$kind];
    if (!$row || !$team_active) {
        role_forbidden($label . ' gibt es nicht (mehr).', 404);
    }
    if ((int)$row['team_id'] !== $team_id) {
        // Ticker sind öffentlich lesbar; alles andere gehört zu einem anderen Team
        if ($kind === 'ticker') redirect('/ticker/' . $id);
        role_forbidden($label . ' gehört zu einem anderen Team.', 403, true);
    }
    if ($kind !== 'ticker' && $row['visibility'] === 'private') {
        role_forbidden($label . ' ist nur für Koordinatoren sichtbar.');
    }
    redirect('/member/' . $kind . '/' . $id);
}

/** Coordinator opened a member URL: the coordinator view of the same content. */
function role_coordinator_from_member_path(string $path): never {
    $simple = [
        '/member'         => '/coordinator/lists',
        '/member/lists'   => '/coordinator/lists',
        '/member/stats'   => '/coordinator/stats',
        '/member/ticker'  => '/coordinator/ticker',
        '/member/profile' => '/coordinator/profile',
        '/member/switch-team' => '/coordinator/switch-team',
        '/member/resources' => '/coordinator/resources',
    ];
    if (isset($simple[$path])) redirect($simple[$path]);
    if (preg_match('#^/member/events/(\d+)(/.*)?$#', $path, $m)) {
        redirect('/coordinator/events/' . (int)$m[1] . '/edit');
    }
    if (preg_match('#^/member/(lists|files|ticker)/(\d+)(/.*)?$#', $path, $m)) {
        redirect('/coordinator/' . $m[1] . '/' . (int)$m[2]);   // dort greift die Prüfung der Koordinatoren
    }
    role_forbidden('Diese Seite ist nur für Mitglieder.');
}

/** "Kein Zugriff" page in the user's own layout, with a way back (and to switch team). */
function role_forbidden(string $reason, int $status = 403, bool $offer_switch = false): never {
    http_response_code($status);
    $role = ($_SESSION['role'] ?? '') === 'coordinator' ? 'coordinator' : 'member';
    $home = $role === 'coordinator' ? '/coordinator/lists' : '/member/lists';
    $switch = null;
    if ($offer_switch) {
        require_once ROOT_PATH . '/src/db/team_switch.php';
        if (count(team_switch_options(get_db())) > 1) {
            $switch = $role === 'coordinator' ? '/coordinator/switch-team' : '/member/switch-team';
        }
    }
    require_once ROOT_PATH . '/src/templates/layout.php';
    render_page(['title' => 'Kein Zugriff', 'role' => $role], function () use ($reason, $home, $switch, $status) {
        render_empty($status === 404 ? 'question-circle' : 'lock',
            $status === 404 ? 'Nicht gefunden' : 'Kein Zugriff',
            $reason . ($switch ? ' Wenn du dort dabei bist, wechsle das Team.' : ''),
            '<div class="d-flex gap-2 justify-content-center flex-wrap mt-3">'
            . ($switch ? '<a href="' . $switch . '" class="btn btn-outline-secondary">Team wechseln</a>' : '')
            . '<a href="' . $home . '" class="btn btn-primary">Zur Übersicht</a></div>');
    });
}
