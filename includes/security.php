<?php
declare(strict_types=1);

const SESSION_IDLE_SECONDS = 1800;   // 30 min inactivity
const LOGIN_MAX_ATTEMPTS   = 5;      // per identity+IP
const LOGIN_LOCK_MINUTES   = 15;

/** Start a hardened session. Safe to call more than once. */
function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = is_https();
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['_last']) && $now - $_SESSION['_last'] > SESSION_IDLE_SECONDS) {
        session_unset();
        session_regenerate_id(true);
        flash('warning', 'You were signed out after 30 minutes of inactivity.');
    }
    $_SESSION['_last'] = $now;
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    // Rental history, customer details and invoices should not remain in shared browser caches.
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* ---------- CSRF ---------- */

function csrf_token(): string
{
    return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(?string $token): bool
{
    return is_string($token) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
}

/** Call at the top of every state-changing POST handler. */
function csrf_check(): void
{
    if (!csrf_valid($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Your session expired or the request was invalid. Go back, refresh the page and try again.');
    }
}

/* ---------- Auth & roles ---------- */

const ROLES = [
    // role => [table, id column, name column, password column]
    'customer' => ['customers', 'customer_username', 'customer_name', 'customer_password'],
    'admin'    => ['clients',   'client_username',   'client_name',   'client_password'],
];

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_role(string $role): bool
{
    return (current_user()['role'] ?? null) === $role;
}

/** Gate a page. Redirects guests to login; wrong role gets a 403. */
function require_role(string $role): array
{
    $u = current_user();
    if ($u === null) {
        $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(url('login.php?role=' . urlencode($role)));
    }
    if ($u['role'] !== $role) {
        http_response_code(403);
        exit('403 - You do not have permission to view this page.');
    }
    return $u;
}

function login_throttled(string $ident, string $ip): bool
{
    $n = (int) db_value(
        'SELECT COUNT(*) FROM login_attempts WHERE ident = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL ' . LOGIN_LOCK_MINUTES . ' MINUTE)',
        [$ident, $ip]
    );
    return $n >= LOGIN_MAX_ATTEMPTS;
}

/**
 * Verify credentials. Returns the user array on success, or an error string.
 * Throttles brute force, upgrades old hashes, and rotates the session id.
 */
function auth_attempt(string $role, string $username, string $password, string $ip = ''): array|string
{
    if (!isset(ROLES[$role])) {
        return 'Invalid account type.';
    }
    $ip = $ip ?: ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $ident = $role . ':' . strtolower($username);

    if (login_throttled($ident, $ip)) {
        return 'Too many failed attempts. Please try again in ' . LOGIN_LOCK_MINUTES . ' minutes.';
    }

    [$table, $idCol, $nameCol, $pwCol] = ROLES[$role];
    $row = db_one("SELECT $idCol AS id, $nameCol AS name, $pwCol AS pw FROM $table WHERE $idCol = ? LIMIT 1", [$username]);

    // Always run a hash check so response time doesn't reveal valid usernames.
    $hash = $row['pw'] ?? '$2y$10$usesomesillystringforsaltuNQ0gH0gZbJpnQmQy0nQq9x3b8lO6';
    $ok = password_verify($password, $hash) && $row !== null;

    if (!$ok) {
        db_exec('INSERT INTO login_attempts (ident, ip, attempted_at) VALUES (?, ?, NOW())', [$ident, $ip]);
        return 'Invalid username or password.';
    }

    db_exec('DELETE FROM login_attempts WHERE ident = ?', [$ident]);
    if (password_needs_rehash($row['pw'], PASSWORD_DEFAULT)) {
        db_exec("UPDATE $table SET $pwCol = ? WHERE $idCol = ?", [password_hash($password, PASSWORD_DEFAULT), $username]);
    }

    session_regenerate_id(true);   // prevent session fixation
    $_SESSION['user'] = ['id' => $row['id'], 'name' => $row['name'], 'role' => $role];
    return $_SESSION['user'];
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function password_hash_new(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}
