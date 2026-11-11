<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

if (is_post()) {
    csrf_check();
    [$errors, $c] = validate($_POST, [
        'name' => 'required|min:2|max:100',
        'email' => 'required|email|max:100',
        'message' => 'required|min:5|max:1000',
    ]);
    if ($errors) {
        flash('danger', implode(' ', $errors));
        redirect(url('contact.php'));
    }

    db_exec('INSERT INTO feedback (name, e_mail, message) VALUES (?, ?, ?)', [$c['name'], $c['email'], $c['message']]);
    flash('success', 'Thanks! Your message has been sent.');
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    redirect(str_contains($referer, '#contact') ? url('index.php#contact') : url('contact.php'));
}

render_header('Contact & support', 'contact');
?>
<section class="container py-5">
  <div class="row g-4 align-items-start">
    <div class="col-lg-5">
      <span class="badge text-bg-warning mb-3">WE'RE HERE TO HELP</span>
      <h1 class="display-6 fw-bold mb-3">Let's get you moving.</h1>
      <p class="lead text-muted">Questions about finding a car, a booking, or using DriveEase? Send us a message and include enough detail for us to understand your request.</p>
      <div class="card border-0 shadow-sm mt-4"><div class="card-body p-4">
        <div class="d-flex gap-3 mb-3"><span class="text-brand fs-4"><i class="fa-solid fa-car-side" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Booking help</h2><p class="small text-muted mb-0">Browse available vehicles and review the booking details before confirming.</p></div></div>
        <div class="d-flex gap-3"><span class="text-brand fs-4"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><div><h2 class="h6 fw-bold mb-1">Account support</h2><p class="small text-muted mb-0">Never include your password or sensitive payment information in a message.</p></div></div>
      </div></div>
    </div>
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm"><div class="card-body p-4 p-md-5">
        <h2 class="h4 fw-bold mb-1">Send us a message</h2>
        <p class="small text-muted mb-4">Fields marked with * are required.</p>
        <form method="post" action="<?= e(url('contact.php')) ?>" data-once>
          <?= csrf_field() ?>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="name">Full name *</label><input class="form-control" id="name" name="name" maxlength="100" autocomplete="name" required></div>
            <div class="col-md-6"><label class="form-label" for="email">Email address *</label><input class="form-control" id="email" name="email" type="email" maxlength="100" autocomplete="email" required></div>
            <div class="col-12"><label class="form-label" for="message">How can we help? *</label><textarea class="form-control" id="message" name="message" rows="5" maxlength="1000" placeholder="Tell us a little about your question..." required></textarea><div class="form-text">Please don't share passwords, card numbers or other sensitive details.</div></div>
            <div class="col-12 d-grid d-sm-flex justify-content-sm-end"><button class="btn btn-brand px-4" type="submit"><i class="fa-solid fa-paper-plane me-2" aria-hidden="true"></i>Send message</button></div>
          </div>
        </form>
      </div></div>
    </div>
  </div>
</section>
<?php render_footer();
