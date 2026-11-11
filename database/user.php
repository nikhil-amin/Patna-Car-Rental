<?php
declare(strict_types=1);
/**
 * Account administration from the command line.
 *   php database/user.php list
 *   php database/user.php set-password <customer|admin> <username>
 *   php database/user.php create <customer|admin> <username> "<Full name>" <email> <phone> "<address>"
 * Password is prompted (hidden where the OS allows). For scripts: USER_PASSWORD=... php database/user.php ...
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/validation.php';

function fail(string $m): never { fwrite(STDERR, "Error: $m\n"); exit(1); }
function role_table(string $role): array {
    return match ($role) {
        'customer' => ['customers', 'customer'],
        'admin'    => ['clients', 'client'],
        default    => fail("role must be 'customer' or 'admin'"),
    };
}
function ask_password(): string {
    if (($p = getenv('USER_PASSWORD')) !== false && $p !== '') { return $p; }
    $hide = PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec');
    for ($i = 0; $i < 2; $i++) {
        echo $i ? 'Repeat password: ' : 'New password: ';
        if ($hide) { shell_exec('stty -echo 2>/dev/null'); }
        $v = rtrim((string) fgets(STDIN), "\r\n");
        if ($hide) { shell_exec('stty echo 2>/dev/null'); echo "\n"; }
        $vals[] = $v;
    }
    if (!$hide) { echo "(note: input is visible on this system)\n"; }
    if ($vals[0] !== $vals[1]) { fail('passwords do not match'); }
    return $vals[0];
}
function check_password(string $pw): void {
    [$err] = validate(['p' => $pw], ['p' => 'required|password']);
    if ($err) { fail($err['p']); }
}

$cmd = $argv[1] ?? 'help';
switch ($cmd) {
    case 'list':
        foreach (['customers' => 'customer', 'clients' => 'client'] as $t => $pre) {
            echo strtoupper($t) . "\n";
            foreach (db_all("SELECT {$pre}_username u, {$pre}_name n, {$pre}_email e FROM $t ORDER BY 1") as $r) {
                printf("  %-20s %-25s %s\n", $r['u'], $r['n'], $r['e']);
            }
        }
        break;
    case 'set-password':
        [$table, $pre] = role_table($argv[2] ?? '');
        $user = $argv[3] ?? fail('username required');
        if (!db_value("SELECT COUNT(*) FROM $table WHERE {$pre}_username = ?", [$user])) { fail("no such $argv[2]: $user"); }
        $pw = ask_password(); check_password($pw);
        db_exec("UPDATE $table SET {$pre}_password = ? WHERE {$pre}_username = ?", [password_hash($pw, PASSWORD_DEFAULT), $user]);
        db_exec('DELETE FROM login_attempts WHERE ident = ?', [$argv[2] . ':' . strtolower($user)]);
        echo "Password updated for $argv[2] '$user'.\n";
        break;
    case 'create':
        [$table, $pre] = role_table($argv[2] ?? '');
        if (count($argv) < 8) { fail('usage: create <role> <username> "<name>" <email> <phone> "<address>"'); }
        [, , , $user, $name, $email, $phone, $addr] = $argv;
        [$err, $c] = validate(['u' => $user, 'n' => $name, 'e' => $email, 'p' => $phone, 'a' => $addr],
            ['u' => 'required|username', 'n' => 'required|min:2|max:100', 'e' => 'required|email|max:100', 'p' => 'required|phone', 'a' => 'required|min:3|max:255']);
        if ($err) { fail(implode(' ', $err)); }
        if (db_value("SELECT COUNT(*) FROM $table WHERE {$pre}_username = ?", [$c['u']])) { fail('username already exists'); }
        $pw = ask_password(); check_password($pw);
        db_exec("INSERT INTO $table ({$pre}_username, {$pre}_name, {$pre}_email, {$pre}_phone, {$pre}_address, {$pre}_password) VALUES (?,?,?,?,?,?)",
            [$c['u'], $c['n'], $c['e'], $c['p'], $c['a'], password_hash($pw, PASSWORD_DEFAULT)]);
        echo "Created $argv[2] '{$c['u']}'.\n";
        break;
    default:
        echo "Usage:\n  php database/user.php list\n  php database/user.php set-password <customer|admin> <username>\n  php database/user.php create <customer|admin> <username> \"<Full name>\" <email> <phone> \"<address>\"\n";
}
