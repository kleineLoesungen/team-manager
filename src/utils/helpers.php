<?php
// src/utils/helpers.php — Shared utility functions

/**
 * HTML-escape a value for safe output. Always use this in templates.
 * Usage: <?= e($user['name']) ?>
 */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a URL and stop execution.
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/**
 * Generate a random password using a safe character set.
 * Avoids visually confusing characters (0/O, 1/l/I).
 * Default length: 12 characters.
 */
function generate_random_password(int $length = 12): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $bytes = random_bytes($length);
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[ord($bytes[$i]) % strlen($chars)];
    }
    return $password;
}

/**
 * Generate a username from first/last name initials + 4-digit random number.
 * Format: mm4821 (per D-11). Lowercase initials only.
 */
function generate_username(string $first_name, string $last_name): string {
    $initials = strtolower(mb_substr($first_name, 0, 1)) . strtolower(mb_substr($last_name, 0, 1));
    $number   = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    return $initials . $number;
}

/**
 * Generate a unique username: tries up to 10 times before giving up.
 * Requires a PDO instance to check for collisions.
 */
function generate_unique_username(PDO $pdo, string $first_name, string $last_name): string {
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $username = generate_username($first_name, $last_name);
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            return $username;
        }
    }
    // Fallback: append timestamp fragment to ensure uniqueness
    $initials = strtolower(mb_substr($first_name, 0, 1)) . strtolower(mb_substr($last_name, 0, 1));
    return $initials . substr((string)time(), -4);
}
