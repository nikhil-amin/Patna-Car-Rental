<?php /** Expects $car (row from cars_search). Optional $q for carrying dates to details. */
$qs = [];
foreach (['start', 'end'] as $k) { if (!empty($dates[$k])) { $qs[$k] = $dates[$k]; } }
$detail = url('car.php?id=' . (int) $car['car_id'] . ($qs ? '&' . http_build_query($qs) : ''));
?>
<div class="col-12 col-sm-6 col-lg-4">
  <div class="card car-card h-100 shadow-sm position-relative">
    <span class="badge badge-status <?= $car['is_free'] ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $car['is_free'] ? 'Available' : 'Booked' ?></span>
    <img class="car-img" src="<?= e(car_image($car['car_img'])) ?>" alt="<?= e($car['car_name']) ?>" loading="lazy" decoding="async">
    <div class="card-body d-flex flex-column">
      <div class="d-flex justify-content-between align-items-start">
        <h3 class="h5 mb-1"><?= e($car['car_name']) ?></h3>
        <span class="badge text-bg-light border"><?= e($car['category']) ?></span>
      </div>
      <div class="spec mb-3">
        <span class="me-3"><i class="fa-solid fa-user-group me-1"></i><?= (int) $car['seats'] ?> seats</span>
        <span class="me-3"><i class="fa-solid fa-gas-pump me-1"></i><?= e($car['fuel_type']) ?></span>
        <span><i class="fa-solid fa-gears me-1"></i><?= e($car['transmission']) ?></span>
      </div>
      <div class="mt-auto d-flex justify-content-between align-items-center">
        <div><span class="price"><?= e(money($car['ac_price_per_day'])) ?></span><span class="spec"> /day</span><div class="spec">or <?= e(money($car['ac_price'])) ?>/km · AC</div></div>
        <a href="<?= e($detail) ?>" class="btn btn-brand btn-sm">View details</a>
      </div>
    </div>
  </div>
</div>
