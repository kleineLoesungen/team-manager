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
