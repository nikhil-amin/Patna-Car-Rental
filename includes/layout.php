<?php
declare(strict_types=1);

function nav_links(): array
{
    $u = current_user();
    $links = [
        'home' => ['Home', 'index.php', 'fa-house'],
        'cars' => ['Browse cars', 'cars.php', 'fa-car'],
        'how-it-works' => ['How it works', 'how-it-works.php', 'fa-route'],
        'about' => ['About', 'about.php', 'fa-circle-info'],
        'faq' => ['FAQ', 'faq.php', 'fa-circle-question'],
        'contact' => ['Contact', 'contact.php', 'fa-envelope'],
    ];

    if ($u && $u['role'] === 'customer') {
        $links['trips'] = ['Active rentals', 'prereturncar.php', 'fa-key'];
        $links['history'] = ['My bookings', 'mybookings.php', 'fa-clock-rotate-left'];
    } elseif ($u && $u['role'] === 'admin') {
        $links['fleet'] = ['Fleet dashboard', 'entercar.php', 'fa-warehouse'];
        $links['drivers'] = ['Drivers', 'enterdriver.php', 'fa-id-card'];
        $links['rentals'] = ['Rentals & payments', 'admin-rentals.php', 'fa-receipt'];
        $links['customers'] = ['Customers', 'admin-customers.php', 'fa-users'];
        $links['calendar'] = ['Booking calendar', 'admin-calendar.php', 'fa-calendar-days'];
    }

    return $links;
}

function render_header(string $title, string $active = ''): void
{
    $u = current_user();
    $app = (string) config('APP_NAME', 'DriveEase');
    ?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e($app) ?></title>
  <meta name="description" content="<?= e($title) ?> — <?= e($app) ?> car rental platform. Browse vehicles, understand the booking process and get support.">
  <meta name="theme-color" content="#0b0b0b">
  <link rel="icon" href="<?= e(asset('img/P.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/fontawesome/css/all.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('vendor/sweetalert2/sweetalert2.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/driveease.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-xxl navbar-dark sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-car-side me-2"></i><?= e($app) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto mb-2 mb-xl-0">
        <?php foreach (nav_links() as $key => [$label, $href, $icon]): ?>
          <li class="nav-item"><a class="nav-link<?= $active === $key ? ' active' : '' ?>"<?= $active === $key ? ' aria-current="page"' : '' ?> href="<?= e(url($href)) ?>"><i class="fa-solid <?= e($icon) ?> me-1" aria-hidden="true"></i><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex align-items-center flex-wrap gap-2 pb-3 pb-xl-0">
        <?php if ($u): ?>
          <span class="text-white-50 small d-none d-xxl-inline"><i class="fa-solid fa-user me-1" aria-hidden="true"></i><?= e($u['name']) ?> <span class="badge text-bg-light text-uppercase"><?= e($u['role'] === 'admin' ? 'Fleet owner' : $u['role']) ?></span></span>
          <form method="post" action="<?= e(url('logout.php')) ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-outline-light btn-sm"><i class="fa-solid fa-right-from-bracket me-1" aria-hidden="true"></i>Logout</button></form>
        <?php else: ?>
          <a class="btn btn-outline-light btn-sm" href="<?= e(url('login.php')) ?>">Log in</a>
          <a class="btn btn-warning btn-sm fw-semibold" href="<?= e(url('register.php')) ?>">Get started</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main class="flex-grow-1">
<?php
}

function render_footer(): void
{
    $app = (string) config('APP_NAME', 'DriveEase');
    $flash = flash_pull();
    ?>
</main>
<footer class="bg-dark text-white-50 mt-5 pt-5 pb-3">
  <div class="container">
    <div class="row g-4 pb-4">
      <div class="col-lg-4">
        <a class="navbar-brand fw-bold text-white text-decoration-none" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-car-side me-2" aria-hidden="true"></i><?= e($app) ?></a>
        <p class="small mt-3 mb-3" style="max-width:340px">A simpler way to discover cars, manage bookings and keep every trip organised.</p>
        <div class="d-flex gap-3" aria-label="Developer social links">
          <a class="text-white-50" href="https://github.com/ashutoshpalhare" target="_blank" rel="noopener noreferrer" aria-label="Developer GitHub"><i class="fa-brands fa-github fa-lg"></i></a>
          <a class="text-white-50" href="https://www.linkedin.com/in/ashutoshpalhare" target="_blank" rel="noopener noreferrer" aria-label="Developer LinkedIn"><i class="fa-brands fa-linkedin-in fa-lg"></i></a>
          <a class="text-white-50" href="https://twitter.com/ashutoshpalhare" target="_blank" rel="noopener noreferrer" aria-label="Developer X"><i class="fa-brands fa-x-twitter fa-lg"></i></a>
          <a class="text-white-50" href="https://www.instagram.com/ashutoshpalhare/" target="_blank" rel="noopener noreferrer" aria-label="Developer Instagram"><i class="fa-brands fa-instagram fa-lg"></i></a>
          <a class="text-white-50" href="https://www.facebook.com/ashutoshpalhare/" target="_blank" rel="noopener noreferrer" aria-label="Developer Facebook"><i class="fa-brands fa-facebook fa-lg"></i></a>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="h6 text-white fw-bold">Explore</h2>
        <ul class="list-unstyled small d-grid gap-2 mb-0">
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('index.php')) ?>">Home</a></li>
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('cars.php')) ?>">Browse cars</a></li>
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('how-it-works.php')) ?>">How it works</a></li>
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('about.php')) ?>">About DriveEase</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h2 class="h6 text-white fw-bold">Help</h2>
        <ul class="list-unstyled small d-grid gap-2 mb-0">
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('faq.php')) ?>">FAQs</a></li>
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('contact.php')) ?>">Contact & support</a></li>
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('login.php')) ?>">Customer login</a></li>
          <li><a class="link-light link-opacity-75 link-opacity-100-hover text-decoration-none" href="<?= e(url('login.php?role=admin')) ?>">Fleet owner login</a></li>
        </ul>
      </div>
      <div class="col-12 col-lg-4">
        <h2 class="h6 text-white fw-bold">Project & credits</h2>
        <p class="small mb-2">DriveEase is an evolving car-rental management project.</p>
        <p class="small mb-2">Original project by <a class="link-light" href="https://github.com/nikhil-amin/Patna-Car-Rental" target="_blank" rel="noopener noreferrer">Nikhil Amin</a>. Current enhancements by <a class="link-light" href="https://github.com/ashutoshpalhare" target="_blank" rel="noopener noreferrer">Ashutosh Palhare</a>.</p>
        <div class="small d-flex flex-wrap gap-3">
          <a class="link-light link-opacity-75 link-opacity-100-hover" href="<?= e(url('privacy.php')) ?>">Privacy</a>
          <a class="link-light link-opacity-75 link-opacity-100-hover" href="<?= e(url('terms.php')) ?>">Terms</a>
          <a class="link-light link-opacity-75 link-opacity-100-hover" href="https://github.com/ashutoshpalhare/DriveEase" target="_blank" rel="noopener noreferrer">GitHub repository <i class="fa-solid fa-arrow-up-right-from-square ms-1" aria-hidden="true"></i></a>
        </div>
      </div>
    </div>
    <hr class="border-secondary">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 small">
      <span>&copy; <?= date('Y') ?> <?= e($app) ?>. All rights reserved.</span>
      <span>Built with care · <a class="link-light" href="https://ashutoshpalhare.github.io/" target="_blank" rel="noopener noreferrer">Ashutosh Palhare</a></span>
    </div>
  </div>
</footer>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>"></script>
<script>window.DE_FLASH = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
<?php
}

function field_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? '<div class="invalid-feedback d-block">' . e($errors[$field]) . '</div>' : '';
}
