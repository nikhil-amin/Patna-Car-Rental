<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role('customer');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$booking = $id > 0 ? db_one(
    'SELECT r.id, r.rent_start_date, r.rent_end_date, r.fare, r.charge_type, r.ac_type, r.booking_status, r.payment_status, r.estimated_total, c.car_name, c.car_nameplate, d.driver_name, d.driver_phone
     FROM rentedcars r
     JOIN cars c ON c.car_id = r.car_id
     JOIN driver d ON d.driver_id = r.driver_id
     WHERE r.id = ? AND r.customer_username = ?',
    [$id, $user['id']]
) : null;

if (!$booking) {
    http_response_code(404);
    render_header('Booking not found');
    echo '<div class="container py-5 text-center"><h1 class="h3">Booking not found</h1><a class="btn btn-brand mt-3" href="' . e(url('cars.php')) . '">Browse cars</a></div>';
    render_footer();
    exit;
}

render_header('Booking confirmed');
?>
<div class="container py-5">
  <div class="card border-0 shadow-sm mx-auto" style="max-width:760px">
    <div class="card-body p-4 p-md-5 text-center">
      <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px"><i class="fa-solid fa-check fa-2x"></i></div>
      <h1 class="h2 fw-bold">Booking confirmed</h1>
      <p class="text-muted">Your reservation has been saved successfully.</p>
      <div class="bg-light rounded p-3 my-4">
        <div class="small text-muted">Booking reference</div>
        <div class="h3 fw-bold mb-0"><?= e(invoice_no((int) $booking['id'])) ?></div>
      </div>
      <div class="text-start">
        <h2 class="h5 fw-bold"><?= e($booking['car_name']) ?> <span class="text-muted fw-normal">· <?= e($booking['car_nameplate']) ?></span></h2>
        <div class="row g-3 mt-1">
          <div class="col-sm-6"><div class="small text-muted">Pickup</div><strong><?= e($booking['rent_start_date']) ?></strong></div>
          <div class="col-sm-6"><div class="small text-muted">Return</div><strong><?= e($booking['rent_end_date']) ?></strong></div>
          <div class="col-sm-6"><div class="small text-muted">Vehicle type</div><strong><?= e(strtoupper($booking['ac_type'])) ?></strong></div>
          <div class="col-sm-6"><div class="small text-muted">Fare</div><strong><?= e(money($booking['fare'])) ?> / <?= e($booking['charge_type'] === 'days' ? 'day' : 'km') ?></strong></div>
          <div class="col-sm-6"><div class="small text-muted">Driver</div><strong><?= e($booking['driver_name']) ?></strong><div class="small text-muted"><?= e($booking['driver_phone']) ?></div></div>
          <div class="col-sm-6"><div class="small text-muted">Payment status</div><strong><?= e(ucfirst($booking['payment_status'])) ?></strong></div>
        </div>
        <?php if ($booking['estimated_total'] !== null): ?><div class="border-top mt-4 pt-3 d-flex justify-content-between"><span>Estimated rental total</span><strong class="fs-5"><?= e(money($booking['estimated_total'])) ?></strong></div><?php else: ?><p class="small text-muted mt-4">This booking uses per-kilometre pricing; the final total will be calculated from the recorded trip distance.</p><?php endif; ?>
      </div>
      <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mt-4">
        <a class="btn btn-brand" href="<?= e(url('cars.php')) ?>">Browse more cars</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('mybookings.php')) ?>">View my bookings</a>
      </div>
    </div>
  </div>
</div>
<?php render_footer();
