<?php
// src/public/guest_resources_handler.php — GET /guest/resources — Belegung für Gäste
// Nur Ressourcen „Für Gäste sichtbar“; private Belegungen fehlen, Titel nur bei Einträgen
// „Für Gäste sichtbar“, sonst „Belegt“ (src/templates/components/resource_usage.php, Issue #15).

declare(strict_types=1);

require_once ROOT_PATH . '/src/db/resources.php';
require_once ROOT_PATH . '/src/templates/components/resource_usage.php';
require_once ROOT_PATH . '/src/templates/layout.php';

$page = resource_usage_page_data(get_db(), true);
render_page(['title' => 'Ressourcen', 'role' => 'public', 'active' => 'resources'],
            fn() => render_resource_usage($page, '/guest/resources'));
