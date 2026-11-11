<?php
// Legacy shim: old pages call Connect(). Credentials now come from .env via the
// secure DB layer. Removed once the remaining legacy pages are rebuilt.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/db.php';

function Connect(): mysqli
{
    return db();
}
