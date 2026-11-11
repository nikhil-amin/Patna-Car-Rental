<?php
declare(strict_types=1);

/**
 * Fast, database-free regression checks for critical rental lifecycle safeguards.
 * Run with: php tests/lifecycle_static_test.php
 */
$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        fwrite(STDOUT, "PASS {$label}\n");
    } else {
        $failed++;
        fwrite(STDERR, "FAIL {$label}\n");
    }
}

function source(string $path): string
{
    $fullPath = dirname(__DIR__) . '/' . $path;
    if (!is_file($fullPath)) {
        throw new RuntimeException("Required source file missing: {$path}");
    }
    return (string) file_get_contents($fullPath);
}

function contains_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            return false;
        }
    }
    return true;
}

$helpers = source('includes/helpers.php');
check('Windows document-root paths normalize backslashes for asset URLs', str_contains($helpers, "str_replace('\\\\', '/'") );

$security = source('includes/security.php');
$migration = source('database/migrate.php');
$booking = source('public/bookingconfirm.php');
$cancellation = source('public/cancelbooking.php');
$returnForm = source('public/returncar.php');
$returnHandler = source('public/printbill.php');
$adminRentals = source('public/admin-rentals.php');
$vehicles = source('public/entercar.php');
$drivers = source('public/enterdriver.php');
$vehicleEdit = source('public/editvehicle.php');
$driverEdit = source('public/editdriver.php');
$export = source('public/export-rentals.php');
$reports = source('public/admin-reports.php');
$customersPage = source('public/admin-customers.php');
$calendarPage = source('public/admin-calendar.php');
$reports = source('public/admin-reports.php');

check('sensitive dynamic pages disable browser caching', contains_all($security, ["Cache-Control: no-store, private, max-age=0", "Pragma: no-cache", "Expires: 0"]));

$apache = source('.htaccess');
check('Apache disables directory listings and routes the root into public/', contains_all($apache, ['Options -Indexes', 'RewriteEngine On', 'RewriteRule ^$ public/ [L]', 'RewriteRule ^(.*)$ public/$1 [L]']));
check('Apache blocks dotfiles and private application directories', contains_all($apache, ['RewriteRule (^|/)\\. - [F,L,NC]', 'RewriteRule ^(?:config|database|includes|tests|\\.github)(?:/|$) - [F,L,NC]']));

fwrite(STDOUT, "Migration compatibility\n");
check('booking cancellation columns are migrated', contains_all($migration, ["'cancelled_at'", "'cancel_reason'"]));
check('vehicle and driver active flags are migrated', contains_all($migration, ["add_col('cars', 'is_active'", "add_col('driver', 'is_active'"]));
check('payment ledger is created by migration', str_contains($migration, 'CREATE TABLE IF NOT EXISTS payments'));
check('migration repairs either missing rental index independently', contains_all($migration, ["INDEX_NAME='idx_car_dates'", "INDEX_NAME='idx_status'", "ADD INDEX idx_car_dates (car_id, rent_start_date, rent_end_date)", "ADD INDEX idx_status (booking_status)"]));

fwrite(STDOUT, "\nBooking safeguards\n");
check('booking submission is POST-only and CSRF-protected', contains_all($booking, ['if (!is_post())', 'csrf_check();']));
check('inactive cars and drivers cannot be booked', contains_all($booking, ['car_id = ? AND is_active = 1', 'd.is_active = 1']));
check('booking rejects overlapping blocking statuses', str_contains($booking, 'BLOCKING_STATUSES'));
$bookingForm = source('public/booking.php');
$appJs = source('public/assets/js/app.js');
check('booking date edits require a fresh availability check', contains_all($bookingForm, ['id="availability-check-form"', 'id="availability-stale"', 'id="booking-confirm-button"']) && contains_all($appJs, ['requireAvailabilityRefresh', 'checkPickup.value = pickup.value', 'confirmButton.disabled = true']));

check('booking form prechecks vehicle conflicts and blocks unavailable cars', contains_all($bookingForm, ['car_is_available($id, $start, $end)', 'Vehicle conflict:', '($drivers && $carAvailable)']));
check('booking form refreshes checks for chosen dates and excludes conflicting drivers', contains_all($bookingForm, ['id="check_start"', 'name="start"', 'NOT EXISTS (', 'r.driver_id = d.driver_id', 'r.rent_start_date <= ? AND r.rent_end_date >= ?']));
check('cancellation is POST-only and CSRF-protected', contains_all($cancellation, ['if (!is_post())', 'csrf_check();']));
check('cancellation checks customer ownership and confirmed status', contains_all($cancellation, ['customer_username', 'confirmed']));

fwrite(STDOUT, "\nReturn and payment safeguards\n");
check('return form requires an active, unreturned trip', contains_all($returnForm, ["booking_status'] !== 'active'", "return_status'] !== 'NR'"]));
check('return handler locks the booking and verifies customer ownership', contains_all($returnHandler, ['FOR UPDATE', 'customer_username', 'csrf_check();']));
check('return handler accepts only active trips', contains_all($returnHandler, ["booking_status'] !== 'active'", "return_status'] !== 'NR'"]));
check('return distance remains positive after invoice rounding', contains_all($returnHandler, ['$distance = round((float)$raw, 1)', '$distance < 0.1', 'Trip distance must be at least 0.1 km.']));
check('payment recording is scoped to the fleet owner', contains_all($adminRentals, ['JOIN clientcars cc ON cc.car_id = r.car_id', "cc.client_username = ?"]));
check('payments require a completed, returned rental', contains_all($adminRentals, ["booking_status'] !== 'completed'", "return_status'] !== 'R'"]));
check('payments cannot exceed the outstanding balance', str_contains($adminRentals, '$amount > $outstanding'));
check('rounded payment amount must be at least one paisa', contains_all($adminRentals, ['$amount = round((float)$amountRaw, 2)', '$amount <= 0', 'Payment must be at least ₹0.01 after rounding.']));
check('payment ledger and booking balance update together', contains_all($adminRentals, ['INSERT INTO payments', 'UPDATE rentedcars SET amount_paid = ?, payment_status = ?']));
check('CSV export is admin-only and fleet-owner scoped', contains_all($export, ["require_role('admin')", 'cc.client_username = ?', 'Content-Disposition']));
check('CSV export protects spreadsheet formulas', contains_all($export, ['csv_safe', 'spreadsheet formula execution']));
check('reports require admin and validate date range', contains_all($reports, ["require_role('admin')", '$from > $to', 'createFromFormat']));
check('reports aggregate only owner-scoped completed returns', contains_all($reports, ["r.booking_status = 'completed'", "r.return_status = 'R'", 'cc.client_username = ?', 'car_return_date']));
check('reports include monthly and vehicle performance queries', contains_all($reports, ["DATE_FORMAT(r.car_return_date, '%Y-%m')", 'GROUP BY c.car_id']));
check('customer directory requires admin access', str_contains($customersPage, "require_role('admin')"));
check('customer directory scopes records to the fleet owner', str_contains($customersPage, 'cc.client_username = ?'));
check('customer search uses prepared LIKE filters', str_contains($customersPage, 'LIKE ?'));
check('customer history is limited and fleet-scoped', contains_all($customersPage, ['LIMIT 100', 'r.customer_username = ?', 'cc.client_username = ?']));
check('booking calendar requires admin and scopes fleet vehicles', contains_all($calendarPage, ["require_role('admin')", 'cc.client_username=?']));
check('booking calendar filters blocking statuses and date overlap', contains_all($calendarPage, ["IN ('confirmed','active')", 'r.rent_start_date<=?', 'r.rent_end_date>=?']));
check('booking calendar validates a maximum 90-day date range', contains_all($calendarPage, ['DateTimeImmutable::createFromFormat', '> 90']));
check('reports require admin and validate date range', contains_all($reports, ["require_role('admin')", '$from > $to', 'createFromFormat']));
check('reports aggregate only owner-scoped completed returns', contains_all($reports, ["r.booking_status = 'completed'", "r.return_status = 'R'", 'cc.client_username = ?', 'car_return_date']));
check('reports include monthly and vehicle performance queries', contains_all($reports, ["DATE_FORMAT(r.car_return_date, '%Y-%m')", 'GROUP BY c.car_id']));

fwrite(STDOUT, "\nFleet management safeguards\n");
check('vehicle creation is admin-only and CSRF-protected', contains_all($vehicles, ["require_role('admin')", 'csrf_check();']));
check('vehicle registration uses prepared duplicate check', contains_all($vehicles, ['car_nameplate = ?', 'db_exec(']));
check('vehicle edit is CSRF-protected and owner-scoped', contains_all($vehicleEdit, ['csrf_check();', 'cc.client_username = ?', 'UPDATE cars SET']));
check('vehicle cannot be deactivated during an active trip', contains_all($vehicles, ['toggle_vehicle', "booking_status = 'active'", 'active trip']));
check('driver creation is admin-only and CSRF-protected', contains_all($drivers, ["require_role('admin')", 'csrf_check();']));
check('driver listing is scoped to the fleet owner', str_contains($drivers, 'WHERE client_username = ?'));
check('driver edit is CSRF-protected and owner-scoped', contains_all($driverEdit, ['csrf_check();', 'WHERE driver_id = ? AND client_username = ?', 'UPDATE driver SET']));
check('driver cannot be deactivated during an active trip', contains_all($drivers, ['toggle_driver', "booking_status = 'active'", 'active trip']));

fwrite(STDOUT, "\nResult: {$passed} passed, {$failed} failed\n");
exit($failed === 0 ? 0 : 1);
