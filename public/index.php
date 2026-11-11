<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

$categories = car_categories();
$featured = cars_search(['sort' => 'price_asc', 'per_page' => 3])['rows'];
$today = date('Y-m-d');
$icons = ['Hatchback' => 'fa-car-side', 'Sedan' => 'fa-car', 'SUV' => 'fa-truck-monster', 'MPV' => 'fa-van-shuttle'];

render_header('Find your next drive', 'home');
?>
<section class="de-hero">
  <div class="de-hero-image" aria-hidden="true"></div>
  <div class="container de-hero-content">
    <div class="row align-items-center min-vh-75">
      <div class="col-lg-8 col-xl-7 py-5">
        <div class="de-eyebrow"><span></span> THE ROAD IS YOURS</div>
        <h1>Make every<br><em>mile</em> matter.</h1>
        <p class="de-hero-copy">The right car changes the whole journey. Discover your ride, choose your dates, and get moving with DriveEase.</p>
        <div class="d-flex flex-wrap gap-3 align-items-center mt-4">
          <a class="btn btn-warning btn-lg px-4 fw-bold" href="<?= e(url('cars.php')) ?>">Explore the fleet <i class="fa-solid fa-arrow-up-right-from-square ms-2" aria-hidden="true"></i></a>
          <a class="de-text-link" href="<?= e(url('how-it-works.php')) ?>">How it works <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a>
        </div>
        <div class="de-hero-proof mt-5"><div class="de-proof-mark"><i class="fa-solid fa-route" aria-hidden="true"></i></div><div><strong>Built around your journey</strong><span>Clear rates. Real availability. A simpler booking flow.</span></div></div>
      </div>
      <div class="col-lg-4 col-xl-5 d-none d-lg-block"><div class="de-hero-stamp"><span>DRIVE</span><strong>YOUR<br>WAY.</strong><i class="fa-solid fa-arrow-down-right" aria-hidden="true"></i></div></div>
    </div>
  </div>
  <div class="de-hero-bottom"><span>01 — DISCOVER</span><span>FIND YOUR KIND OF DRIVE <i class="fa-solid fa-arrow-down ms-2" aria-hidden="true"></i></span></div>
</section>
<section class="container de-search-wrap" aria-label="Find a car">
  <div class="de-search-panel">
    <div class="de-search-heading"><span class="de-section-kicker">START HERE</span><h2>Where are you headed?</h2><p>Choose your dates and we’ll help you find the right ride.</p></div>
    <form action="<?= e(url('cars.php')) ?>" method="get" class="row g-3 align-items-end">
      <div class="col-6 col-lg-3"><label class="form-label" for="start"><i class="fa-regular fa-calendar me-2" aria-hidden="true"></i>Pickup date</label><input type="date" class="form-control" id="start" name="start" min="<?= e($today) ?>" value="<?= e($today) ?>" data-date-start="#end" required></div>
      <div class="col-6 col-lg-3"><label class="form-label" for="end"><i class="fa-regular fa-calendar-check me-2" aria-hidden="true"></i>Return date</label><input type="date" class="form-control" id="end" name="end" min="<?= e($today) ?>" value="<?= e($today) ?>" required></div>
      <div class="col-12 col-lg-3"><label class="form-label" for="category"><i class="fa-solid fa-car-side me-2" aria-hidden="true"></i>Vehicle type</label><select class="form-select" id="category" name="category"><option value="">Every category</option><?php foreach ($categories as $c): ?><option value="<?= e($c['category']) ?>"><?= e($c['category']) ?></option><?php endforeach; ?></select></div>
      <div class="col-12 col-lg-3 d-grid"><button class="btn btn-warning fw-bold py-3" type="submit">Find my car <i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></button></div>
    </form>
  </div>
</section>
<section class="container de-section de-categories">
  <div class="de-section-head"><div><span class="de-section-kicker">PICK YOUR VIBE</span><h2>Every trip has a <em>type.</em></h2></div><p>From quick city runs to long-way-round weekends, start with what fits.</p></div>
  <div class="row g-3"><?php foreach ($categories as $c): ?><div class="col-6 col-lg-3"><a class="de-category-card" href="<?= e(url('cars.php?category=' . urlencode($c['category']))) ?>"><div class="de-category-icon"><i class="fa-solid <?= e($icons[$c['category']] ?? 'fa-car') ?>" aria-hidden="true"></i></div><div><span><?= (int) $c['n'] ?> vehicles</span><h3><?= e($c['category']) ?></h3></div><i class="fa-solid fa-arrow-up-right-from-square de-category-arrow" aria-hidden="true"></i></a></div><?php endforeach; ?></div>
</section>
<section class="de-fleet-section"><div class="container de-section">
  <div class="de-section-head"><div><span class="de-section-kicker">THE LINEUP</span><h2>Find your <em>next favourite.</em></h2></div><a class="de-text-link" href="<?= e(url('cars.php')) ?>">View all cars <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
  <?php if ($featured): ?><div class="row g-4"><?php foreach ($featured as $car) { include __DIR__ . '/../includes/car_card.php'; } ?></div><?php else: ?><div class="de-empty-fleet"><i class="fa-solid fa-car-side" aria-hidden="true"></i><h3>The next ride is on its way.</h3><p>Our fleet will show here as vehicles become available.</p></div><?php endif; ?>
</div></section>
<section class="container de-section de-process">
  <div class="de-section-head"><div><span class="de-section-kicker">NO DETOURS</span><h2>Good to go in <em>four steps.</em></h2></div><a class="de-text-link" href="<?= e(url('how-it-works.php')) ?>">See how it works <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a></div>
  <div class="row g-3"><?php foreach ([['01','Discover','Find a car that fits your plans.','fa-magnifying-glass'],['02','Choose','Check dates, rates and vehicle details.','fa-calendar-check'],['03','Book','Sign in and send your booking details.','fa-key'],['04','Go','Track your rental and review your invoice.','fa-road']] as [$n,$title,$desc,$icon]): ?><div class="col-6 col-lg-3"><article class="de-step-card"><span class="de-step-number"><?= e($n) ?></span><i class="fa-solid <?= e($icon) ?>" aria-hidden="true"></i><h3><?= e($title) ?></h3><p><?= e($desc) ?></p></article></div><?php endforeach; ?></div>
</section>
<section class="container de-section"><div class="de-owner-cta"><div class="de-owner-art" aria-hidden="true"><i class="fa-solid fa-key"></i><span>YOUR FLEET.<br>YOUR CALL.</span></div><div class="de-owner-copy"><span class="de-section-kicker">FOR FLEET OWNERS</span><h2>More control.<br><em>Less running around.</em></h2><p>Manage vehicles, drivers, bookings and payment records from one dedicated workspace.</p><a class="btn btn-warning fw-bold px-4" href="<?= e(url('login.php?role=admin')) ?>">Open fleet workspace <i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></a></div></div></section>
<section class="container de-section de-contact-cta"><div><span class="de-section-kicker">LET'S GET YOU MOVING</span><h2>Your next story starts <em>out there.</em></h2><p>Got a question before you hit the road? We’re here to help.</p></div><div class="d-flex flex-wrap gap-3"><a class="btn btn-warning btn-lg fw-bold px-4" href="<?= e(url('cars.php')) ?>">Browse cars <i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></a><a class="btn btn-outline-light btn-lg px-4" href="<?= e(url('contact.php')) ?>">Talk to us</a></div></section>
<?php render_footer();
