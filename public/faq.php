<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$faqs = [
    ['How do I book a car?', 'Search by dates on the home page, open a car, check availability and press "Book this car". You need a customer account.'],
    ['How is the fare calculated?', 'Day-rate bookings are charged per day. Per-km bookings are charged on the distance recorded at return. AC and non-AC rates differ.'],
    ['Do I get a driver?', 'Yes. Every booking is assigned a verified driver from the fleet owner of the car you choose.'],
    ['Can I cancel a booking?', 'Yes, from your bookings page before the pickup date. Cancelled bookings free the car for other customers.'],
    ['What if I return the car late?', 'A late fee of ₹200 per extra day is added to the final invoice.'],
    ['How do I pay?', 'Payment is recorded against your booking by the fleet owner (cash, UPI, card or bank transfer). Your invoice shows the payment status.'],
];
render_header('FAQ', 'faq');
?>
<div class="container py-4" style="max-width:820px">
  <h1 class="h3 fw-bold mb-3">Frequently asked questions</h1>
  <div class="accordion shadow-sm" id="faq">
    <?php foreach ($faqs as $i => [$q, $a]): ?>
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#f<?= $i ?>" aria-expanded="<?= $i ? 'false' : 'true' ?>"><?= e($q) ?></button></h2>
        <div id="f<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faq"><div class="accordion-body"><?= e($a) ?></div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php render_footer();
