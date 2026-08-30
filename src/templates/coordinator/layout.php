<?php
// src/templates/coordinator/layout.php — Thin wrapper delegating to render_page() (D-03)
// Delete this function in Phase 9 final cleanup plan (09-08).
declare(strict_types=1);
require_once dirname(__DIR__) . '/layout.php';

function render_coach_page(string $title, string $active, callable $body): void {
    render_page(['title' => $title, 'role' => 'coordinator', 'active' => $active], $body);
}
