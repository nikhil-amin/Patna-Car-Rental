<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role('customer');
$bookings = db_all(
    "SELECT r.id, r.rent_start_date, r.rent_end_date, r.fare, r.charge_type, r.ac_type,
            r.booking_status, c.car_name, c.car_nameplate, d.driver_name
     FROM rentedcars r JOIN cars c ON c.car_id = r.car_id JOIN driver d ON d.driver_id = r.driver_id
     WHERE r.customer_username = ? AND r.return_status = 'NR'
       AND r.booking_status = 'active'
     ORDER BY r.rent_end_date ASC, r.id DESC",
    [$user['id']]
);
render_header('Active rentals', 'trips');
?>
<div class="container py-4">
  <div class="mb-4"><h1 class="h3 fw-bold mb-1">Active rentals</h1><p class="text-muted mb-0">Review an active trip and submit its actual distance or rental duration to prepare the final invoice.</p></div>
  <?php if (!$bookings): ?>
    <div class="card border-0 shadow-sm"><div class="card-body text-center py-5">
      <i class="fa-solid fa-car-side fa-3x text-muted mb-3"></i><h2 class="h5 fw-bold">No active rentals</h2>
      <p class="text-muted">Trips started by your fleet owner will appear here once they are ready for return.</p><a class="btn btn-brand" href="<?= e(url('mybookings.php')) ?>">View booking history</a>
    </div></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($bookings as $b): ?>
        <div class="col-12"><article class="card border-0 shadow-sm"><div class="card-body p-3 p-md-4">
          <div class="d-flex flex-column flex-md-row justify-content-between gap-3"><div>
            <div class="small text-muted"><?= e(invoice_no((int) $b['id'])) ?></div>
            <h2 class="h5 fw-bold mt-1 mb-1"><?= e($b['car_name']) ?> <span class="text-muted fw-normal">· <?= e($b['car_nameplate']) ?></span></h2>
            <div class="small text-muted">Driver: <?= e($b['driver_name']) ?> · <?= e(strtoupper((string) $b['ac_type'])) ?></div>
          </div><div class="text-md-end"><span class="badge text-bg-primary"><?= e(ucfirst((string) $b['booking_status'])) ?></span>
            <div class="fw-semibold mt-2"><?= e(money($b['fare'])) ?> / <?= e($b['charge_type'] === 'days' ? 'day' : 'km') ?></div>
          </div></div>
          <div class="row g-3 mt-2 align-items-end">
            <div class="col-6 col-md-4"><div class="small text-muted">Pickup date</div><strong><?= e($b['rent_start_date']) ?></strong></div>
            <div class="col-6 col-md-4"><div class="small text-muted">Scheduled return</div><strong><?= e($b['rent_end_date']) ?></strong></div>
            <div class="col-12 col-md-4 d-grid"><a class="btn btn-warning fw-semibold" href="<?= e(url('returncar.php?id=' . (int) $b['id'])) ?>"><i class="fa-solid fa-clipboard-check me-2"></i>Prepare return invoice</a></div>
          </div>
        </div></article></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer();