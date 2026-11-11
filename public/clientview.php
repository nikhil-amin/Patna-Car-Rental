<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

// Fleet reporting and payment history now live in the secured admin dashboard.
redirect(url('admin-rentals.php'));
