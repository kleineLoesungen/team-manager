<?php
// src/coordinator/resources_handler.php — GET /coordinator/resources — Auslastung der Ressourcen
// Gleiche Seite wie für Mitglieder (src/templates/components/resource_usage.php).

declare(strict_types=1);

require_coordinator();
require_once ROOT_PATH . '/src/db/resources.php';
require_once ROOT_PATH . '/src/templates/components/resource_usage.php';

$page = resource_usage_page_data(get_db());

require ROOT_PATH . '/src/templates/coordinator/layout.php';
render_coach_page('Ressourcen', 'contents', fn() => render_resource_usage($page, '/coordinator/resources'));
