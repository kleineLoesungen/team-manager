<?php
// src/utils/env.php — liest eine .env-Datei in die Umgebung (ohne Composer, ohne Bibliothek)
//
// Webspace: deploy.sh lädt die lokale .env.production als .env neben config.php hoch; config.php
// liest sie hiermit. Docker: die Variablen kommen aus env_file, eine .env wird dann nicht gelesen.
//
// Format: KEY=VALUE je Zeile. Leere Zeilen und Zeilen mit # am Anfang zählen nicht. Werte mit
// Leerzeichen oder # in "…" oder '…' setzen — ohne Anführungszeichen beginnt bei " #" ein
// Kommentar. Kein ${…}-Ersetzen: ein $ (z. B. im Passwort-Hash) bleibt, wie es ist.
// Schon gesetzte Variablen werden nicht überschrieben.

declare(strict_types=1);

function env_load(string $file): void {
    if (!is_readable($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (str_starts_with($line, 'export ')) $line = ltrim(substr($line, 7));
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $m)) continue;
        [$key, $value] = [$m[1], $m[2]];
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $end   = strpos($value, $quote, 1);
            $value = $end === false ? substr($value, 1) : substr($value, 1, $end - 1);
            if ($quote === '"') $value = str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $value);
        } else {
            $value = trim((string)preg_replace('/\s+#.*$/', '', $value));   // Kommentar am Zeilenende
        }
        if (getenv($key) !== false) continue;   // Umgebung hat Vorrang
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
