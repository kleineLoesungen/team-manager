<?php
// src/member/resources_handler.php — GET /member/resources — Auslastung der Ressourcen
// Gleiche Seite wie für Koordinatoren (src/templates/components/resource_usage.php).

declare(strict_types=1);

require_member();
require_once ROOT_PATH . '/src/db/resources.php';
require_once ROOT_PATH . '/src/templates/components/resource_usage.php';

$page = resource_usage_page_data(get_db());

require ROOT_PATH . '/src/templates/member/layout.php';
render_member_page('Ressourcen', 'lists', fn() => render_resource_usage($page, '/member/resources'));
