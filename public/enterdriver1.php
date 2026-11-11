<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

// Driver creation now uses the CSRF-protected form in enterdriver.php.
if (is_post()) {
    http_response_code(410);
    exit('This legacy endpoint has been retired. Please use Driver Management.');
}

redirect(url('enterdriver.php'));
