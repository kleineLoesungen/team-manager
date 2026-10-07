<?php
// src/utils/updates.php — Version der Instanz und Hinweis „Update verfügbar“ (Issue #8)
//
// Die Version ist die oberste Überschrift in CHANGELOG.md (Datum, z. B. „## 2026.10.15“,
// bei mehreren am Tag „## 2026.10.15.2“). Die Instanz vergleicht ihr mitgeliefertes
// CHANGELOG.md mit dem auf GitHub (UPDATE_CHECK_URL, Standard: Rohdatei auf main) und zeigt
// dem Admin neuere Einträge. Abgerufen wird höchstens alle 24 Stunden, Ergebnis in settings
// ('update_check'). Ohne Netz oder bei Fehlern: nur ein Hinweis, die App läuft unberührt weiter.
//
// Format eines Eintrags (CHANGELOG.md):
//   ## 2026.10.15
//   - Ressourcen-Auslastung nach Wochen gruppiert
//   - Migration: 20261015_beispiel.sql      ← vor dem Deployment einzuspielen

declare(strict_types=1);

const UPDATE_CHECK_DEFAULT_URL = 'https://raw.githubusercontent.com/kleineLoesungen/team-manager/main/CHANGELOG.md';
const UPDATE_CHECK_TTL         = 86400;   // Sekunden zwischen zwei Abrufen
const UPDATE_CHECK_TIMEOUT     = 3;       // Sekunden — der Admin-Login soll nie hängen

/** URL of the reference changelog; empty = check disabled (config.php: UPDATE_CHECK_URL). */
function update_check_url(): string {
    return defined('UPDATE_CHECK_URL') ? (string)UPDATE_CHECK_URL : UPDATE_CHECK_DEFAULT_URL;
}

/**
 * Entries of a changelog, newest first: version, items (bullet points without the dash) and
 * migrations (items starting with "Migration:"). Lines that do not fit the format are ignored.
 * @return list<array{version: string, items: list<string>, migrations: list<string>}>
 */
function changelog_parse(string $md): array {
    $entries = [];
    foreach (preg_split('/\R/', $md) as $line) {
        if (preg_match('/^##\s+(\d{4}\.\d{2}\.\d{2}(?:\.\d+)?)\s*$/', $line, $m)) {
            $entries[] = ['version' => $m[1], 'items' => [], 'migrations' => []];
        } elseif ($entries && preg_match('/^\s*[-*]\s+(.+?)\s*$/', $line, $m)) {
            $i = count($entries) - 1;
            if (preg_match('/^Migration:\s*(.+)$/i', $m[1], $mm)) {
                $entries[$i]['migrations'][] = $mm[1];
            } else {
                $entries[$i]['items'][] = $m[1];
            }
        }
    }
    return $entries;
}

/** Compare two date versions (2026.10.15 < 2026.10.15.2 < 2026.10.16): <0, 0, >0. */
function version_compare_date(string $a, string $b): int {
    $pa = array_map('intval', explode('.', $a)) + [0, 0, 0, 0];
    $pb = array_map('intval', explode('.', $b)) + [0, 0, 0, 0];
    return $pa <=> $pb;
}

/** "2026.10.15" → "15.10.2026" (the extra ".2" stays: "15.10.2026 (2)"). */
function version_label(string $v): string {
    $p = explode('.', $v);
    return count($p) >= 3 ? sprintf('%s.%s.%s', $p[2], $p[1], $p[0]) . (isset($p[3]) ? ' (' . (int)$p[3] . ')' : '') : $v;
}

/** Installed version: top entry of the deployed CHANGELOG.md; null if missing or without entry. */
function app_version(): ?string {
    $file = ROOT_PATH . '/CHANGELOG.md';
    if (!is_readable($file)) return null;
    $entries = changelog_parse((string)file_get_contents($file));
    return $entries[0]['version'] ?? null;
}

/** Fetch the reference changelog (HTTPS, short timeout); null on any failure. */
function update_check_fetch(string $url): ?array {
    $body = false; $status = 0;
    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
        $ctx = stream_context_create([
            'http' => ['timeout' => UPDATE_CHECK_TIMEOUT, 'user_agent' => 'Team-Manager-Update-Check',
                       'ignore_errors' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
        }
    } elseif (function_exists('curl_init')) {   // Hoster ohne allow_url_fopen
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => UPDATE_CHECK_TIMEOUT,
                                CURLOPT_USERAGENT => 'Team-Manager-Update-Check', CURLOPT_FOLLOWLOCATION => true]);
        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    }
    if ($body === false || $status !== 200) return null;
    $entries = changelog_parse((string)$body);
    return $entries ? array_slice($entries, 0, 100) : null;
}

/**
 * Update status for the admin. Uses the cached result unless it is older than a day or $force.
 * @return array{enabled: bool, installed: ?string, latest: ?string, newer: list<array>,
 *               ok: bool, checked_at: ?int}
 */
function update_status(PDO $pdo, bool $force = false): array {
    $installed = app_version();
    $url       = update_check_url();
    if ($url === '') {
        return ['enabled' => false, 'installed' => $installed, 'latest' => null, 'newer' => [], 'ok' => false, 'checked_at' => null];
    }

    $stmt  = $pdo->prepare("SELECT value FROM settings WHERE key = 'update_check'");
    $stmt->execute();
    $cache = json_decode((string)$stmt->fetchColumn(), true);
    $fresh = is_array($cache) && ($cache['url'] ?? '') === $url && time() - (int)($cache['checked_at'] ?? 0) < UPDATE_CHECK_TTL;

    if ($force || !$fresh) {
        $entries = update_check_fetch($url);
        $cache   = [
            'url'        => $url,
            'checked_at' => time(),
            'ok'         => $entries !== null,
            // Bei einem Fehler die zuletzt bekannten Einträge behalten — nur von derselben Adresse
            'entries'    => $entries ?? ((is_array($cache) && ($cache['url'] ?? '') === $url) ? ($cache['entries'] ?? []) : []),
        ];
        $pdo->prepare(
            "INSERT INTO settings (key, value) VALUES ('update_check', ?)
             ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value"
        )->execute([json_encode($cache, JSON_UNESCAPED_UNICODE)]);
    }

    $entries = $cache['entries'] ?? [];
    $newer   = $installed === null ? [] : array_values(array_filter(
        $entries, fn($e) => version_compare_date($e['version'], $installed) > 0));
    return [
        'enabled'    => true,
        'installed'  => $installed,
        'latest'     => $entries[0]['version'] ?? null,
        'newer'      => $newer,
        'ok'         => (bool)($cache['ok'] ?? false),
        'checked_at' => isset($cache['checked_at']) ? (int)$cache['checked_at'] : null,
    ];
}
