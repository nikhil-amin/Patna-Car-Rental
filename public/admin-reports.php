<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$admin = require_role('admin');

$defaultFrom = (new DateTimeImmutable('first day of -11 months'))->format('Y-m-d');
$defaultTo = date('Y-m-d');
$from = trim((string)($_GET['from'] ?? $defaultFrom));
$to = trim((string)($_GET['to'] ?? $defaultTo));
$isDate = static function (string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};
if (!$isDate($from) || !$isDate($to) || $from > $to) {
    flash('warning', 'Choose a valid date range; the start date must not be after the end date.');
    redirect(url('admin-reports.php'));
}

$filter = "cc.client_username = ? AND r.booking_status = 'completed'
           AND r.return_status = 'R' AND r.car_return_date >= ? AND r.car_return_date <= ?";
$params = [$admin['id'], $from, $to];

$summary = db_one(
    "SELECT COUNT(*) AS rentals,
            COALESCE(SUM(r.total_amount), 0) AS invoiced,
            COALESCE(SUM(r.amount_paid), 0) AS collected,
            COALESCE(SUM(GREATEST(COALESCE(r.total_amount, 0) - COALESCE(r.amount_paid, 0), 0)), 0) AS outstanding
     FROM rentedcars r JOIN clientcars cc ON cc.car_id = r.car_id
     WHERE {$filter}",
    $params
) ?? [];

$monthly = db_all(
    "SELECT DATE_FORMAT(r.car_return_date, '%Y-%m') AS month_key,
            COUNT(*) AS rentals,
            COALESCE(SUM(r.total_amount), 0) AS invoiced,
            COALESCE(SUM(r.amount_paid), 0) AS collected
     FROM rentedcars r JOIN clientcars cc ON cc.car_id = r.car_id
     WHERE {$filter}
     GROUP BY DATE_FORMAT(r.car_return_date, '%Y-%m')
     ORDER BY month_key DESC",
    $params
);

$vehicles = db_all(
    "SELECT c.car_name, c.car_nameplate, COUNT(*) AS rentals,
            COALESCE(SUM(r.total_amount), 0) AS invoiced,
            COALESCE(SUM(r.amount_paid), 0) AS collected,
            COALESCE(SUM(GREATEST(COALESCE(r.total_amount, 0) - COALESCE(r.amount_paid, 0), 0)), 0) AS outstanding
     FROM rentedcars r
     JOIN clientcars cc ON cc.car_id = r.car_id
     JOIN cars c ON c.car_id = r.car_id
     WHERE {$filter}
     GROUP BY c.car_id, c.car_name, c.car_nameplate
     ORDER BY invoiced DESC, rentals DESC, c.car_name ASC",
    $params
);

render_header('Fleet reports', 'rentals');
?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div><div class="text-uppercase small fw-semibold text-muted">Fleet owner workspace</div><h1 class="h3 fw-bold mb-1">Fleet reports</h1><p class="text-muted mb-0">Revenue and vehicle performance from completed, returned rentals.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin-rentals.php')) ?>"><i class="fa-solid fa-arrow-left me-2"></i>Back to rentals</a>
  </div>

  <form method="get" class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="row g-3 align-items-end">
    <div class="col-12 col-sm-5"><label class="form-label" for="from">Return date from</label><input class="form-control" type="date" id="from" name="from" value="<?= e($from) ?>" required></div>
    <div class="col-12 col-sm-5"><label class="form-label" for="to">Return date to</label><input class="form-control" type="date" id="to" name="to" value="<?= e($to) ?>" min="<?= e($from) ?>" required></div>
    <div class="col-12 col-sm-2 d-grid"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply</button></div>
  </div><p class="small text-muted mb-0 mt-2">Range: <?= e($from) ?> to <?= e($to) ?>. Cancelled, active and not-yet-returned bookings are excluded.</p></div></form>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Completed rentals</div><div class="fs-3 fw-bold"><?= (int)($summary['rentals'] ?? 0) ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Revenue invoiced</div><div class="fs-5 fw-bold"><?= e(money($summary['invoiced'] ?? 0)) ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Payments collected</div><div class="fs-5 fw-bold text-success"><?= e(money($summary['collected'] ?? 0)) ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Outstanding</div><div class="fs-5 fw-bold text-danger"><?= e(money($summary['outstanding'] ?? 0)) ?></div></div></div></div>
  </div>

  <section class="card border-0 shadow-sm mb-4"><div class="card-body p-0">
    <div class="p-3 p-md-4 border-bottom"><h2 class="h5 fw-bold mb-1">Monthly revenue</h2><p class="small text-muted mb-0">Grouped by actual return month.</p></div>
    <?php if (!$monthly): ?><div class="p-4 text-muted">No completed rentals in this date range.</div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Month</th><th>Rentals</th><th>Invoiced</th><th>Collected</th><th>Outstanding</th></tr></thead><tbody>
      <?php foreach ($monthly as $m): $due = max(0, (float)$m['invoiced'] - (float)$m['collected']); ?><tr><td class="fw-semibold"><?= e(date('F Y', strtotime($m['month_key'] . '-01'))) ?></td><td><?= (int)$m['rentals'] ?></td><td><?= e(money($m['invoiced'])) ?></td><td class="text-success"><?= e(money($m['collected'])) ?></td><td class="<?= $due > 0 ? 'text-danger' : 'text-muted' ?>"><?= e(money($due)) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></section>

  <section class="card border-0 shadow-sm"><div class="card-body p-0">
    <div class="p-3 p-md-4 border-bottom"><h2 class="h5 fw-bold mb-1">Vehicle performance</h2><p class="small text-muted mb-0">Ranked by invoiced value for the selected period.</p></div>
    <?php if (!$vehicles): ?><div class="p-4 text-muted">No vehicle performance data for this period.</div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Vehicle</th><th>Rentals</th><th>Invoiced</th><th>Collected</th><th>Outstanding</th></tr></thead><tbody>
      <?php foreach ($vehicles as $v): ?><tr><td><strong><?= e($v['car_name']) ?></strong><div class="small text-muted"><?= e($v['car_nameplate']) ?></div></td><td><?= (int)$v['rentals'] ?></td><td class="fw-semibold"><?= e(money($v['invoiced'])) ?></td><td class="text-success"><?= e(money($v['collected'])) ?></td><td class="<?= (float)$v['outstanding'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= e(money($v['outstanding'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></section>
</div>
<?php render_footer();