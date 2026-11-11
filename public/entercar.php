<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/cars.php';
$user = require_role('admin');
$errors = [];
$old = [];
if (is_post()) {
    csrf_check();

    if (($_POST['action'] ?? '') === 'toggle_vehicle') {
        $carId = filter_input(INPUT_POST, 'car_id', FILTER_VALIDATE_INT);
        try {
            if (!$carId || $carId < 1) {
                throw new DomainException('Invalid vehicle reference.');
            }
            db_transaction(function () use ($carId, $user): void {
                $car = db_one(
                    'SELECT c.car_id, c.is_active FROM cars c JOIN clientcars cc ON cc.car_id = c.car_id WHERE c.car_id = ? AND cc.client_username = ? FOR UPDATE',
                    [$carId, $user['id']]
                );
                if (!$car) {
                    throw new DomainException('Vehicle not found in your fleet.');
                }
                if ((int)$car['is_active'] === 1 && (int)db_value(
                    "SELECT COUNT(*) FROM rentedcars WHERE car_id = ? AND booking_status = 'active' AND return_status = 'NR'",
                    [$carId]
                ) > 0) {
                    throw new DomainException('This vehicle has an active trip. Return the vehicle before deactivating it.');
                }
                db_exec('UPDATE cars SET is_active = ? WHERE car_id = ?', [(int)$car['is_active'] === 1 ? 0 : 1, $carId]);
            });
            flash('success', 'Vehicle status updated.');
        } catch (DomainException $e) {
            flash('warning', $e->getMessage());
        } catch (Throwable $e) {
            error_log('DriveEase vehicle status update failed: ' . $e->getMessage());
            flash('error', 'Vehicle status could not be updated. Please try again.');
        }
        redirect(url('entercar.php'));
    }

    [$errors, $old] = validate($_POST, [
        'car_name'=>'required|min:2|max:100',
        'car_nameplate'=>'required|min:2|max:30',
        'ac_price'=>'required|numeric|positive',
        'non_ac_price'=>'required|numeric|positive',
        'ac_price_per_day'=>'required|numeric|positive',
        'non_ac_price_per_day'=>'required|numeric|positive',
        'category'=>'required|in:Hatchback,Sedan,SUV,MPV,Luxury',
        'seats'=>'required|int',
        'transmission'=>'required|in:Manual,Automatic',
        'fuel_type'=>'required|in:Petrol,Diesel,CNG,Electric,Hybrid',
        'description'=>'max:1000',
    ]);
    if (!$errors && ((int)$old['seats'] < 2 || (int)$old['seats'] > 15)) $errors['seats']='Seats must be between 2 and 15.';
    if (!$errors && db_value('SELECT COUNT(*) FROM cars WHERE car_nameplate = ?', [$old['car_nameplate']])) $errors['car_nameplate']='This vehicle number is already registered.';
    if (!$errors) {
        db_transaction(function () use ($old, $user): void {
            db_exec('INSERT INTO cars (car_name, car_nameplate, car_img, ac_price, non_ac_price, ac_price_per_day, non_ac_price_per_day, car_availability, category, seats, transmission, fuel_type, description, is_active) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)', [
                $old['car_name'], strtoupper($old['car_nameplate']), (float)$old['ac_price'], (float)$old['non_ac_price'],
                (float)$old['ac_price_per_day'], (float)$old['non_ac_price_per_day'], 'yes', $old['category'],
                (int)$old['seats'], $old['transmission'], $old['fuel_type'], $old['description'] ?: null
            ]);
            db_exec('INSERT INTO clientcars (car_id, client_username) VALUES (?, ?)', [db_insert_id(), $user['id']]);
        });
        flash('success', 'Vehicle added to your fleet.');
        redirect(url('entercar.php'));
    }
}
$cars = db_all('SELECT c.* FROM cars c JOIN clientcars cc ON cc.car_id=c.car_id WHERE cc.client_username=? ORDER BY c.car_id DESC', [$user['id']]);
render_header('Fleet vehicles', 'fleet');
?>
<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><p class="text-uppercase small text-secondary mb-1">Fleet management</p><h1 class="h2 fw-bold mb-1">Your vehicles</h1><p class="text-secondary mb-0">Add vehicles and maintain clear, accurate rental rates.</p></div><span class="badge text-bg-light border fs-6"><?= count($cars) ?> vehicles</span></div>
  <div class="row g-4">
    <div class="col-lg-5"><div class="card shadow-sm border-0"><div class="card-body p-4">
      <h2 class="h5 fw-bold mb-3">Add a vehicle</h2>
      <?php if ($errors): ?><div class="alert alert-danger"><strong>Please check:</strong><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" action="<?= e(url('entercar.php')) ?>"><?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="car_name">Vehicle name</label><input class="form-control" id="car_name" name="car_name" required maxlength="100" value="<?= e($old['car_name'] ?? '') ?>"></div>
        <div class="mb-3"><label class="form-label" for="car_nameplate">Registration number</label><input class="form-control text-uppercase" id="car_nameplate" name="car_nameplate" required maxlength="30" value="<?= e($old['car_nameplate'] ?? '') ?>"></div>
        <div class="row g-3 mb-3"><div class="col-6"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="category" required><?php foreach (['Hatchback','Sedan','SUV','MPV','Luxury'] as $v): ?><option <?= ($old['category'] ?? 'Hatchback')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div><div class="col-6"><label class="form-label" for="seats">Seats</label><input class="form-control" id="seats" name="seats" type="number" min="2" max="15" required value="<?= e($old['seats'] ?? '5') ?>"></div></div>
        <div class="row g-3 mb-3"><div class="col-6"><label class="form-label" for="transmission">Transmission</label><select class="form-select" id="transmission" name="transmission"><?php foreach (['Manual','Automatic'] as $v): ?><option <?= ($old['transmission'] ?? 'Manual')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div><div class="col-6"><label class="form-label" for="fuel_type">Fuel</label><select class="form-select" id="fuel_type" name="fuel_type"><?php foreach (['Petrol','Diesel','CNG','Electric','Hybrid'] as $v): ?><option <?= ($old['fuel_type'] ?? 'Petrol')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div></div>
        <h3 class="small fw-bold text-uppercase text-secondary mt-4">Rates (₹)</h3>
        <div class="row g-3"><?php foreach (['ac_price'=>'AC per km','non_ac_price'=>'Non-AC per km','ac_price_per_day'=>'AC per day','non_ac_price_per_day'=>'Non-AC per day'] as $key=>$label): ?><div class="col-6"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><input class="form-control" type="number" min="0.01" step="0.01" id="<?= e($key) ?>" name="<?= e($key) ?>" required value="<?= e($old[$key] ?? '') ?>"></div><?php endforeach; ?></div>
        <div class="mt-3 mb-3"><label class="form-label" for="description">Description <span class="text-secondary">(optional)</span></label><textarea class="form-control" id="description" name="description" rows="3" maxlength="1000"><?= e($old['description'] ?? '') ?></textarea></div>
        <p class="small text-secondary">Vehicle photos are loaded from royalty-free online sources; no image upload is stored in this app.</p><button class="btn btn-brand w-100" type="submit"><i class="fa-solid fa-plus me-2"></i>Add vehicle</button>
      </form>
    </div></div></div>
    <div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="p-4 border-bottom"><h2 class="h5 fw-bold mb-0">Fleet inventory</h2></div>
    <?php if (!$cars): ?><div class="p-5 text-center"><i class="fa-solid fa-car-side fa-2x text-secondary mb-3"></i><p class="mb-0 text-secondary">No vehicles yet. Add your first vehicle using the form.</p></div><?php else: ?><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Vehicle</th><th>Category</th><th>Day rate</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($cars as $car): ?><tr><td><div class="fw-semibold"><?= e($car['car_name']) ?></div><div class="small text-secondary"><?= e($car['car_nameplate']) ?></div></td><td><?= e($car['category'] ?? '—') ?><div class="small text-secondary"><?= (int)$car['seats'] ?> seats · <?= e($car['fuel_type'] ?? '—') ?></div></td><td><?= money($car['ac_price_per_day']) ?><div class="small text-secondary">AC / day</div></td><td><span class="badge <?= (int)($car['is_active'] ?? 1)===1?'text-bg-success':'text-bg-secondary' ?>"><?= (int)($car['is_active'] ?? 1)===1?'Active':'Inactive' ?></span></td><td><div class="d-flex flex-wrap gap-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('editvehicle.php?id='.(int)$car['car_id'])) ?>">Edit</a><form method="post" action="<?= e(url('entercar.php')) ?>" data-once><?= csrf_field() ?><input type="hidden" name="action" value="toggle_vehicle"><input type="hidden" name="car_id" value="<?= (int)$car['car_id'] ?>"><button class="btn btn-sm <?= (int)($car['is_active'] ?? 1)===1?'btn-outline-danger':'btn-outline-success' ?>" type="submit" onclick="return confirm('<?= (int)($car['is_active'] ?? 1)===1?'Deactivate this vehicle?':'Activate this vehicle?' ?>')"><?= (int)($car['is_active'] ?? 1)===1?'Deactivate':'Activate' ?></button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div></div>
  </div>
</div>
<?php render_footer(); ?>