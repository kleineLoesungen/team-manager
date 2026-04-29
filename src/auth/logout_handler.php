<?php
// src/auth/logout_handler.php — Logout handler

declare(strict_types=1);

// Destroy session and redirect to login
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]
    );
}

session_destroy();

redirect('/login');
