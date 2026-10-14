<?php
<<<<<<< HEAD
/** Generate a hash without storing or displaying a default password. */
$plain = trim((string) stream_get_contents(STDIN));
if ($plain === '') {
    fwrite(STDERR, "Usage: printf '%s' 'your-password' | php create_admin_hash.php\n");
    exit(1);
}
echo password_hash($plain, PASSWORD_DEFAULT), PHP_EOL;
=======

echo password_hash("shivam@123", PASSWORD_DEFAULT);

?>
>>>>>>> 21d202221995cc0ce9792faca7d37b1ccb23b3b4
