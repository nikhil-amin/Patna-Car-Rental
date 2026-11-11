<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';

$in = [
    'q' => trim((string) ($_GET['q'] ?? '')), 'category' => (string) ($_GET['category'] ?? ''),
    'fuel' => (string) ($_GET['fuel'] ?? ''), 'transmission' => (string) ($_GET['transmission'] ?? ''),
    'seats' => (string) ($_GET['seats'] ?? ''), 'max_price' => (string) ($_GET['max_price'] ?? ''),
    'sort' => (string) ($_GET['sort'] ?? 'price_asc'), 'start' => (string) ($_GET['start'] ?? ''), 'end' => (string) ($_GET['end'] ?? ''),
    'page' => (string) ($_GET['page'] ?? '1'),
];
$dateError = null;
$dates = ['start' => '', 'end' => ''];
if ($in['start'] !== '' || $in['end'] !== '') {
    [$dateError, $s, $e2] = validate_date_range($in['start'], $in['end']);
    if ($dateError) { $in['start'] = $in['end'] = ''; } else { $dates = ['start' => $s, 'end' => $e2]; }
}
$result = cars_search($in);
$categories = car_categories();
$today = date('Y-m-d');
$qsBase = array_filter($in, fn($v, $k) => $v !== '' && $k !== 'page', ARRAY_FILTER_USE_BOTH);   // for pagination links
$activeFilters = array_diff_key($qsBase, ['sort' => 1]);                                           // sorting isn't filtering

render_header('Our cars', 'cars');
?>
<section class="de-page-hero"><div class="container"><span class="de-section-kicker">THE DRIVE STARTS HERE</span><h1>Find the car that <em>gets you there.</em></h1><p>Explore the fleet, compare real rates and check availability for your dates.</p></div></section>
<div class="container py-4">
  <h2 class="h5 fw-bold mb-3">Explore the fleet</h2>
  <?php if ($dateError): ?><div class="alert alert-warning"><?= e($dateError) ?></div><?php endif; ?>

  <button class="btn btn-outline-secondary d-md-none w-100 mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#filters" aria-expanded="<?= $activeFilters ? 'true' : 'false' ?>" aria-controls="filters"><i class="fa-solid fa-sliders me-1"></i>Filters<?= $activeFilters ? ' (' . count($activeFilters) . ' active)' : '' ?></button>
  <form method="get" id="filters" class="card border-0 shadow-sm mb-4 collapse d-md-block<?= $activeFilters ? ' show' : '' ?>"><div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="start">Pickup</label><input type="date" id="start" name="start" class="form-control form-control-sm" min="<?= e($today) ?>" value="<?= e($dates['start']) ?>" data-date-start="#end"></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="end">Return</label><input type="date" id="end" name="end" class="form-control form-control-sm" min="<?= e($today) ?>" value="<?= e($dates['end']) ?>"></div>
      <div class="col-12 col-md-3"><label class="form-label small mb-1" for="q">Search</label><input id="q" name="q" class="form-control form-control-sm" placeholder="e.g. Innova" value="<?= e($in['q']) ?>" maxlength="50"></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="category">Type</label>
        <select id="category" name="category" class="form-select form-select-sm"><option value="">All</option>
          <?php foreach ($categories as $c): ?><option <?= $in['category'] === $c['category'] ? 'selected' : '' ?>><?= e($c['category']) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-6 col-md-3"><label class="form-label small mb-1" for="sort">Sort by</label>
        <select id="sort" name="sort" class="form-select form-select-sm">
          <?php foreach (['price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'name' => 'Name', 'seats' => 'Most seats'] as $k => $l): ?>
            <option value="<?= e($k) ?>" <?= $in['sort'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="fuel">Fuel</label>
        <select id="fuel" name="fuel" class="form-select form-select-sm"><option value="">Any</option>
          <?php foreach (['Petrol', 'Diesel', 'CNG', 'Electric'] as $o): ?><option <?= $in['fuel'] === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="transmission">Gearbox</label>
        <select id="transmission" name="transmission" class="form-select form-select-sm"><option value="">Any</option>
          <?php foreach (['Manual', 'Automatic'] as $o): ?><option <?= $in['transmission'] === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="seats">Min seats</label>
        <select id="seats" name="seats" class="form-select form-select-sm"><option value="">Any</option>
          <?php foreach ([4, 5, 7] as $o): ?><option value="<?= $o ?>" <?= $in['seats'] === (string) $o ? 'selected' : '' ?>><?= $o ?>+</option><?php endforeach; ?></select></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="max_price">Max ₹/day</label><input type="number" min="0" step="100" id="max_price" name="max_price" class="form-control form-control-sm" value="<?= e($in['max_price']) ?>"></div>
      <div class="col-12 col-md-4 d-flex gap-2 justify-content-md-end">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('cars.php')) ?>">Reset</a>
        <button class="btn btn-brand btn-sm px-4" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply filters</button>
      </div>
    </div>
  </div></form>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-muted small"><?= (int) $result['total'] ?> car<?= $result['total'] === 1 ? '' : 's' ?> found<?= $result['has_dates'] ? ' · availability for ' . e($dates['start']) . ' to ' . e($dates['end']) : '' ?></div>
  </div>

  <?php if (!$result['rows']): ?>
    <div class="text-center py-5 text-muted"><i class="fa-solid fa-car-burst fa-3x mb-3"></i><p>No cars match these filters. Try widening your search.</p></div>
  <?php else: ?>
    <div class="row g-4"><?php foreach ($result['rows'] as $car) { include __DIR__ . '/../includes/car_card.php'; } ?></div>
  <?php endif; ?>

  <?php if ($result['pages'] > 1): ?>
    <nav class="mt-4" aria-label="Pages"><ul class="pagination justify-content-center">
      <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
        <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($qsBase + ['page' => $i])) ?>"><?= $i ?></a></li>
      <?php endfor; ?>
    </ul></nav>
  <?php endif; ?>
</div>
<?php render_footer();
