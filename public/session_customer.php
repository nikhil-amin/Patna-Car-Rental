<?php
declare(strict_types=1);

// Retired legacy session guard. Use includes/security.php and require_role('customer').
http_response_code(410);
exit('This legacy session endpoint has been retired. Please use the DriveEase login flow.');
