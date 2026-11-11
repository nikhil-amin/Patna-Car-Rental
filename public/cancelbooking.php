<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role('customer');
if (!is_post()) {
    redirect(url('mybookings.php'));
}
csrf_check();

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
if (!$bookingId || $bookingId < 1) {
    flash('warning', 'Invalid booking reference.');
    redirect(url('mybookings.php'));
}

try {
    db_transaction(function () use ($bookingId, $user): void {
        $booking = db_one(
            'SELECT id, customer_username, rent_start_date, booking_status FROM rentedcars WHERE id = ? FOR UPDATE',
            [$bookingId]
        );
        if (!$booking || $booking['customer_username'] !== $user['id']) {
            throw new DomainException('Booking not found.');
        }
        if ($booking['booking_status'] !== 'confirmed') {
            throw new DomainException('Only confirmed bookings can be cancelled online.');
        }
        if ((string) $booking['rent_start_date'] <= date('Y-m-d')) {
            throw new DomainException('Bookings can only be cancelled before the pickup date. Please contact the fleet owner for help.');
        }

        db_exec(
            "UPDATE rentedcars
             SET booking_status = 'cancelled', cancelled_at = NOW(), cancel_reason = ?
             WHERE id = ? AND customer_username = ? AND booking_status = 'confirmed'",
            ['Cancelled by customer', $bookingId, $user['id']]
        );
    });
    flash('success', 'Booking cancelled. The car and driver are now available for those dates.');
} catch (DomainException $e) {
    flash('warning', $e->getMessage());
} catch (Throwable $e) {
    error_log('DriveEase booking cancellation failed: ' . $e->getMessage());
    flash('error', 'We could not cancel this booking. Please try again.');
}

redirect(url('mybookings.php'));
