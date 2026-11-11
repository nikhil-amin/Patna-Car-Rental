<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

$user = require_role('customer');
if (!is_post()) {
    redirect(url('cars.php'));
}
csrf_check();

$carId = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT) ?: 0;
$driverId = filter_input(INPUT_POST, 'driver_id', FILTER_VALIDATE_INT) ?: 0;
$startRaw = trim((string) ($_POST['rent_start_date'] ?? ''));
$endRaw = trim((string) ($_POST['rent_end_date'] ?? ''));
$acType = (string) ($_POST['ac_type'] ?? '');
$chargeType = (string) ($_POST['charge_type'] ?? '');

[$dateError, $start, $end] = validate_date_range($startRaw, $endRaw);
if ($carId < 1 || $driverId < 1 || $dateError || !in_array($acType, ['ac', 'non_ac'], true) || !in_array($chargeType, ['days', 'km'], true)) {
    flash('warning', $dateError ?: 'Please complete all booking fields with valid selections.');
    redirect(url('booking.php?id=' . max(0, $carId) . '&start=' . urlencode($startRaw) . '&end=' . urlencode($endRaw)));
}

try {
    $bookingId = db_transaction(function () use ($carId, $driverId, $start, $end, $acType, $chargeType, $user): int {
        // Lock the car row so two requests cannot reserve it at the same time.
        $car = db_one('SELECT * FROM cars WHERE car_id = ? AND is_active = 1 FOR UPDATE', [$carId]);
        if (!$car) {
            throw new DomainException('This car is no longer available.');
        }

        $overlap = (int) db_value(
            'SELECT COUNT(*) FROM rentedcars WHERE car_id = ? AND booking_status IN ' . BLOCKING_STATUSES . ' AND rent_start_date <= ? AND rent_end_date >= ?',
            [$carId, $end, $start]
        );
        if ($overlap > 0) {
            throw new DomainException('This car has already been booked for some or all of those dates. Please choose another date.');
        }

        // Validate driver ownership and lock the row before checking its schedule.
        $driver = db_one(
            "SELECT d.driver_id FROM driver d
             JOIN clientcars cc ON cc.client_username = d.client_username
             WHERE d.driver_id = ? AND cc.car_id = ? AND d.driver_availability = 'yes' AND d.is_active = 1
             FOR UPDATE",
            [$driverId, $carId]
        );
        if (!$driver) {
            throw new DomainException('That driver is no longer available for this car.');
        }
        $driverOverlap = (int) db_value(
            'SELECT COUNT(*) FROM rentedcars WHERE driver_id = ? AND booking_status IN ' . BLOCKING_STATUSES . ' AND rent_start_date <= ? AND rent_end_date >= ?',
            [$driverId, $end, $start]
        );
        if ($driverOverlap > 0) {
            throw new DomainException('That driver has been booked for those dates. Please choose another driver or date.');
        }

        $fareColumn = $acType === 'ac'
            ? ($chargeType === 'days' ? 'ac_price_per_day' : 'ac_price')
            : ($chargeType === 'days' ? 'non_ac_price_per_day' : 'non_ac_price');
        $fare = (float) $car[$fareColumn];
        if ($fare <= 0) {
            throw new DomainException('The selected fare is invalid. Please choose another car.');
        }
        $estimatedTotal = $chargeType === 'days' ? $fare * rental_days($start, $end) : null;

        db_exec(
            "INSERT INTO rentedcars
             (customer_username, car_id, driver_id, booking_date, rent_start_date, rent_end_date, fare, charge_type, return_status, ac_type, booking_status, payment_status, estimated_total)
             VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, 'NR', ?, 'confirmed', 'unpaid', ?)",
            [$user['id'], $carId, $driverId, $start, $end, $fare, $chargeType, $acType, $estimatedTotal]
        );

        return db_insert_id();
    });

    redirect(url('booking-success.php?id=' . $bookingId));
} catch (DomainException $e) {
    flash('warning', $e->getMessage());
    redirect(url('booking.php?id=' . $carId . '&start=' . urlencode($start) . '&end=' . urlencode($end)));
} catch (Throwable $e) {
    error_log('DriveEase booking failed: ' . $e->getMessage());
    flash('error', 'We could not complete your booking. Please try again.');
    redirect(url('booking.php?id=' . $carId . '&start=' . urlencode($start) . '&end=' . urlencode($end)));
}
