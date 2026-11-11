<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

$user = require_role('customer');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$car = $id > 0 ? car_find($id) : null;

if (!$car) {
    http_response_code(404);
    render_header('Car not found', 'cars');
    echo '<div class="container py-5 text-center"><h1 class="h3">Car not found</h1><a class="btn btn-brand mt-3" href="' . e(url('cars.php')) . '">Browse cars</a></div>';
    render_footer();
    exit;
}

$today = date('Y-m-d');
$startInput = (string) ($_GET['start'] ?? $today);
$endInput = (string) ($_GET['end'] ?? $today);
[$dateError, $start, $end] = validate_date_range($startInput, $endInput);
if ($dateError) {
    $start = $end = $today;
}
$carAvailable = car_is_available($id, $start, $end);

$drivers = db_all(
    "SELECT d.driver_id, d.driver_name, d.driver_phone, d.driver_gender
     FROM driver d
     JOIN clientcars cc ON cc.client_username = d.client_username
     WHERE cc.car_id = ? AND d.driver_availability = 'yes' AND d.is_active = 1
       AND NOT EXISTS (
         SELECT 1 FROM rentedcars r
         WHERE r.driver_id = d.driver_id
           AND r.booking_status IN " . BLOCKING_STATUSES . "
           AND r.rent_start_date <= ? AND r.rent_end_date >= ?
       )
     ORDER BY d.driver_name",
    [$id, $end, $start]
);

render_header('Book ' . $car['car_name'], 'cars');
?>
<div class="container py-4">
  <nav aria-label="breadcrumb"><ol class="breadcrumb small">
    <li class="breadcrumb-item"><a href="<?= e(url('cars.php')) ?>">Cars</a></li>
    <li class="breadcrumb-item"><a href="<?= e(url('car.php?id=' . $id)) ?>"><?= e($car['car_name']) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page">Book</li>
  </ol></nav>
  <div class="row g-4 justify-content-center">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h1 class="h3 fw-bold mb-1">Complete your booking</h1>
          <p class="text-muted mb-4">Check your dates for conflicts, then choose a fare type and available driver.</p>
          <?php if ($dateError): ?><div class="alert alert-warning"><?= e($dateError) ?> Dates have been reset to today; please select your dates again.</div><?php endif; ?>
          <form id="availability-check-form" method="get" action="<?= e(url('booking.php')) ?>" class="border rounded-3 p-3 mb-3 bg-body-tertiary"><input type="hidden" name="id" value="<?= $id ?>"><div class="row g-2 align-items-end"><div class="col-sm-5"><label class="form-label small" for="check_start">Check pickup date</label><input class="form-control form-control-sm" type="date" id="check_start" name="start" min="<?= e($today) ?>" value="<?= e($start) ?>" required></div><div class="col-sm-5"><label class="form-label small" for="check_end">Check return date</label><input class="form-control form-control-sm" type="date" id="check_end" name="end" min="<?= e($start) ?>" value="<?= e($end) ?>" required></div><div class="col-sm-2 d-grid"><button class="btn btn-outline-primary btn-sm" type="submit">Check</button></div></div></form>
          <?php if (!$carAvailable): ?><div class="alert alert-danger"><strong>Vehicle conflict:</strong> This car already has a confirmed or active booking overlapping the selected dates. Choose another date.</div><?php else: ?><div class="alert alert-success py-2">No vehicle conflict found for these dates. Availability will be checked again when you confirm.</div><?php endif; ?>
          <div id="availability-stale" class="alert alert-warning py-2 d-none" role="status">Your booking dates changed. Click <strong>Check</strong> above to refresh vehicle and driver availability before confirming.</div>
          <form id="booking-confirm-form" method="post" action="<?= e(url('bookingconfirm.php')) ?>" data-once>
            <?= csrf_field() ?>
            <input type="hidden" name="car_id" value="<?= $id ?>">
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label" for="rent_start_date">Pickup date</label>
                <input class="form-control" type="date" id="rent_start_date" name="rent_start_date" min="<?= e($today) ?>" value="<?= e($start) ?>" data-date-start="#rent_end_date" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="rent_end_date">Return date</label>
                <input class="form-control" type="date" id="rent_end_date" name="rent_end_date" min="<?= e($start) ?>" value="<?= e($end) ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label d-block">Comfort preference</label>
                <div class="d-flex flex-wrap gap-3">
                  <div class="form-check"><input class="form-check-input" type="radio" name="ac_type" id="ac" value="ac" checked required><label class="form-check-label" for="ac">AC</label></div>
                  <div class="form-check"><input class="form-check-input" type="radio" name="ac_type" id="non_ac" value="non_ac" required><label class="form-check-label" for="non_ac">Non-AC</label></div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label d-block">Fare calculation</label>
                <div class="d-flex flex-wrap gap-3">
                  <div class="form-check"><input class="form-check-input" type="radio" name="charge_type" id="days" value="days" checked required><label class="form-check-label" for="days">Per day</label></div>
                  <div class="form-check"><input class="form-check-input" type="radio" name="charge_type" id="km" value="km" required><label class="form-check-label" for="km">Per kilometre</label></div>
                </div>
                <div class="form-text">Per-kilometre bookings are billed using the actual distance recorded on return.</div>
              </div>
              <div class="col-12">
                <label class="form-label" for="driver_id">Driver</label>
                <select class="form-select" id="driver_id" name="driver_id" required <?= $drivers ? '' : 'disabled' ?>>
                  <option value="">Select an available driver</option>
                  <?php foreach ($drivers as $driver): ?>
                    <option value="<?= (int) $driver['driver_id'] ?>"><?= e($driver['driver_name']) ?> · <?= e($driver['driver_phone']) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if (!$drivers): ?><div class="form-text text-danger">No driver is currently available for these dates. Try another date or contact the fleet owner.</div><?php endif; ?>
              </div>
              <div class="col-12 d-grid mt-2">
                <button id="booking-confirm-button" class="btn btn-warning btn-lg fw-semibold" type="submit" <?= ($drivers && $carAvailable) ? '' : 'disabled' ?>><i class="fa-solid fa-calendar-check me-2"></i>Confirm booking</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm overflow-hidden">
        <img src="<?= e(car_image($car['car_img'])) ?>" alt="<?= e($car['car_name']) ?>" class="detail-img rounded-0">
        <div class="card-body">
          <span class="badge text-bg-light border mb-2"><?= e($car['category']) ?></span>
          <h2 class="h4 fw-bold"><?= e($car['car_name']) ?></h2>
          <p class="small text-muted"><?= (int) $car['seats'] ?> seats · <?= e($car['fuel_type']) ?> · <?= e($car['transmission']) ?></p>
          <div class="d-flex justify-content-between py-2 border-top"><span>AC · per day</span><strong><?= e(money($car['ac_price_per_day'])) ?></strong></div>
          <div class="d-flex justify-content-between py-2 border-top"><span>Non-AC · per day</span><strong><?= e(money($car['non_ac_price_per_day'])) ?></strong></div>
          <div class="d-flex justify-content-between py-2 border-top"><span>AC · per km</span><strong><?= e(money($car['ac_price'])) ?></strong></div>
          <div class="d-flex justify-content-between py-2 border-top"><span>Non-AC · per km</span><strong><?= e(money($car['non_ac_price'])) ?></strong></div>
          <p class="small text-muted mt-3 mb-0">Your selection and estimated charges are checked again on the server before a booking is saved.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php render_footer();
