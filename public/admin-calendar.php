<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
$admin = require_role('admin');
$owner = (string)$admin['id'];
$today = date('Y-m-d');
$from = (string)($_GET['from'] ?? $today);
$to = (string)($_GET['to'] ?? date('Y-m-d', strtotime('+30 days')));
$valid = static function (string $v): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    $e = DateTimeImmutable::getLastErrors();
    return $d !== false && ($e === false || ($e['warning_count'] === 0 && $e['error_count'] === 0)) && $d->format('Y-m-d') === $v;
};
if (!$valid($from) || !$valid($to) || $from > $to || (int)(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 90) {
    $from = $today;
    $to = date('Y-m-d', strtotime('+30 days'));
    flash('warning', 'Choose a valid date range of up to 90 days.');
}
$cars = db_all("SELECT c.car_id,c.car_name,c.car_nameplate,c.is_active FROM cars c JOIN clientcars cc ON cc.car_id=c.car_id WHERE cc.client_username=? ORDER BY c.car_name", [$owner]);
$bookings = db_all("SELECT r.id,r.car_id,r.rent_start_date,r.rent_end_date,r.booking_status,cu.customer_name,cu.customer_phone,c.car_name,c.car_nameplate,d.driver_name FROM rentedcars r JOIN clientcars cc ON cc.car_id=r.car_id JOIN cars c ON c.car_id=r.car_id JOIN customers cu ON cu.customer_username=r.customer_username LEFT JOIN driver d ON d.driver_id=r.driver_id WHERE cc.client_username=? AND r.booking_status IN ('confirmed','active') AND r.return_status='NR' AND r.rent_start_date<=? AND r.rent_end_date>=? ORDER BY r.rent_start_date,r.id", [$owner,$to,$from]);
$busy = [];
foreach ($bookings as $b) $busy[(int)$b['car_id']] = true;
render_header('Booking calendar', 'calendar');
?>
<div class="container py-4">
 <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4"><div><div class="text-uppercase small fw-semibold text-muted">Fleet owner workspace</div><h1 class="h3 fw-bold mb-1">Booking calendar</h1><p class="text-muted mb-0">Review reservations and availability for your own fleet.</p></div><a class="btn btn-outline-secondary" href="<?= e(url('admin-rentals.php')) ?>">Back to rentals</a></div>
 <form method="get" class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-3 align-items-end"><div class="col-md-4"><label class="form-label" for="from">From</label><input class="form-control" id="from" type="date" name="from" value="<?= e($from) ?>" required></div><div class="col-md-4"><label class="form-label" for="to">To</label><input class="form-control" id="to" type="date" name="to" value="<?= e($to) ?>" min="<?= e($from) ?>" required></div><div class="col-md-4 d-flex gap-2"><button class="btn btn-brand flex-grow-1" type="submit">View schedule</button><a class="btn btn-outline-secondary" href="<?= e(url('admin-calendar.php')) ?>">Next 30 days</a></div></div><p class="form-text mb-0">Up to 90 days. Confirmed and active rentals block availability; cancelled and completed rentals are excluded.</p></div></form>
 <div class="row g-3 mb-4"><?php foreach ([['Fleet vehicles',count($cars),''],['Reserved in range',count($busy),'text-primary'],['No blocking booking',max(0,count($cars)-count($busy)),'text-success']] as [$label,$value,$color]): ?><div class="col-4"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?= e($label) ?></div><div class="fs-3 fw-bold <?= e($color) ?>"><?= (int)$value ?></div></div></div></div><?php endforeach; ?></div>
 <section class="card border-0 shadow-sm mb-4"><div class="card-body p-0"><div class="p-3 border-bottom"><h2 class="h5 fw-bold mb-1">Reservation schedule</h2><p class="small text-muted mb-0"><?= e($from) ?> to <?= e($to) ?> · <?= count($bookings) ?> booking(s)</p></div>
 <?php if (!$bookings): ?><div class="p-5 text-center"><h3 class="h6 fw-bold">No blocking reservations</h3><p class="text-muted mb-0">No confirmed or active rentals overlap this range.</p></div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Dates</th><th>Vehicle</th><th>Customer</th><th>Driver</th><th>Status</th><th>Booking</th></tr></thead><tbody><?php foreach ($bookings as $b): ?><tr><td><strong><?= e($b['rent_start_date']) ?></strong><div class="small text-muted">to <?= e($b['rent_end_date']) ?></div></td><td><strong><?= e($b['car_name']) ?></strong><div class="small text-muted"><?= e($b['car_nameplate']) ?></div></td><td><strong><?= e($b['customer_name']) ?></strong><div class="small text-muted"><?= e($b['customer_phone'] ?? '') ?></div></td><td><?= e($b['driver_name'] ?? '—') ?></td><td><span class="badge <?= $b['booking_status']==='active'?'text-bg-warning':'text-bg-primary' ?>"><?= e(ucfirst((string)$b['booking_status'])) ?></span></td><td><?= e(invoice_no((int)$b['id'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
 <section class="card border-0 shadow-sm"><div class="card-body p-0"><div class="p-3 border-bottom"><h2 class="h5 fw-bold mb-1">Fleet availability</h2><p class="small text-muted mb-0">Status for the selected date range.</p></div><?php if (!$cars): ?><div class="p-4 text-muted">No vehicles are assigned to your fleet yet.</div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Vehicle</th><th>Fleet status</th><th>Selected range</th></tr></thead><tbody><?php foreach ($cars as $car): $reserved=isset($busy[(int)$car['car_id']]); ?><tr><td><strong><?= e($car['car_name']) ?></strong><div class="small text-muted"><?= e($car['car_nameplate']) ?></div></td><td><span class="badge <?= (int)$car['is_active']===1?'text-bg-success':'text-bg-secondary' ?>"><?= (int)$car['is_active']===1?'Active':'Inactive' ?></span></td><td><span class="badge <?= $reserved?'text-bg-primary':'text-bg-success' ?>"><?= $reserved?'Reserved':'No blocking booking' ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
</div>
<?php render_footer();