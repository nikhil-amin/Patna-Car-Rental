<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$admin = require_role('admin');

$rows = db_all(
    "SELECT r.id, r.booking_date, r.rent_start_date, r.rent_end_date, r.car_return_date,
            r.booking_status, r.return_status, r.payment_status, r.fare, r.charge_type,
            r.no_of_days, r.distance, r.late_fee, r.total_amount, r.amount_paid,
            r.customer_username, cu.customer_name, cu.customer_phone,
            c.car_name, c.car_nameplate, d.driver_name
     FROM rentedcars r
     JOIN clientcars cc ON cc.car_id = r.car_id
     JOIN cars c ON c.car_id = r.car_id
     JOIN customers cu ON cu.customer_username = r.customer_username
     LEFT JOIN driver d ON d.driver_id = r.driver_id
     WHERE cc.client_username = ?
     ORDER BY r.booking_date DESC, r.id DESC",
    [$admin['id']]
);

function csv_safe(mixed $value): string
{
    $text = trim((string)($value ?? ''));
    // Prevent spreadsheet formula execution when opening exported user-controlled text.
    if ($text !== '' && preg_match('/^[=+@\-\t\r]/', $text)) {
        return "'" . $text;
    }
    return $text;
}

$filename = 'driveease-rentals-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'wb');
if ($out === false) {
    http_response_code(500);
    exit('Unable to create export.');
}
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
    'Booking ID', 'Booking date', 'Customer username', 'Customer name', 'Customer phone',
    'Vehicle', 'Registration', 'Driver', 'Pickup date', 'Scheduled return', 'Actual return',
    'Booking status', 'Return status', 'Fare type', 'Rate (INR)', 'Days', 'Distance (km)',
    'Late fee (INR)', 'Invoice total (INR)', 'Amount paid (INR)', 'Balance due (INR)', 'Payment status'
]);

foreach ($rows as $row) {
    $total = $row['total_amount'] === null ? null : round((float)$row['total_amount'], 2);
    $paid = round((float)$row['amount_paid'], 2);
    $due = $total === null ? null : max(0, round($total - $paid, 2));
    fputcsv($out, array_map('csv_safe', [
        $row['id'], $row['booking_date'], $row['customer_username'], $row['customer_name'],
        $row['customer_phone'], $row['car_name'], $row['car_nameplate'], $row['driver_name'],
        $row['rent_start_date'], $row['rent_end_date'], $row['car_return_date'],
        $row['booking_status'], $row['return_status'], $row['charge_type'], $row['fare'],
        $row['no_of_days'], $row['distance'], $row['late_fee'], $total, $paid, $due,
        $row['payment_status']
    ]));
}
fclose($out);
exit;
