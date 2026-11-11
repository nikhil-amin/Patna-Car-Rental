<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$sessionUser = current_user();
if (!$sessionUser || !in_array($sessionUser['role'], ['customer', 'admin'], true)) {
    require_role('customer');
}
$user = $sessionUser;
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid booking reference.'); }

if (is_post()) {
    if ($user['role'] !== 'customer') { http_response_code(403); exit('Only the renting customer can submit return details.'); }
    csrf_check();
    try {
        db_transaction(function () use ($id, $user): void {
            $b = db_one('SELECT id, customer_username, rent_start_date, rent_end_date, fare, charge_type, booking_status, return_status FROM rentedcars WHERE id = ? FOR UPDATE', [$id]);
            if (!$b || $b['customer_username'] !== $user['id']) throw new DomainException('Booking not found.');
            if ($b['return_status'] !== 'NR' || $b['booking_status'] !== 'active') {
                throw new DomainException('Only an active trip can be returned. Ask the fleet owner to start the trip first.');
            }
            if ((string)$b['rent_start_date'] > date('Y-m-d')) {
                throw new DomainException('This rental cannot be returned before its pickup date.');
            }
            $days = rental_days((string)$b['rent_start_date'], (string)$b['rent_end_date']);
            $distance = null;
            if ($b['charge_type'] === 'km') {
                $raw = $_POST['distance'] ?? '';
                if (!is_string($raw) || !is_numeric($raw) || (float)$raw <= 0 || (float)$raw > 100000) throw new DomainException('Enter a valid trip distance between 0.1 and 100,000 km.');
                $distance = round((float)$raw, 1);
                if ($distance < 0.1) {
                    throw new DomainException('Trip distance must be at least 0.1 km.');
                }
                $base = round((float)$b['fare'] * $distance, 2);
            } elseif ($b['charge_type'] === 'days') {
                $base = round((float)$b['fare'] * $days, 2);
            } else throw new DomainException('The saved fare type is invalid. Contact support.');
            $today = date('Y-m-d');
            $lateDays = max(0, (int)(new DateTimeImmutable((string)$b['rent_end_date']))->diff(new DateTimeImmutable($today))->format('%r%a'));
            $lateFee = round($lateDays * 200, 2);
            $total = round($base + $lateFee, 2);
            db_exec("UPDATE rentedcars SET car_return_date = ?, return_status = 'R', booking_status = 'completed',
                     payment_status = 'unpaid', amount_paid = 0, no_of_days = ?, distance = ?, late_fee = ?, total_amount = ?
                     WHERE id = ? AND customer_username = ? AND return_status = 'NR'",
                [$today, $days, $distance, $lateFee, $total, $id, $user['id']]);
        });
        flash('success', 'Rental returned. Your final invoice is ready.');
        redirect(url('printbill.php?id=' . $id));
    } catch (DomainException $e) {
        flash('warning', $e->getMessage());
        redirect(url('prereturncar.php'));
    } catch (Throwable $e) {
        error_log((string)$e);
        flash('error', 'We could not complete the return. Please try again or contact support.');
        redirect(url('prereturncar.php'));
    }
}

if ($user['role'] === 'customer') {
    $b = db_one(
        "SELECT r.id, r.customer_username, r.rent_start_date, r.rent_end_date, r.car_return_date, r.fare, r.charge_type,
                r.booking_status, r.return_status, r.no_of_days, r.distance, r.late_fee, r.total_amount, r.payment_status,
                r.amount_paid, c.car_name, c.car_nameplate, d.driver_name, cu.customer_name
         FROM rentedcars r JOIN cars c ON c.car_id = r.car_id JOIN driver d ON d.driver_id = r.driver_id
         JOIN customers cu ON cu.customer_username = r.customer_username
         WHERE r.id = ? AND r.customer_username = ? LIMIT 1", [$id, $user['id']]
    );
} else {
    $b = db_one(
        "SELECT r.id, r.customer_username, r.rent_start_date, r.rent_end_date, r.car_return_date, r.fare, r.charge_type,
                r.booking_status, r.return_status, r.no_of_days, r.distance, r.late_fee, r.total_amount, r.payment_status,
                r.amount_paid, c.car_name, c.car_nameplate, d.driver_name, cu.customer_name
         FROM rentedcars r JOIN cars c ON c.car_id = r.car_id JOIN driver d ON d.driver_id = r.driver_id
         JOIN customers cu ON cu.customer_username = r.customer_username
         JOIN clientcars cc ON cc.car_id = r.car_id
         WHERE r.id = ? AND cc.client_username = ? LIMIT 1", [$id, $user['id']]
    );
}
if (!$b) { http_response_code(404); exit('Invoice not found.'); }
if ($b['return_status'] !== 'R' || $b['booking_status'] !== 'completed' || $b['total_amount'] === null) {
    if ($user['role'] === 'customer') {
        flash('info', 'Submit your trip details to calculate the final invoice.');
        redirect(url('returncar.php?id=' . $id));
    }
    http_response_code(409); exit('The final invoice is not available yet.');
}
$activeNav = $user['role'] === 'admin' ? 'rentals' : 'history';
render_header('Invoice ' . invoice_no((int)$b['id']), $activeNav);
$paid = (float)($b['amount_paid'] ?? 0);
$due = max(0, round((float)$b['total_amount'] - $paid, 2));
?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div><div class="text-success small fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Rental returned</div><h1 class="h3 fw-bold mb-1">Final invoice</h1><p class="text-muted mb-0">Reference <?= e(invoice_no((int)$b['id'])) ?></p></div>
    <div class="d-flex gap-2"><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Print invoice</button><a class="btn btn-brand" href="<?= e(url($user['role']==='admin'?'admin-rentals.php':'mybookings.php')) ?>"><?= $user['role']==='admin'?'Back to rentals':'My bookings' ?></a></div>
  </div>
  <article class="card border-0 shadow-sm invoice-paper"><div class="card-body p-4 p-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 border-bottom pb-4 mb-4">
      <div><div class="text-muted small">BILLED BY</div><h2 class="h4 fw-bold mb-1"><?= e((string)config('APP_NAME','DriveEase')) ?></h2><div class="text-muted">Car rental service</div></div>
      <div class="text-md-end"><div class="text-muted small">INVOICE NUMBER</div><div class="h5 fw-bold"><?= e(invoice_no((int)$b['id'])) ?></div><div class="text-muted small">Issued <?= e($b['car_return_date']) ?></div></div>
    </div>
    <h3 class="h5 fw-bold"><?= e($b['car_name']) ?> <span class="text-muted fw-normal">· <?= e($b['car_nameplate']) ?></span></h3>
    <div class="row g-3 mt-2 mb-4">
      <div class="col-6 col-md-3"><div class="small text-muted">Customer</div><strong><?= e($b['customer_name']) ?></strong></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Pickup date</div><strong><?= e($b['rent_start_date']) ?></strong></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Scheduled return</div><strong><?= e($b['rent_end_date']) ?></strong></div>
      <div class="col-6 col-md-3"><div class="small text-muted">Actual return</div><strong><?= e($b['car_return_date']) ?></strong></div>
    </div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Description</th><th class="text-end">Calculation</th><th class="text-end">Amount</th></tr></thead><tbody>
      <tr><td>Rental charges</td><td class="text-end"><?= e($b['charge_type']==='days'?(string)$b['no_of_days'].' day(s) × '.money($b['fare']):(string)$b['distance'].' km × '.money($b['fare'])) ?></td><td class="text-end"><?= e(money((float)$b['total_amount']-(float)$b['late_fee'])) ?></td></tr>
      <?php if ((float)$b['late_fee']>0): ?><tr><td>Late return fee</td><td class="text-end">₹200 × <?= e((string)((float)$b['late_fee']/200)) ?> day(s)</td><td class="text-end"><?= e(money($b['late_fee'])) ?></td></tr><?php endif; ?>
    </tbody><tfoot><tr><th colspan="2" class="text-end">Invoice total</th><th class="text-end fs-5"><?= e(money($b['total_amount'])) ?></th></tr><tr><td colspan="2" class="text-end">Amount paid</td><td class="text-end text-success"><?= e(money($paid)) ?></td></tr><tr><th colspan="2" class="text-end">Balance due</th><th class="text-end <?= $due>0?'text-danger':'text-success' ?>"><?= e(money($due)) ?></th></tr></tfoot></table></div>
    <div class="text-muted small mt-3">Payment status: <strong><?= e(ucfirst((string)$b['payment_status'])) ?></strong>. This invoice reflects the saved booking fare and recorded trip details.</div>
  </div></article>
</div>
<style>@media print { nav, footer, .btn, a { display:none!important } body { background:#fff!important } .invoice-paper { box-shadow:none!important; border:1px solid #ddd!important } .container { max-width:100%!important; width:100%!important } }</style>
<?php render_footer();