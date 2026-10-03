<?php

/**
 * Shared security helpers used across admin/ and driver/ pages.
 * Included automatically by config.php.
 */

// -----------------------------------------------------------
// Safe output escaping shorthand
// -----------------------------------------------------------
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// -----------------------------------------------------------
// CSRF protection
// -----------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Prints a hidden <input> to place inside every <form method="POST">
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Call at the top of every POST handler, before touching $_POST data.
// Stops and shows an error instead of processing the request if the
// token is missing or does not match the one stored in the session.
function csrf_verify() {
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Security check failed (invalid or expired form token). Please go back and try again.');
    }
}

// -----------------------------------------------------------
// Password hashing helpers
// -----------------------------------------------------------
function hash_password($plain) {
    return password_hash($plain, PASSWORD_DEFAULT);
}

// Verifies a password against a stored value. Supports the old
// plain-text passwords that may still exist in the database from
// before hashing was added — returns true for a matching legacy
// plain-text password too, so existing accounts keep working.
// The caller should re-hash and save the password after a legacy
// match succeeds (see admin/login.php and driver/login.php).
function verify_password($plain, $stored) {
    if ($stored !== '' && (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2'))) {
        return password_verify($plain, $stored);
    }
    // Legacy plain-text fallback
    return hash_equals($stored, $plain);
}

function is_legacy_plain_password($stored) {
    return !(str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2'));
}
