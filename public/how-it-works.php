<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

render_header('How it works', 'how-it-works');
?>
<section class="container py-5">
  <div class="text-center mx-auto mb-5" style="max-width:760px">
    <span class="badge text-bg-warning mb-3">SIMPLE BY DESIGN</span>
    <h1 class="display-5 fw-bold">From browsing to booking, made clearer.</h1>
    <p class="lead text-muted">Explore the customer journey and see how fleet owners manage the rental lifecycle in DriveEase.</p>
  </div>
  <h2 class="h4 fw-bold mb-4">For customers</h2>
  <div class="row g-3 mb-5">
    <?php foreach ([['01','Find your car','Browse the fleet, compare available details and use filters to narrow your options.','fa-magnifying-glass'],['02','Choose your dates','Open a vehicle and review rental details, dates and applicable rates before continuing.','fa-calendar-days'],['03','Create a booking','Sign in or create a customer account, then submit the booking details.','fa-clipboard-check'],['04','Track and return','Use your bookings and active-rental pages to follow the rental and review the final invoice.','fa-key']] as [$n,$title,$desc,$icon]): ?>
      <div class="col-sm-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-4"><span class="display-6 fw-bold text-brand"><?= e($n) ?></span><i class="fa-solid <?= e($icon) ?> fs-3 text-muted" aria-hidden="true"></i></div><h3 class="h6 fw-bold"><?= e($title) ?></h3><p class="small text-muted mb-0"><?= e($desc) ?></p></div></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card border-0 bg-light mb-5"><div class="card-body p-4 p-md-5">
    <div class="row align-items-center g-4"><div class="col-lg-8"><h2 class="h4 fw-bold">For fleet owners</h2><p class="text-muted mb-0">Use the fleet workspace to maintain vehicle and driver records, review rentals, record payments and keep an eye on scheduled bookings. Access depends on your account role.</p></div><div class="col-lg-4 text-lg-end"><a class="btn btn-brand" href="<?= e(url('login.php?role=admin')) ?>">Fleet owner login <i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></a></div></div>
  </div></div>
  <div class="text-center"><h2 class="h4 fw-bold">Ready to explore?</h2><p class="text-muted">Browse the cars and choose the dates that work for your trip.</p><a class="btn btn-warning fw-semibold px-4" href="<?= e(url('cars.php')) ?>">Browse available cars</a></div>
</section>
<?php render_footer();
