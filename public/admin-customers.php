<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$admin = require_role('admin');
$owner = (string)$admin['id'];
$q = trim((string)($_GET['q'] ?? ''));
if (strlen($q) > 80) {
    $q = substr($q, 0, 80);
}
$selected = trim((string)($_GET['customer'] ?? ''));
if (strlen($selected) > 50) {
    $selected = '';
}

$where = "EXISTS (
    SELECT 1 FROM rentedcars r
    JOIN clientcars cc ON cc.car_id = r.car_id
    WHERE r.customer_username = cu.customer_username
      AND cc.client_username = ?
)";
$params = [$owner];
if ($q !== '') {
    $where .= " AND (cu.customer_name LIKE ? OR cu.customer_username LIKE ? OR cu.customer_phone LIKE ? OR cu.customer_email LIKE ?)";
    $term = '%' . $q . '%';
    array_push($params, $term, $term, $term, $term);
}

$customers = db_all(
    "SELECT cu.customer_username, cu.customer_name, cu.customer_phone, cu.customer_email,
            COUNT(r.id) AS rental_count,
            SUM(CASE WHEN r.booking_status = 'completed' AND r.return_status = 'R' THEN 1 ELSE 0 END) AS completed_count,
            MAX(r.booking_date) AS last_booking
     FROM customers cu
     JOIN rentedcars r ON r.customer_username = cu.customer_username
     JOIN clientcars cc ON cc.car_id = r.car_id
     WHERE {$where}
     GROUP BY cu.customer_username, cu.customer_name, cu.customer_phone, cu.customer_email
     ORDER BY last_booking DESC, cu.customer_name ASC
     LIMIT 200",
    $params
);

$profile = null;
$history = [];
$totals = [];
if ($selected !== '') {
    $profile = db_one(
        "SELECT cu.customer_username, cu.customer_name, cu.customer_phone, cu.customer_email, cu.customer_address
         FROM customers cu
         WHERE cu.customer_username = ?
           AND EXISTS (
             SELECT 1 FROM rentedcars r
             JOIN clientcars cc ON cc.car_id = r.car_id
             WHERE r.customer_username = cu.customer_username
               AND cc.client_username = ?
           )",
        [$selected, $owner]
    );

    if ($profile) {
        $history = db_all(
            "SELECT r.id, r.booking_date, r.rent_start_date, r.rent_end_date, r.car_return_date,
                    r.booking_status, r.return_status, r.payment_status, r.total_amount,
                    r.amount_paid, r.late_fee, c.car_name, c.car_nameplate, d.driver_name
             FROM rentedcars r
             JOIN clientcars cc ON cc.car_id = r.car_id
             JOIN cars c ON c.car_id = r.car_id
             LEFT JOIN driver d ON d.driver_id = r.driver_id
             WHERE r.customer_username = ? AND cc.client_username = ?
             ORDER BY r.booking_date DESC, r.id DESC
             LIMIT 100",
            [$selected, $owner]
        );
        $totals = db_one(
            "SELECT COUNT(*) AS bookings,
                    SUM(CASE WHEN r.booking_status = 'completed' AND r.return_status = 'R' THEN 1 ELSE 0 END) AS completed,
                    COALESCE(SUM(CASE WHEN r.booking_status = 'completed' AND r.return_status = 'R' THEN COALESCE(r.total_amount,0) ELSE 0 END),0) AS invoiced,
                    COALESCE(SUM(CASE WHEN r.booking_status = 'completed' AND r.return_status = 'R' THEN COALESCE(r.amount_paid,0) ELSE 0 END),0) AS collected,
                    COALESCE(SUM(CASE WHEN r.booking_status = 'completed' AND r.return_status = 'R' THEN GREATEST(COALESCE(r.total_amount,0)-COALESCE(r.amount_paid,0),0) ELSE 0 END),0) AS outstanding
             FROM rentedcars r
             JOIN clientcars cc ON cc.car_id = r.car_id
             WHERE r.customer_username = ? AND cc.client_username = ?",
            [$selected, $owner]
        ) ?? [];
    } else {
        flash('warning', 'That customer has no booking in your fleet.');
        redirect(url('admin-customers.php' . ($q !== '' ? '?q=' . rawurlencode($q) : '')));
    }
}

$statusClass = static fn(string $status): string => match ($status) {
    'confirmed' => 'text-bg-primary',
    'active' => 'text-bg-warning',
    'completed' => 'text-bg-success',
    'cancelled' => 'text-bg-secondary',
    default => 'text-bg-light',
};

render_header('Customers', 'customers');
?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div><div class="text-uppercase small fw-semibold text-muted">Fleet owner workspace</div><h1 class="h3 fw-bold mb-1">Customers</h1><p class="text-muted mb-0">Find customers who have booked vehicles in your fleet and review their rental history.</p></div>
    <a class="btn btn-outline-secondary" href="<?= e(url('admin-rentals.php')) ?>"><i class="fa-solid fa-arrow-left me-2"></i>Back to rentals</a>
  </div>

  <form method="get" class="card border-0 shadow-sm mb-4"><div class="card-body"><label for="customer-search" class="form-label fw-semibold">Search your customers</label><div class="input-group"><span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span><input id="customer-search" class="form-control" type="search" name="q" maxlength="80" value="<?= e($q) ?>" placeholder="Name, username, phone or email"><button class="btn btn-brand" type="submit">Search</button><?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="<?= e(url('admin-customers.php')) ?>">Clear</a><?php endif; ?></div><div class="form-text">Showing up to 200 customers with bookings from your fleet.</div></div></form>

  <?php if ($profile): ?>
  <section class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3 p-md-4">
      <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3"><div><div class="small text-muted">Customer profile</div><h2 class="h4 fw-bold mb-1"><?= e($profile['customer_name']) ?></h2><div class="text-muted small">@<?= e($profile['customer_username']) ?></div></div><a class="btn btn-sm btn-outline-secondary align-self-start" href="<?= e(url('admin-customers.php' . ($q !== '' ? '?q=' . rawurlencode($q) : ''))) ?>"><i class="fa-solid fa-xmark me-1"></i>Close profile</a></div>
      <div class="row g-3 mb-4">
        <div class="col-12 col-md-4"><div class="rounded-3 bg-body-tertiary p-3 h-100"><div class="small text-muted mb-1">Phone</div><div class="fw-semibold"><?= e($profile['customer_phone'] ?: 'Not provided') ?></div></div></div>
        <div class="col-12 col-md-4"><div class="rounded-3 bg-body-tertiary p-3 h-100"><div class="small text-muted mb-1">Email</div><div class="fw-semibold text-break"><?= e($profile['customer_email'] ?: 'Not provided') ?></div></div></div>
        <div class="col-12 col-md-4"><div class="rounded-3 bg-body-tertiary p-3 h-100"><div class="small text-muted mb-1">Address</div><div class="fw-semibold"><?= e($profile['customer_address'] ?: 'Not provided') ?></div></div></div>
      </div>
      <div class="row g-3">
        <div class="col-6 col-xl"><div class="small text-muted">Total bookings</div><div class="fs-4 fw-bold"><?= (int)($totals['bookings'] ?? 0) ?></div></div>
        <div class="col-6 col-xl"><div class="small text-muted">Completed</div><div class="fs-4 fw-bold"><?= (int)($totals['completed'] ?? 0) ?></div></div>
        <div class="col-6 col-xl"><div class="small text-muted">Invoiced</div><div class="fw-bold"><?= e(money($totals['invoiced'] ?? 0)) ?></div></div>
        <div class="col-6 col-xl"><div class="small text-muted">Collected</div><div class="fw-bold text-success"><?= e(money($totals['collected'] ?? 0)) ?></div></div>
        <div class="col-12 col-xl"><div class="small text-muted">Outstanding</div><div class="fw-bold text-danger"><?= e(money($totals['outstanding'] ?? 0)) ?></div></div>
      </div>
    </div>
  </section>
  <section class="card border-0 shadow-sm mb-4"><div class="card-body p-0">
    <div class="p-3 p-md-4 border-bottom"><h2 class="h5 fw-bold mb-1">Rental history</h2><p class="small text-muted mb-0">Latest 100 bookings from your fleet for this customer.</p></div>
    <?php if (!$history): ?><div class="p-4 text-muted">No rental history found.</div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Booking</th><th>Vehicle / driver</th><th>Dates</th><th>Status</th><th>Invoice / paid</th></tr></thead><tbody>
      <?php foreach ($history as $b): $total = $b['total_amount'] === null ? null : (float)$b['total_amount']; $due = $total === null ? null : max(0, round($total - (float)$b['amount_paid'], 2)); ?>
      <tr><td><strong><?= e(invoice_no((int)$b['id'])) ?></strong><div class="small text-muted"><?= e($b['booking_date']) ?></div></td><td><strong><?= e($b['car_name']) ?></strong><div class="small text-muted"><?= e($b['car_nameplate']) ?> · <?= e($b['driver_name'] ?? '—') ?></div></td><td><div class="small"><?= e($b['rent_start_date']) ?></div><div class="small text-muted">to <?= e($b['car_return_date'] ?: $b['rent_end_date']) ?></div></td><td><span class="badge <?= e($statusClass((string)$b['booking_status'])) ?>"><?= e(ucfirst((string)$b['booking_status'])) ?></span><div class="small text-muted mt-1"><?= e(ucfirst((string)$b['payment_status'])) ?></div></td><td><?php if ($total !== null && $b['booking_status'] === 'completed'): ?><a href="<?= e(url('printbill.php?id=' . (int)$b['id'])) ?>">View invoice</a><div class="small fw-semibold"><?= e(money($total)) ?></div><?php else: ?><span class="text-muted">Not finalised</span><?php endif; ?><div class="small text-success">Paid: <?= e(money($b['amount_paid'])) ?></div><?php if ($due !== null && $due > 0): ?><div class="small text-danger">Due: <?= e(money($due)) ?></div><?php endif; ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></section>
  <?php endif; ?>

  <section class="card border-0 shadow-sm"><div class="card-body p-0">
    <div class="p-3 p-md-4 border-bottom d-flex justify-content-between gap-2"><div><h2 class="h5 fw-bold mb-1">Customer directory</h2><p class="small text-muted mb-0">Customer records are read-only here; account details remain customer-managed.</p></div><span class="badge text-bg-light align-self-start"><?= count($customers) ?> shown</span></div>
    <?php if (!$customers): ?><div class="p-5 text-center"><i class="fa-solid fa-users fa-2x text-muted mb-3"></i><h3 class="h6 fw-bold">No matching customers</h3><p class="text-muted mb-0">Customers appear here after they book one of your vehicles.</p></div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Customer</th><th>Contact</th><th>Bookings</th><th>Last booking</th><th></th></tr></thead><tbody>
      <?php foreach ($customers as $c): ?><tr><td><strong><?= e($c['customer_name']) ?></strong><div class="small text-muted">@<?= e($c['customer_username']) ?></div></td><td><div><?= e($c['customer_phone'] ?: '—') ?></div><div class="small text-muted text-break"><?= e($c['customer_email'] ?: '—') ?></div></td><td><strong><?= (int)$c['rental_count'] ?></strong><div class="small text-muted"><?= (int)$c['completed_count'] ?> completed</div></td><td><?= e($c['last_booking'] ?: '—') ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin-customers.php?' . http_build_query(array_filter(['q' => $q, 'customer' => $c['customer_username']], static fn($v) => $v !== '')))) ?>">View history</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></section>
</div>
<?php render_footer();