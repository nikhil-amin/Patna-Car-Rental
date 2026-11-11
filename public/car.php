<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

$id  = (int) ($_GET['id'] ?? 0);
$car = $id > 0 ? car_find($id) : null;
if (!$car) {
    http_response_code(404);
    render_header('Car not found', 'cars');
    echo '<div class="container py-5 text-center"><h1 class="h3">Car not found</h1><a class="btn btn-brand mt-3" href="' . e(url('cars.php')) . '">Browse cars</a></div>';
    render_footer();
    exit;
}

$start = (string) ($_GET['start'] ?? '');
$end   = (string) ($_GET['end'] ?? '');
$check = null; $checkMsg = null;
if ($start !== '' || $end !== '') {
    [$err, $s, $e2] = validate_date_range($start, $end);
    if ($err) { $checkMsg = $err; $start = $end = ''; }
    else { $start = $s; $end = $e2; $check = car_is_available($id, $s, $e2); }
}
$today = date('Y-m-d');
$upcoming = db_all("SELECT rent_start_date, rent_end_date FROM rentedcars WHERE car_id = ? AND booking_status IN " . BLOCKING_STATUSES . " AND rent_end_date >= CURDATE() ORDER BY rent_start_date LIMIT 5", [$id]);
$bookUrl = url('booking.php?id=' . $id . ($start ? '&start=' . urlencode($start) . '&end=' . urlencode($end) : ''));

render_header($car['car_name'], 'cars');
?>
<section class="de-detail-wrap"><div class="container py-4">
  <nav aria-label="breadcrumb"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= e(url('cars.php')) ?>">Cars</a></li>
    <li class="breadcrumb-item active"><?= e($car['car_name']) ?></li>
  </ol></nav>
  <div class="row g-4">
    <div class="col-lg-7"><img class="detail-img shadow-sm" src="<?= e(car_image($car['car_img'])) ?>" alt="<?= e($car['car_name']) ?>"></div>
    <div class="col-lg-5">
      <span class="badge text-bg-light border mb-2"><?= e($car['category']) ?></span>
      <h1 class="h2 fw-bold"><?= e($car['car_name']) ?></h1>
      <p class="text-muted small mb-3">Reg. no. <?= e($car['car_nameplate']) ?></p>
      <div class="row g-2 mb-3 text-center">
        <div class="col-4"><div class="border rounded p-2 bg-white"><i class="fa-solid fa-user-group text-brand"></i><div class="small"><?= (int) $car['seats'] ?> seats</div></div></div>
        <div class="col-4"><div class="border rounded p-2 bg-white"><i class="fa-solid fa-gas-pump text-brand"></i><div class="small"><?= e($car['fuel_type']) ?></div></div></div>
        <div class="col-4"><div class="border rounded p-2 bg-white"><i class="fa-solid fa-gears text-brand"></i><div class="small"><?= e($car['transmission']) ?></div></div></div>
      </div>
      <?php if (!empty($car['description'])): ?><p><?= e($car['description']) ?></p><?php endif; ?>

      <table class="table table-sm bg-white border rounded mb-3">
        <thead class="table-light"><tr><th></th><th>Per day</th><th>Per km</th></tr></thead>
        <tbody>
          <tr><th>AC</th><td><?= e(money($car['ac_price_per_day'])) ?></td><td><?= e(money($car['ac_price'])) ?></td></tr>
          <tr><th>Non-AC</th><td><?= e(money($car['non_ac_price_per_day'])) ?></td><td><?= e(money($car['non_ac_price'])) ?></td></tr>
        </tbody>
      </table>

      <form method="get" class="card border-0 shadow-sm"><div class="card-body">
        <input type="hidden" name="id" value="<?= $id ?>">
        <h2 class="h6 fw-bold">Check availability</h2>
        <div class="row g-2">
          <div class="col-6"><input type="date" name="start" id="start" class="form-control" min="<?= e($today) ?>" value="<?= e($start) ?>" data-date-start="#end" required aria-label="Pickup date"></div>
          <div class="col-6"><input type="date" name="end" id="end" class="form-control" min="<?= e($today) ?>" value="<?= e($end) ?>" required aria-label="Return date"></div>
          <div class="col-12 d-grid"><button class="btn btn-outline-secondary" type="submit">Check dates</button></div>
        </div>
        <?php if ($checkMsg): ?><div class="alert alert-warning mt-3 mb-0 py-2"><?= e($checkMsg) ?></div><?php endif; ?>
        <?php if ($check === true): ?><div class="alert alert-success mt-3 mb-0 py-2"><i class="fa-solid fa-circle-check me-1"></i>Available from <?= e($start) ?> to <?= e($end) ?>.</div><?php endif; ?>
        <?php if ($check === false): ?><div class="alert alert-danger mt-3 mb-0 py-2"><i class="fa-solid fa-circle-xmark me-1"></i>Not available for those dates. Try other dates or <a href="<?= e(url('cars.php?' . http_build_query(['start' => $start, 'end' => $end]))) ?>">see similar cars</a>.</div><?php endif; ?>
      </div></form>

      <?php if ($upcoming): ?>
        <p class="small text-muted mt-3 mb-1">Already booked:</p>
        <ul class="small text-muted ps-3"><?php foreach ($upcoming as $b): ?><li><?= e($b['rent_start_date']) ?> → <?= e($b['rent_end_date']) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>

      <?php if ($check === false): ?>
        <button class="btn btn-secondary btn-lg w-100 mt-3" disabled>Unavailable for selected dates</button>
      <?php elseif (is_role('admin')): ?>
        <div class="alert alert-info mt-3 mb-0 small">Fleet owners can't book cars. Log in as a customer to rent.</div>
      <?php else: ?>
        <a class="btn btn-warning btn-lg fw-semibold w-100 mt-3" href="<?= e($bookUrl) ?>"><i class="fa-solid fa-calendar-check me-2"></i>Book this car</a>
      <?php endif; ?>
    </div>
  </div>
</div>
</section>
<?php render_footer();
