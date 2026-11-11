<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

// Vehicle creation now lives in the CSRF-protected, prepared-statement form.
if (is_post()) {
    http_response_code(410);
    exit('This legacy endpoint has been retired. Please use Fleet Vehicles.');
}

redirect(url('entercar.php'));
