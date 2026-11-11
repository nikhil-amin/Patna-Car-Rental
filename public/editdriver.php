<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_role('admin');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (is_post()) {
    $id = filter_input(INPUT_POST, 'driver_id', FILTER_VALIDATE_INT);
}
if (!$id || $id < 1) {
    http_response_code(400);
    exit('Invalid driver reference.');
}

$driver = db_one(
    'SELECT * FROM driver WHERE driver_id = ? AND client_username = ? LIMIT 1',
    [$id, $user['id']]
);
if (!$driver) {
    http_response_code(404);
    exit('Driver not found in your fleet.');
}

$errors = [];
$old = $driver;
if (is_post()) {
    csrf_check();
    [$errors, $old] = validate($_POST, [
        'driver_name' => 'required|min:2|max:100',
        'dl_number' => 'required|min:5|max:40',
        'driver_phone' => 'required|phone',
        'driver_address' => 'required|min:5|max:255',
        'driver_gender' => 'required|in:Male,Female,Other',
    ]);
    $licence = strtoupper(trim((string)($old['dl_number'] ?? '')));
    if (!$errors && db_value('SELECT COUNT(*) FROM driver WHERE dl_number = ? AND driver_id <> ?', [$licence, $id])) {
        $errors['dl_number'] = 'Another driver already uses this licence number.';
    }
    if (!$errors) {
        try {
            db_transaction(function () use ($id, $user, $old, $licence): void {
                $owned = db_one(
                    'SELECT driver_id FROM driver WHERE driver_id = ? AND client_username = ? FOR UPDATE',
                    [$id, $user['id']]
                );
                if (!$owned) {
                    throw new DomainException('Driver not found in your fleet.');
                }
                db_exec(
                    'UPDATE driver SET driver_name = ?, dl_number = ?, driver_phone = ?, driver_address = ?, driver_gender = ? WHERE driver_id = ? AND client_username = ?',
                    [
                        trim((string)$old['driver_name']), $licence, trim((string)$old['driver_phone']),
                        trim((string)$old['driver_address']), $old['driver_gender'], $id, $user['id']
                    ]
                );
            });
            flash('success', 'Driver details updated.');
            redirect(url('enterdriver.php'));
        } catch (DomainException $e) {
            $errors['general'] = $e->getMessage();
        } catch (Throwable $e) {
            error_log('DriveEase driver edit failed: ' . $e->getMessage());
            $errors['general'] = 'Driver details could not be saved. Please try again.';
        }
    }
}

render_header('Edit driver', 'drivers');
?>
<div class="container py-4" style="max-width:800px">
  <a class="small text-decoration-none" href="<?= e(url('enterdriver.php')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back to drivers</a>
  <div class="mt-3 mb-4"><p class="small text-uppercase text-secondary mb-1">Fleet operations</p><h1 class="h2 fw-bold mb-1">Edit driver</h1><p class="text-secondary mb-0">Update driver contact and licence details.</p></div>
  <?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>Please check:</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" action="<?= e(url('editdriver.php')) ?>" class="card border-0 shadow-sm"><div class="card-body p-4">
    <?= csrf_field() ?><input type="hidden" name="driver_id" value="<?= (int)$id ?>">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="driver_name">Full name</label><input class="form-control" id="driver_name" name="driver_name" maxlength="100" required value="<?= e($old['driver_name'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label" for="dl_number">Driving licence number</label><input class="form-control text-uppercase" id="dl_number" name="dl_number" maxlength="40" required value="<?= e($old['dl_number'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label" for="driver_phone">Phone</label><input class="form-control" id="driver_phone" name="driver_phone" type="tel" maxlength="20" required value="<?= e($old['driver_phone'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label" for="driver_gender">Gender</label><select class="form-select" id="driver_gender" name="driver_gender" required><?php foreach (['Male','Female','Other'] as $v): ?><option value="<?= e($v) ?>" <?= ($old['driver_gender'] ?? '')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="col-12"><label class="form-label" for="driver_address">Address</label><textarea class="form-control" id="driver_address" name="driver_address" rows="3" maxlength="255" required><?= e($old['driver_address'] ?? '') ?></textarea></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-brand" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save changes</button><a class="btn btn-outline-secondary" href="<?= e(url('enterdriver.php')) ?>">Cancel</a></div>
  </div></form>
</div>
<?php render_footer(); ?>
