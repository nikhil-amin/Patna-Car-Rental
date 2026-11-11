<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

render_header('Privacy information');
?>
<section class="container py-5" style="max-width:900px">
  <span class="badge text-bg-light mb-3">POLICIES</span>
  <h1 class="display-6 fw-bold mb-3">Privacy information</h1>
  <p class="lead text-muted">This page explains, at a high level, how information is used within this DriveEase project.</p>
  <div class="alert alert-info small" role="note"><strong>Project notice:</strong> DriveEase is an evolving application. Before using it for a real rental business, the operator should review and adapt this notice to the actual hosting, retention, legal and operational requirements.</div>
  <h2 class="h5 fw-bold mt-4">Information you provide</h2>
  <p class="text-muted">Account registration may store details such as your name, username, contact information and a password hash. Booking and rental records may be associated with your account. Messages submitted through the contact form are stored so the operator can review feedback.</p>
  <h2 class="h5 fw-bold mt-4">How information is used</h2>
  <p class="text-muted">Information is used to support account access, booking and fleet operations, rental history, invoices, payment-status records and responses to messages.</p>
  <h2 class="h5 fw-bold mt-4">Passwords and payments</h2>
  <p class="text-muted">Passwords should be stored as hashes, not readable text. Do not send passwords or full payment-card details through the contact form. The application records rental and payment information; this notice does not imply that DriveEase itself processes online card payments.</p>
  <h2 class="h5 fw-bold mt-4">Access and security</h2>
  <p class="text-muted">Use only your own account and sign out on shared devices. Access to account and rental information should be limited by role. No web application can guarantee absolute security, so production operators should regularly review access controls, backups and server configuration.</p>
  <h2 class="h5 fw-bold mt-4">Retention and questions</h2>
  <p class="text-muted">Records may remain in the application's database until the operator removes them or implements a retention process. For questions about data held by this installation, use the <a href="<?= e(url('contact.php')) ?>">contact page</a> to reach its operator.</p>
  <p class="small text-muted mt-5 mb-0">Last updated: <?= date('F Y') ?></p>
</section>
<?php render_footer();
