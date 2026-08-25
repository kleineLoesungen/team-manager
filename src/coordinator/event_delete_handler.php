<?php
// src/coordinator/event_delete_handler.php — POST /coordinator/events/{id}/delete

declare(strict_types=1);

require_coordinator();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/coordinator/lists');

require_csrf();

$event_id = (int)($_REQUEST['event_id'] ?? 0);
if ($event_id <= 0) redirect('/coordinator/lists');

$pdo     = get_db();
$team_id = (int)$_SESSION['team_id'];
set_team_context($pdo, $team_id, 'coordinator', (int)$_SESSION['user_id']);

$pdo->prepare("DELETE FROM events WHERE id = ? AND team_id = ?")
    ->execute([$event_id, $team_id]);

$back = $_POST['_back'] ?? '';
$back = preg_match('#^/coordinator/lists(\?[^<>"\']*)?$#', $back) ? $back : '/coordinator/lists';
redirect($back);
