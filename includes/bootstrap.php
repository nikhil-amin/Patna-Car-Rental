<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/security.php';

date_default_timezone_set('Asia/Kolkata');

// Never leak stack traces or SQL in production.
if (config('APP_ENV', 'production') === 'local') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    set_exception_handler(function (Throwable $e): void {
        error_log((string) $e);
        http_response_code(500);
        echo 'Something went wrong. Please try again later.';
    });
}

session_boot();
send_security_headers();
