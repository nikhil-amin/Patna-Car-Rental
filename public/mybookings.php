<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_role('customer');
$bookings = db_all(
    'SELECT r.id, r.rent_start_date, r.rent_end_date, r.car_return_date, r.fare, r.charge_type, r.ac_type,
            r.booking_status, r.payment_status, r.estimated_total, r.total_amount, r.distance, r.no_of_days,
            c.car_name, c.car_nameplate, d.driver_name
     FROM rentedcars r JOIN cars c ON c.car_id = r.car_id JOIN driver d ON d.driver_id = r.driver_id
     WHERE r.customer_username = ? ORDER BY r.booking_date DESC, r.id DESC',
    [$user['id']]
);
$statusClass = static fn(string $status): string => match ($status) {
    'confirmed' => 'text-bg-primary', 'active' => 'text-bg-warning',
    'completed' => 'text-bg-success', 'cancelled' => 'text-bg-secondary', default => 'text-bg-light',
};
render_header('My bookings', 'history');
?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
    <div><h1 class="h3 fw-bold mb-1">My bookings</h1><p class="text-muted mb-0">Track reservations, trip status and final charges.</p></div>
    <a class="btn btn-brand" href="<?= e(url('cars.php')) ?>"><i class="fa-solid fa-car me-2"></i>Book a car</a>
  </div>
  <?php if (!$bookings): ?>
    <div class="card border-0 shadow-sm"><div class="card-body text-center py-5">
      <i class="fa-solid fa-calendar-xmark fa-3x text-muted mb-3"></i><h2 class="h5 fw-bold">No bookings yet</h2>
      <p class="text-muted">Your reservations will appear here after you book a car.</p><a class="btn btn-warning fw-semibold" href="<?= e(url('cars.php')) ?>">Explore cars</a>
    </div></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($bookings as $b): ?>
        <div class="col-12"><article class="card border-0 shadow-sm"><div class="card-body p-3 p-md-4">
          <div class="d-flex flex-column flex-md-row justify-content-between gap-2"><div>
            <div class="small text-muted mb-1"><?= e(invoice_no((int)$b['id'])) ?></div>
            <h2 class="h5 fw-bold mb-1"><?= e($b['car_name']) ?> <span class="text-muted fw-normal">· <?= e($b['car_nameplate']) ?></span></h2>
            <div class="small text-muted">Driver: <?= e($b['driver_name']) ?></div>
          </div><div class="d-flex align-items-start gap-2 flex-wrap">
            <span class="badge <?= e($statusClass((string)$b['booking_status'])) ?>"><?= e(ucfirst((string)$b['booking_status'])) ?></span>
            <span class="badge text-bg-light border"><?= e(ucfirst((string)$b['payment_status'])) ?></span>
          </div></div>
          <div class="row g-3 mt-2">
            <div class="col-6 col-md-3"><div class="small text-muted">Pickup</div><strong><?= e($b['rent_start_date']) ?></strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">Return</div><strong><?= e($b['rent_end_date']) ?></strong></div>
            <div class="col-6 col-md-3"><div class="small text-muted">Fare</div><strong><?= e(money($b['fare'])) ?> / <?= e($b['charge_type']==='days'?'day':'km') ?></strong><div class="small text-muted"><?= e(strtoupper((string)$b['ac_type'])) ?></div></div>
            <div class="col-6 col-md-3"><div class="small text-muted"><?= $b['booking_status']==='completed'?'Final total':'Estimated total' ?></div><strong><?= $b['total_amount']!==null && $b['booking_status']==='completed' ? e(money($b['total_amount'])) : ($b['estimated_total']!==null ? e(money($b['estimated_total'])) : 'Calculated after return') ?></strong></div>
          </div>
          <?php if ($b['booking_status']==='completed' && $b['distance']!==null): ?><div class="small text-muted mt-3">Trip completed<?= $b['car_return_date'] ? ' on '.e($b['car_return_date']) : '' ?> · <?= e((string)$b['distance']) ?> km recorded</div><?php endif; ?>
          <div class="mt-3 d-flex flex-wrap gap-2">
            <?php if ($b['booking_status']==='completed' && $b['total_amount']!==null): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('printbill.php?id='.(int)$b['id'])) ?>"><i class="fa-solid fa-file-invoice me-1"></i>View invoice</a><?php endif; ?>
            <?php if ($b['booking_status']==='confirmed' && (string)$b['rent_start_date'] > date('Y-m-d')): ?>
              <form method="post" action="<?= e(url('cancelbooking.php')) ?>" onsubmit="return confirm('Cancel this booking? This action cannot be undone.');">
                <?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-solid fa-ban me-1"></i>Cancel booking</button>
              </form>
            <?php endif; ?>
          </div>
        </div></article></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php render_footer();