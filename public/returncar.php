<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$user = require_role('customer');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid booking reference.'); }
$b = db_one("SELECT r.id,r.customer_username,r.rent_start_date,r.rent_end_date,r.fare,r.charge_type,r.booking_status,r.return_status,c.car_name,c.car_nameplate,d.driver_name,d.driver_phone FROM rentedcars r JOIN cars c ON c.car_id=r.car_id JOIN driver d ON d.driver_id=r.driver_id WHERE r.id=? AND r.customer_username=? LIMIT 1", [$id,$user['id']]);
if (!$b) { http_response_code(404); exit('Booking not found.'); }
if ($b['return_status'] !== 'NR' || $b['booking_status'] !== 'active') {
    flash('info', 'This trip is not active yet. The fleet owner must start the trip before it can be returned.');
    redirect(url('mybookings.php'));
}
$days = rental_days((string)$b['rent_start_date'], (string)$b['rent_end_date']);
render_header('Return rental','trips');
?>
<div class="container py-4">
  <a class="small text-decoration-none" href="<?= e(url('prereturncar.php')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back to active rentals</a>
  <h1 class="h3 fw-bold mt-2">Return rental</h1>
  <div class="row g-4 mt-1">
    <div class="col-lg-7"><section class="card border-0 shadow-sm"><div class="card-body p-4">
      <div class="small text-muted"><?= e(invoice_no((int)$b['id'])) ?></div><h2 class="h5 fw-bold mt-1"><?= e($b['car_name']) ?> · <?= e($b['car_nameplate']) ?></h2>
      <div class="row g-3 mt-2">
        <div class="col-6"><div class="small text-muted">Pickup</div><strong><?= e($b['rent_start_date']) ?></strong></div>
        <div class="col-6"><div class="small text-muted">Scheduled return</div><strong><?= e($b['rent_end_date']) ?></strong></div>
        <div class="col-6"><div class="small text-muted">Driver</div><strong><?= e($b['driver_name']) ?></strong></div>
        <div class="col-6"><div class="small text-muted">Contact</div><strong><?= e($b['driver_phone']) ?></strong></div>
        <div class="col-12"><div class="small text-muted">Agreed fare</div><strong class="fs-5"><?= e(money($b['fare'])) ?> / <?= e($b['charge_type']==='days'?'day':'km') ?></strong></div>
      </div>
    </div></section></div>
    <div class="col-lg-5"><section class="card border-0 shadow-sm"><div class="card-body p-4">
      <h2 class="h5 fw-bold">Trip completion</h2><p class="small text-muted">The final amount is calculated using the saved booking fare.</p>
      <form action="<?= e(url('printbill.php?id='.(int)$b['id'])) ?>" method="post"><?= csrf_field() ?>
        <?php if ($b['charge_type']==='km'): ?>
          <label class="form-label fw-semibold" for="distance">Distance travelled (km)</label>
          <input class="form-control mb-3" id="distance" name="distance" type="number" min="0.1" max="100000" step="0.1" inputmode="decimal" required>
        <?php else: ?>
          <div class="p-3 rounded bg-body-tertiary mb-3"><div class="small text-muted">Billable rental days</div><div class="fs-4 fw-bold"><?= $days ?></div></div>
        <?php endif; ?>
        <div class="alert alert-info small">A late-return fee of ₹200 per day may apply after the scheduled return date.</div>
        <button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-file-invoice me-2"></i>Calculate final invoice</button>
      </form>
    </div></section></div>
  </div>
</div>
<?php render_footer();