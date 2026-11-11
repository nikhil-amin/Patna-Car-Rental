<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role('admin');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (is_post()) {
    $id = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
}
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid vehicle reference.');
}

$vehicle = db_one(
    'SELECT c.* FROM cars c JOIN clientcars cc ON cc.car_id = c.car_id WHERE c.car_id = ? AND cc.client_username = ? LIMIT 1',
    [$id, $user['id']]
);
if (!$vehicle) {
    http_response_code(404);
    exit('Vehicle not found in your fleet.');
}

$errors = [];
$old = $vehicle;
if (is_post()) {
    csrf_check();
    [$errors, $old] = validate($_POST, [
        'car_name' => 'required|min:2|max:100',
        'car_nameplate' => 'required|min:2|max:30',
        'ac_price' => 'required|numeric|positive',
        'non_ac_price' => 'required|numeric|positive',
        'ac_price_per_day' => 'required|numeric|positive',
        'non_ac_price_per_day' => 'required|numeric|positive',
        'category' => 'required|in:Hatchback,Sedan,SUV,MPV,Luxury',
        'seats' => 'required|int',
        'transmission' => 'required|in:Manual,Automatic',
        'fuel_type' => 'required|in:Petrol,Diesel,CNG,Electric,Hybrid',
        'description' => 'max:1000',
    ]);
    if (!$errors && ((int)$old['seats'] < 2 || (int)$old['seats'] > 15)) {
        $errors['seats'] = 'Seats must be between 2 and 15.';
    }
    $plate = strtoupper(trim((string)($old['car_nameplate'] ?? '')));
    if (!$errors && db_value('SELECT COUNT(*) FROM cars WHERE car_nameplate = ? AND car_id <> ?', [$plate, $id])) {
        $errors['car_nameplate'] = 'Another vehicle already uses this registration number.';
    }
    if (!$errors) {
        try {
            db_transaction(function () use ($id, $user, $old, $plate): void {
                $owned = db_one(
                    'SELECT c.car_id FROM cars c JOIN clientcars cc ON cc.car_id = c.car_id WHERE c.car_id = ? AND cc.client_username = ? FOR UPDATE',
                    [$id, $user['id']]
                );
                if (!$owned) {
                    throw new DomainException('Vehicle not found in your fleet.');
                }
                db_exec(
                    'UPDATE cars SET car_name = ?, car_nameplate = ?, ac_price = ?, non_ac_price = ?, ac_price_per_day = ?, non_ac_price_per_day = ?, category = ?, seats = ?, transmission = ?, fuel_type = ?, description = ? WHERE car_id = ?',
                    [
                        trim((string)$old['car_name']), $plate, (float)$old['ac_price'], (float)$old['non_ac_price'],
                        (float)$old['ac_price_per_day'], (float)$old['non_ac_price_per_day'], $old['category'],
                        (int)$old['seats'], $old['transmission'], $old['fuel_type'],
                        trim((string)($old['description'] ?? '')) !== '' ? trim((string)$old['description']) : null, $id
                    ]
                );
            });
            flash('success', 'Vehicle details updated.');
            redirect(url('entercar.php'));
        } catch (DomainException $e) {
            $errors['general'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('DriveEase vehicle edit failed: ' . $e->getMessage());
            $errors['general'] = 'Vehicle details could not be saved. Please try again.';
        }
    }
}

render_header('Edit vehicle', 'fleet');
?>
<div class="container py-4" style="max-width:900px">
  <a class="small text-decoration-none" href="<?= e(url('entercar.php')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back to fleet</a>
  <div class="mt-3 mb-4"><p class="small text-uppercase text-secondary mb-1">Fleet management</p><h1 class="h2 fw-bold mb-1">Edit vehicle</h1><p class="text-secondary mb-0">Update vehicle details and rates. Existing bookings keep their saved fare.</p></div>
  <?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please check:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('editvehicle.php')) ?>" class="card border-0 shadow-sm"><div class="card-body p-4">
    <?= csrf_field() ?><input type="hidden" name="car_id" value="<?= (int)$id ?>">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="car_name">Vehicle name</label><input class="form-control" id="car_name" name="car_name" maxlength="100" required value="<?= e($old['car_name'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label" for="car_nameplate">Registration number</label><input class="form-control text-uppercase" id="car_nameplate" name="car_nameplate" maxlength="30" required value="<?= e($old['car_nameplate'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category" required><?php foreach (['Hatchback','Sedan','SUV','MPV','Luxury'] as $v): ?><option value="<?= e($v) ?>" <?= ($old['category'] ?? '')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label" for="seats">Seats</label><input class="form-control" id="seats" name="seats" type="number" min="2" max="15" required value="<?= e($old['seats'] ?? 5) ?>"></div>
      <div class="col-md-4"><label class="form-label" for="transmission">Transmission</label><select class="form-select" id="transmission" name="transmission"><?php foreach (['Manual','Automatic'] as $v): ?><option value="<?= e($v) ?>" <?= ($old['transmission'] ?? 'Manual')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label" for="fuel_type">Fuel type</label><select class="form-select" id="fuel_type" name="fuel_type"><?php foreach (['Petrol','Diesel','CNG','Electric','Hybrid'] as $v): ?><option value="<?= e($v) ?>" <?= ($old['fuel_type'] ?? 'Petrol')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-4"><label class="form-label" for="ac_price">AC per km (₹)</label><input class="form-control" id="ac_price" name="ac_price" type="number" min="0.01" step="0.01" required value="<?= e($old['ac_price'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label" for="non_ac_price">Non-AC per km (₹)</label><input class="form-control" id="non_ac_price" name="non_ac_price" type="number" min="0.01" step="0.01" required value="<?= e($old['non_ac_price'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label" for="ac_price_per_day">AC per day (₹)</label><input class="form-control" id="ac_price_per_day" name="ac_price_per_day" type="number" min="0.01" step="0.01" required value="<?= e($old['ac_price_per_day'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label" for="non_ac_price_per_day">Non-AC per day (₹)</label><input class="form-control" id="non_ac_price_per_day" name="non_ac_price_per_day" type="number" min="0.01" step="0.01" required value="<?= e($old['non_ac_price_per_day'] ?? '') ?>"></div>
      <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="1000"><?= e($old['description'] ?? '') ?></textarea></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save changes</button><a class="btn btn-outline-secondary" href="<?= e(url('entercar.php')) ?>">Cancel</a></div>
  </div></form>
</div>
<?php render_footer(); ?>
