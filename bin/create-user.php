<?php
/**
 * Create an account from the command line — the only way to make a
 * moderator without touching SQL by hand.
 *
 *   php bin/create-user.php --email=ama@example.com --name="Ama Mensah" --role=moderator
 *
 * The password is read from the terminal with echo turned off, so it never
 * lands in your shell history. (It can also be piped in on stdin.)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';

$opts  = getopt('', ['email:', 'name:', 'role::']);
$email = trim((string) ($opts['email'] ?? ''));
$name  = trim((string) ($opts['name'] ?? ''));
$role  = (string) ($opts['role'] ?? ROLE_REPORTER);

if ($email === '' || $name === '') {
    fwrite(STDERR, "Usage: php bin/create-user.php --email=EMAIL --name=\"NAME\" [--role=reporter|moderator]\n");
    exit(2);
}
if (!in_array($role, ROLES, true)) {
    fwrite(STDERR, 'Role must be one of: ' . implode(', ', ROLES) . "\n");
    exit(2);
}

function read_secret(string $prompt): string
{
    $tty = function_exists('posix_isatty') ? posix_isatty(STDIN) : stream_isatty(STDIN);
    if ($tty) {
        fwrite(STDOUT, $prompt);
        shell_exec('stty -echo');
        $value = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    } else {
        $value = fgets(STDIN);
    }
    return rtrim((string) $value, "\r\n");
}

$password = read_secret('Password: ');
$confirm  = stream_isatty(STDIN) ? read_secret('Confirm password: ') : $password;

$errors = validate_registration($email, $name, $password, $confirm);
if ($errors) {
    foreach ($errors as $field => $message) {
        fwrite(STDERR, "{$field}: {$message}\n");
    }
    exit(1);
}

try {
    $id = register_user($email, $name, $password, $role);
} catch (DomainException) {
    fwrite(STDERR, "An account with that email already exists.\n");
    exit(1);
}

echo "Created {$role} #{$id} <" . normalise_email($email) . ">\n";
