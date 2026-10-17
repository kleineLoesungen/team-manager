<?php
// config.php — Konfiguration der App. Steht im Repo und enthält KEINE Zugangsdaten: alle Werte
// kommen aus Umgebungsvariablen.
//
//   Webspace (FTP):  aus der .env neben dieser Datei. deploy.sh lädt dafür die lokale
//                    .env.production bei jedem Deployment als .env hoch (README, „Deployment“).
//   Docker:          aus env_file (.env.docker lokal, .env.production in Produktion);
//                    eine .env-Datei wird dann nicht gelesen.
//
// Vorlage mit allen Variablen: .env.production.example

require_once __DIR__ . '/src/utils/env.php';

// Ohne Container-Umgebung (APP_ENV fehlt): .env neben config.php lesen
if (getenv('APP_ENV') === false) {
    env_load(__DIR__ . '/.env');
}

// Admin (einziger Admin, nicht in der Datenbank)
define('ADMIN_USERNAME',      getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '');

// Datenbank
define('DB_HOST',   getenv('DB_HOST')   ?: 'localhost');
define('DB_PORT',   getenv('DB_PORT')   ?: '5432');
define('DB_NAME',   getenv('DB_NAME')   ?: 'team_manager');
define('DB_SCHEMA', getenv('DB_SCHEMA') ?: 'team_manager');
define('DB_USER',   getenv('DB_USER')   ?: 'postgres');
define('DB_PASS',   getenv('DB_PASS')   ?: '');

// Sitzungen (src/auth/session.php): Admin nach 8 Std. ohne Nutzung abgemeldet, Koordinatoren und
// Mitglieder nach 30 Tagen (gleitend). SESSION_SAVE_PATH leer = Standardverzeichnis des Servers
// statt sessions/.
define('SESSION_TIMEOUT', (int)(getenv('SESSION_TIMEOUT') ?: 8 * 60 * 60));
if (getenv('SESSION_MEMBER_TIMEOUT') !== false && getenv('SESSION_MEMBER_TIMEOUT') !== '') {
    define('SESSION_MEMBER_TIMEOUT', (int)getenv('SESSION_MEMBER_TIMEOUT'));
}
if (getenv('SESSION_SAVE_PATH') !== false) {
    define('SESSION_SAVE_PATH', (string)getenv('SESSION_SAVE_PATH'));
}

// Cronjob: stündlich POST /cron mit Header X-Cron-Token (README, „Cronjob“). Leer = Endpunkt aus.
define('CRON_TOKEN', getenv('CRON_TOKEN') ?: '');

// App
define('APP_ENV',  getenv('APP_ENV')  ?: 'production');
define('BASE_URL', getenv('BASE_URL') ?: '');

// Hinweis „Update verfügbar“ (src/utils/updates.php): Referenz-Changelog. Nicht gesetzt = GitHub
// (kleineLoesungen/team-manager, main); leer = Prüfung abgeschaltet.
if (getenv('UPDATE_CHECK_URL') !== false) {
    define('UPDATE_CHECK_URL', (string)getenv('UPDATE_CHECK_URL'));
}

// E-Mail (Benachrichtigungen): MAIL_DRIVER=smtp über PHPMailer (src/lib/phpmailer/),
// MAIL_DRIVER=mail über PHPs mail()
define('MAIL_DRIVER',       getenv('MAIL_DRIVER')       ?: 'smtp');
define('MAIL_HOST',         getenv('MAIL_HOST')         ?: '');
define('MAIL_PORT',         (int)(getenv('MAIL_PORT')   ?: '587'));
define('MAIL_USERNAME',     getenv('MAIL_USERNAME')     ?: '');
define('MAIL_PASSWORD',     getenv('MAIL_PASSWORD')     ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: '');
define('MAIL_FROM_NAME',    getenv('MAIL_FROM_NAME')    ?: 'Team Manager');
