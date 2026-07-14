<?php
// config.php — Application configuration
// IMPORTANT: Never commit real credentials. Copy .env.example and fill in.

// Admin credentials (single admin — per D-02)
define('ADMIN_USERNAME', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '');

// Database
define('DB_HOST',   getenv('DB_HOST')   ?: 'localhost');
define('DB_PORT',   getenv('DB_PORT')   ?: '5432');
define('DB_NAME',   getenv('DB_NAME')   ?: 'team_manager');
define('DB_SCHEMA', getenv('DB_SCHEMA') ?: 'team_manager');
define('DB_USER',   getenv('DB_USER')   ?: 'postgres');
define('DB_PASS',   getenv('DB_PASS')   ?: '');

// Session
define('SESSION_TIMEOUT', 8 * 60 * 60); // 8 hours (per D-05)

// App
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('BASE_URL', getenv('BASE_URL') ?: '');

// Email configuration (Phase 5 — email notifications)
// Set MAIL_DRIVER=smtp to use PHPMailer+SMTP (bundled in src/lib/phpmailer/ — no Composer needed)
// Set MAIL_DRIVER=mail to use PHP's built-in mail() function
define('MAIL_DRIVER',       getenv('MAIL_DRIVER')       ?: 'smtp');
define('MAIL_HOST',         getenv('MAIL_HOST')         ?: 'mail.your-server.de');
define('MAIL_PORT',         (int)(getenv('MAIL_PORT')   ?: '587'));
define('MAIL_USERNAME',     getenv('MAIL_USERNAME')     ?: 'noreply@frickenfelden.info');
define('MAIL_PASSWORD',     getenv('MAIL_PASSWORD')     ?: 'MswVt93JhuKpfKt5');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@frickenfelden.info');
define('MAIL_FROM_NAME',    getenv('MAIL_FROM_NAME')    ?: 'Team Manager');