<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

render_header('Terms of use');
?>
<section class="container py-5" style="max-width:900px">
  <span class="badge text-bg-light mb-3">POLICIES</span>
  <h1 class="display-6 fw-bold mb-3">Terms of use</h1>
  <p class="lead text-muted">These basic terms describe expected use of this DriveEase installation.</p>
  <div class="alert alert-warning small" role="note"><strong>Project notice:</strong> This is a general starting point, not legal advice or a substitute for rental agreements, insurance terms, consumer-law disclosures or local legal review.</div>
  <h2 class="h5 fw-bold mt-4">Accounts</h2>
  <p class="text-muted">Provide accurate information when creating an account, keep your credentials private and use only accounts you are authorised to access. Notify the operator if you suspect unauthorised use.</p>
  <h2 class="h5 fw-bold mt-4">Bookings and vehicle information</h2>
  <p class="text-muted">Review vehicle details, dates, rates and booking information before submitting a request. Availability and booking status are based on records maintained by the operator. A booking in the application should not be treated as confirmation of terms that have not been shown to you.</p>
  <h2 class="h5 fw-bold mt-4">Rental, return and payment records</h2>
  <p class="text-muted">Customers and fleet owners should verify rental dates, return details, recorded distance, applicable charges and invoice totals. Payment methods and any additional rental conditions must be agreed with the fleet operator; this application does not itself guarantee payment processing.</p>
  <h2 class="h5 fw-bold mt-4">Acceptable use</h2>
  <p class="text-muted">Do not attempt to bypass role-based access, interfere with the application, submit misleading information or access another person's records. The operator may suspend access to protect the service or its users.</p>
  <h2 class="h5 fw-bold mt-4">Availability and changes</h2>
  <p class="text-muted">The application is provided as-is and may change while being maintained. The operator should verify the application and all rental arrangements before relying on it for real-world transactions.</p>
  <h2 class="h5 fw-bold mt-4">Questions</h2>
  <p class="text-muted">For questions about this installation, visit the <a href="<?= e(url('contact.php')) ?>">contact page</a>.</p>
  <p class="small text-muted mt-5 mb-0">Last updated: <?= date('F Y') ?></p>
</section>
<?php render_footer();
