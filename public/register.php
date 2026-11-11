<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$raw  = $_GET['role'] ?? $_POST['role'] ?? 'customer';
$role = ($raw === 'admin' && admin_signup_enabled()) ? 'admin' : 'customer';
if ($raw === 'admin' && $role !== 'admin') { flash('info', 'Fleet-owner accounts are created by the site administrator.'); }
if (current_user()) { redirect(url('index.php')); }
[$table, $idCol, $nameCol, $pwCol] = ROLES[$role];
$prefix = $role === 'admin' ? 'client' : 'customer';

$errors = []; $old = [];
if (is_post()) {
    csrf_check();
    [$errors, $old] = validate($_POST, [
        'name' => 'required|min:2|max:100', 'username' => 'required|username', 'email' => 'required|email|max:100',
        'phone' => 'required|phone', 'address' => 'required|min:3|max:255', 'password' => 'required|password',
    ]);
    if (!isset($errors['password']) && ($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
        $errors['confirm'] = 'Passwords do not match.';
    }
    if (!$errors && db_value("SELECT COUNT(*) FROM $table WHERE $idCol = ?", [$old['username']])) {
        $errors['username'] = 'That username is already taken.';
    }
    if (!$errors && db_value("SELECT COUNT(*) FROM $table WHERE {$prefix}_email = ?", [$old['email']])) {
        $errors['email'] = 'An account with this email already exists.';
    }
    if (!$errors) {
        db_exec(
            "INSERT INTO $table ($idCol, $nameCol, {$prefix}_phone, {$prefix}_email, {$prefix}_address, $pwCol) VALUES (?, ?, ?, ?, ?, ?)",
            [$old['username'], $old['name'], $old['phone'], $old['email'], $old['address'], password_hash_new((string) $_POST['password'])]
        );
        flash('success', 'Account created. Please log in.');
        redirect(url('login.php?role=' . $role));
    }
}
$v = fn(string $k) => e($old[$k] ?? '');
$cls = fn(string $k) => isset($errors[$k]) ? ' is-invalid' : '';

render_header('Create account');
?>
<div class="container"><div class="card auth-card" style="max-width:620px"><div class="card-body p-4">
  <h1 class="h4 fw-bold text-center mb-3">Create your account</h1>
  <ul class="nav nav-pills nav-fill mb-3">
    <li class="nav-item"><a class="nav-link <?= $role === 'customer' ? 'active bg-brand' : '' ?>" href="?role=customer">Customer</a></li>
    <?php if (admin_signup_enabled()): ?><li class="nav-item"><a class="nav-link <?= $role === 'admin' ? 'active bg-brand' : '' ?>" href="?role=admin">Fleet owner</a></li><?php endif; ?>
  </ul>
  <form method="post" novalidate data-once><?= csrf_field() ?><input type="hidden" name="role" value="<?= e($role) ?>">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="name">Full name</label><input class="form-control<?= $cls('name') ?>" id="name" name="name" value="<?= $v('name') ?>" required><?= field_error($errors, 'name') ?></div>
      <div class="col-md-6"><label class="form-label" for="username">Username</label><input class="form-control<?= $cls('username') ?>" id="username" name="username" value="<?= $v('username') ?>" autocomplete="username" required><?= field_error($errors, 'username') ?></div>
      <div class="col-md-6"><label class="form-label" for="email">Email</label><input type="email" class="form-control<?= $cls('email') ?>" id="email" name="email" value="<?= $v('email') ?>" required><?= field_error($errors, 'email') ?></div>
      <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input type="tel" class="form-control<?= $cls('phone') ?>" id="phone" name="phone" value="<?= $v('phone') ?>" required><?= field_error($errors, 'phone') ?></div>
      <div class="col-12"><label class="form-label" for="address">Address</label><input class="form-control<?= $cls('address') ?>" id="address" name="address" value="<?= $v('address') ?>" required><?= field_error($errors, 'address') ?></div>
      <div class="col-md-6"><label class="form-label" for="password">Password</label><input type="password" class="form-control<?= $cls('password') ?>" id="password" name="password" autocomplete="new-password" required><?= field_error($errors, 'password') ?></div>
      <div class="col-md-6"><label class="form-label" for="confirm">Confirm password</label><input type="password" class="form-control<?= $cls('confirm') ?>" id="confirm" name="confirm" autocomplete="new-password" required><?= field_error($errors, 'confirm') ?></div>
      <div class="col-12 d-grid"><button class="btn btn-brand" type="submit">Create <?= $role === 'admin' ? 'fleet owner' : 'customer' ?> account</button></div>
    </div>
  </form>
  <p class="text-center small mt-3 mb-0">Already registered? <a href="<?= e(url('login.php?role=' . $role)) ?>">Log in</a></p>
</div></div></div>
<?php render_footer();
