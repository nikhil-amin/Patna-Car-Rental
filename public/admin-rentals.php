<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$admin = require_role('admin');

if (is_post()) {
    csrf_check();

    // Start a confirmed trip only when its scheduled pickup date has arrived.
    if (($_POST['action'] ?? '') === 'start_trip') {
        $startBookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        try {
            if (!$startBookingId || $startBookingId < 1) {
                throw new DomainException('Invalid booking reference.');
            }
            db_transaction(function () use ($admin, $startBookingId): void {
                $booking = db_one(
                    "SELECT r.id, r.rent_start_date, r.booking_status, r.return_status
                     FROM rentedcars r
                     JOIN clientcars cc ON cc.car_id = r.car_id
                     WHERE r.id = ? AND cc.client_username = ? FOR UPDATE",
                    [$startBookingId, $admin['id']]
                );
                if (!$booking) {
                    throw new DomainException('Booking not found in your fleet.');
                }
                if ($booking['booking_status'] !== 'confirmed' || $booking['return_status'] !== 'NR') {
                    throw new DomainException('Only confirmed, not-yet-returned bookings can be started.');
                }
                if ((string) $booking['rent_start_date'] > date('Y-m-d')) {
                    throw new DomainException('You can start this trip on or after its scheduled pickup date.');
                }
                db_exec(
                    "UPDATE rentedcars SET booking_status = 'active' WHERE id = ? AND booking_status = 'confirmed'",
                    [$startBookingId]
                );
            });
            flash('success', 'Trip started. The booking is now marked active.');
        } catch (DomainException $e) {
            flash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('DriveEase trip start failed: ' . $e->getMessage());
            flash('error', 'The trip could not be started. Please try again.');
        }
        redirect(url('admin-rentals.php'));
    }

    $bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $amountRaw = $_POST['amount'] ?? '';
    $method = (string)($_POST['method'] ?? 'cash');
    $reference = trim((string)($_POST['reference'] ?? ''));
    $methods = ['cash', 'upi', 'card', 'bank_transfer', 'other'];

    try {
        if (!$bookingId || $bookingId < 1) throw new DomainException('Invalid booking reference.');
        if (!is_string($amountRaw) || !is_numeric($amountRaw) || (float)$amountRaw <= 0 || (float)$amountRaw > 100000000) {
            throw new DomainException('Enter a valid payment amount greater than zero.');
        }
        if (!in_array($method, $methods, true)) throw new DomainException('Choose a valid payment method.');
        if (mb_strlen($reference) > 80) throw new DomainException('Payment reference must be 80 characters or fewer.');

        db_transaction(function () use ($admin, $bookingId, $amountRaw, $method, $reference): void {
            $b = db_one(
                "SELECT r.id, r.total_amount, r.amount_paid, r.booking_status, r.return_status
                 FROM rentedcars r JOIN clientcars cc ON cc.car_id = r.car_id
                 WHERE r.id = ? AND cc.client_username = ? FOR UPDATE",
                [$bookingId, $admin['id']]
            );
            if (!$b) throw new DomainException('Booking not found in your fleet.');
            if ($b['booking_status'] !== 'completed' || $b['return_status'] !== 'R' || $b['total_amount'] === null) {
                throw new DomainException('Payments can only be recorded for completed rentals with a final invoice.');
            }
            $total = round((float)$b['total_amount'], 2);
            $paid = round((float)$b['amount_paid'], 2);
            $outstanding = round($total - $paid, 2);
            $amount = round((float)$amountRaw, 2);
            if ($amount <= 0) throw new DomainException('Payment must be at least ₹0.01 after rounding.');
            if ($outstanding <= 0) throw new DomainException('This invoice is already fully paid.');
            if ($amount > $outstanding) throw new DomainException('Payment exceeds the outstanding balance of ' . money($outstanding) . '.');

            db_exec(
                "INSERT INTO payments (booking_id, amount, method, reference, status, recorded_by, paid_at)
                 VALUES (?, ?, ?, ?, 'success', ?, NOW())",
                [$bookingId, $amount, $method, $reference !== '' ? $reference : null, $admin['id']]
            );
            $newPaid = round($paid + $amount, 2);
            $status = $newPaid >= $total ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
            db_exec('UPDATE rentedcars SET amount_paid = ?, payment_status = ? WHERE id = ?', [$newPaid, $status, $bookingId]);
        });
        flash('success', 'Payment recorded and outstanding balance updated.');
    } catch (DomainException $e) {
        flash('warning', $e->getMessage());
    } catch (Throwable $e) {
        error_log((string)$e);
        flash('error', 'Payment could not be recorded. Please try again.');
    }
    redirect(url('admin-rentals.php'));
}

$summary = db_one(
    "SELECT COUNT(*) AS total_rentals,
            SUM(CASE WHEN r.booking_status = 'completed' THEN 1 ELSE 0 END) AS completed_rentals,
            COALESCE(SUM(CASE WHEN r.booking_status = 'completed' THEN r.total_amount ELSE 0 END), 0) AS invoiced,
            COALESCE(SUM(CASE WHEN r.booking_status = 'completed' THEN r.amount_paid ELSE 0 END), 0) AS collected,
            COALESCE(SUM(CASE WHEN r.booking_status = 'completed' THEN GREATEST(COALESCE(r.total_amount,0)-r.amount_paid,0) ELSE 0 END), 0) AS outstanding
     FROM rentedcars r JOIN clientcars cc ON cc.car_id = r.car_id
     WHERE cc.client_username = ?",
    [$admin['id']]
) ?? [];
$rentals = db_all(
    "SELECT r.id, r.booking_date, r.rent_start_date, r.rent_end_date, r.car_return_date, r.booking_status,
            r.return_status, r.payment_status, r.total_amount, r.amount_paid, r.late_fee, r.charge_type,
            r.fare, r.distance, r.no_of_days, r.customer_username, cu.customer_name, cu.customer_phone,
            c.car_name, c.car_nameplate, d.driver_name
     FROM rentedcars r
     JOIN clientcars cc ON cc.car_id = r.car_id
     JOIN cars c ON c.car_id = r.car_id
     JOIN customers cu ON cu.customer_username = r.customer_username
     LEFT JOIN driver d ON d.driver_id = r.driver_id
     WHERE cc.client_username = ?
     ORDER BY CASE WHEN r.booking_status = 'completed' AND r.payment_status <> 'paid' THEN 0 ELSE 1 END,
              r.booking_date DESC, r.id DESC LIMIT 150",
    [$admin['id']]
);
$payments = db_all(
    "SELECT p.id, p.booking_id, p.amount, p.method, p.reference, p.paid_at, p.recorded_by,
            cu.customer_name, c.car_name
     FROM payments p JOIN rentedcars r ON r.id = p.booking_id
     JOIN clientcars cc ON cc.car_id = r.car_id
     JOIN customers cu ON cu.customer_username = r.customer_username
     JOIN cars c ON c.car_id = r.car_id
     WHERE cc.client_username = ? AND p.status = 'success'
     ORDER BY p.paid_at DESC, p.id DESC LIMIT 10",
    [$admin['id']]
);
render_header('Rentals & payments', 'rentals');
?>
<div class="container py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
    <div><div class="text-uppercase small fw-semibold text-muted">Fleet owner workspace</div><h1 class="h3 fw-bold mb-1">Rentals & payments</h1><p class="text-muted mb-0">Monitor completed trips, record received payments and track outstanding invoices.</p></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-brand" href="<?= e(url('export-rentals.php')) ?>"><i class="fa-solid fa-file-csv me-2"></i>Export CSV</a><a class="btn btn-outline-secondary" href="<?= e(url('admin-reports.php')) ?>"><i class="fa-solid fa-chart-column me-2"></i>Fleet reports</a><a class="btn btn-outline-secondary" href="<?= e(url('admin-customers.php')) ?>"><i class="fa-solid fa-users me-2"></i>Customers</a><a class="btn btn-outline-secondary" href="<?= e(url('admin-calendar.php')) ?>"><i class="fa-solid fa-calendar-days me-2"></i>Booking calendar</a><a class="btn btn-outline-secondary" href="<?= e(url('entercar.php')) ?>"><i class="fa-solid fa-car me-2"></i>Manage vehicles</a><a class="btn btn-outline-secondary" href="<?= e(url('enterdriver.php')) ?>"><i class="fa-solid fa-id-card me-2"></i>Manage drivers</a></div>
  </div>
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Total rentals</div><div class="fs-3 fw-bold"><?= (int)($summary['total_rentals'] ?? 0) ?></div></div></div></div>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Completed</div><div class="fs-3 fw-bold"><?= (int)($summary['completed_rentals'] ?? 0) ?></div></div></div></div>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Total invoiced</div><div class="fs-5 fw-bold"><?= e(money($summary['invoiced'] ?? 0)) ?></div></div></div></div>
    <div class="col-6 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Collected</div><div class="fs-5 fw-bold text-success"><?= e(money($summary['collected'] ?? 0)) ?></div></div></div></div>
    <div class="col-12 col-xl"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted">Outstanding balance</div><div class="fs-5 fw-bold text-danger"><?= e(money($summary['outstanding'] ?? 0)) ?></div></div></div></div>
  </div>
  <section class="card border-0 shadow-sm mb-4"><div class="card-body p-0">
    <div class="p-3 p-md-4 border-bottom"><h2 class="h5 fw-bold mb-1">Rental ledger</h2><p class="small text-muted mb-0">Showing up to 150 most recent rentals from your own fleet.</p></div>
    <?php if (!$rentals): ?><div class="p-5 text-center"><i class="fa-solid fa-receipt fa-2x text-muted mb-3"></i><h3 class="h6 fw-bold">No rentals yet</h3><p class="text-muted mb-0">Bookings for your fleet will appear here.</p></div>
    <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>Booking</th><th>Customer / car</th><th>Trip</th><th>Status</th><th>Invoice</th><th>Paid / due</th><th>Action</th></tr></thead><tbody>
      <?php foreach ($rentals as $r): $total = $r['total_amount'] === null ? null : (float)$r['total_amount']; $paid = (float)$r['amount_paid']; $due = $total === null ? null : max(0, round($total-$paid,2)); ?>
        <tr>
          <td><strong><?= e(invoice_no((int)$r['id'])) ?></strong><div class="small text-muted"><?= e((string)$r['booking_date']) ?></div></td>
          <td><strong><?= e($r['customer_name']) ?></strong><div class="small text-muted"><?= e($r['car_name']) ?> · <?= e($r['car_nameplate']) ?></div><div class="small text-muted"><?= e($r['customer_phone'] ?? '') ?></div></td>
          <td><div class="small"><?= e($r['rent_start_date']) ?></div><div class="small text-muted">to <?= e($r['car_return_date'] ?: $r['rent_end_date']) ?></div><div class="small text-muted">Driver: <?= e($r['driver_name'] ?? '—') ?></div></td>
          <td><span class="badge <?= $r['booking_status']==='completed'?'text-bg-success':($r['booking_status']==='cancelled'?'text-bg-secondary':'text-bg-primary') ?>"><?= e(ucfirst((string)$r['booking_status'])) ?></span><div class="small mt-1"><?= e(ucfirst((string)$r['payment_status'])) ?></div></td>
          <td><?php if ($total !== null): ?><strong><?= e(money($total)) ?></strong><?php else: ?><span class="text-muted">Not finalised</span><?php endif; ?><?php if ((float)$r['late_fee']>0): ?><div class="small text-muted">Late fee <?= e(money($r['late_fee'])) ?></div><?php endif; ?>
            <?php if ($r['booking_status']==='completed' && $total!==null): ?><div><a class="small" href="<?= e(url('printbill.php?id='.(int)$r['id'])) ?>">View invoice</a></div><?php endif; ?></td>
          <td><strong><?= e(money($paid)) ?></strong><?php if ($due!==null): ?><div class="small <?= $due>0?'text-danger':'text-success' ?>"><?= $due>0 ? e(money($due)).' due' : 'Settled' ?></div><?php endif; ?></td>
          <td style="min-width:245px"><?php if ($r['booking_status']==='confirmed' && $r['return_status']==='NR' && (string)$r['rent_start_date'] <= date('Y-m-d')): ?>
            <form method="post" data-once><?= csrf_field() ?><input type="hidden" name="action" value="start_trip"><input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-primary w-100" type="submit" onclick="return confirm('Start this rental now?')"><i class="fa-solid fa-play me-1"></i>Start trip</button></form>
          <?php elseif ($r['booking_status']==='active'): ?><span class="small text-primary"><i class="fa-solid fa-road me-1"></i>Trip in progress</span>
          <?php elseif ($r['booking_status']==='completed' && $total!==null && $due!==null && $due>0): ?>
            <form method="post" class="d-grid gap-2" data-once><?= csrf_field() ?><input type="hidden" name="booking_id" value="<?= (int)$r['id'] ?>">
              <div class="input-group input-group-sm"><span class="input-group-text">₹</span><input class="form-control" type="number" name="amount" min="0.01" max="<?= e(number_format($due,2,'.','')) ?>" step="0.01" placeholder="Amount" aria-label="Payment amount" required></div>
              <select class="form-select form-select-sm" name="method" aria-label="Payment method"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select>
              <input class="form-control form-control-sm" name="reference" maxlength="80" placeholder="Transaction reference (optional)" aria-label="Transaction reference">
              <button class="btn btn-sm btn-brand" type="submit"><i class="fa-solid fa-plus me-1"></i>Record payment</button>
            </form>
          <?php elseif ($due !== null && $due <= 0): ?><span class="small text-success"><i class="fa-solid fa-circle-check me-1"></i>Fully paid</span><?php else: ?><span class="small text-muted">Available after return</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
  </div></section>
  <section class="card border-0 shadow-sm"><div class="card-body p-0">
    <div class="p-3 p-md-4 border-bottom"><h2 class="h5 fw-bold mb-1">Recent payment records</h2><p class="small text-muted mb-0">Latest successful payments recorded against your fleet.</p></div>
    <?php if (!$payments): ?><div class="p-4 text-muted">No payments have been recorded yet.</div><?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Recorded at</th><th>Booking / customer</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th></tr></thead><tbody>
      <?php foreach ($payments as $p): ?><tr><td><?= e($p['paid_at']) ?><div class="small text-muted">By <?= e($p['recorded_by'] ?? '—') ?></div></td><td><strong><?= e(invoice_no((int)$p['booking_id'])) ?></strong><div class="small text-muted"><?= e($p['customer_name']) ?> · <?= e($p['car_name']) ?></div></td><td><?= e(str_replace('_',' ',ucfirst((string)$p['method']))) ?></td><td><?= e($p['reference'] ?: '—') ?></td><td class="text-end fw-semibold"><?= e(money($p['amount'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></section>
</div>
<?php render_footer();