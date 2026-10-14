<?php
/**
 * Generate a password hash securely from standard input.
 * Does not store or display the plain-text password.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI access only.');
}

$plain = trim((string) stream_get_contents(STDIN));

if ($plain === '') {
    fwrite(STDERR, "Usage: php create_admin_hash.php\n");
    exit(1);
}

echo password_hash($plain, PASSWORD_DEFAULT), PHP_EOL;
