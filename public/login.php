<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$role = in_array($_GET['role'] ?? $_POST['role'] ?? 'customer', ['customer', 'admin'], true) ? ($_GET['role'] ?? $_POST['role'] ?? 'customer') : 'customer';
if (current_user()) { redirect(url('index.php')); }

$error = null;
$username = '';
if (is_post()) {
    csrf_check();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $r = auth_attempt($role, $username, $password);
        if (is_array($r)) {
            // Legacy session keys keep not-yet-rebuilt pages working (removed in Phase 7).
            $_SESSION[$role === 'admin' ? 'login_client' : 'login_customer'] = $r['id'];
            flash('success', 'Welcome back, ' . $r['name'] . '!');
            $next = (string) ($_SESSION['_intended'] ?? '');
            unset($_SESSION['_intended']);
            // Only follow same-site relative paths (prevents open redirects).
            $safe = $next !== '' && $next[0] === '/' && !str_starts_with($next, '//') && !str_contains($next, '\\');
            redirect($safe ? $next : url($role === 'admin' ? 'entercar.php' : 'index.php'));
        }
        $error = $r;
    }
}

render_header('Log in');
?>
<div class="container"><div class="card auth-card"><div class="card-body p-4">
  <h1 class="h4 fw-bold text-center mb-1">Welcome back</h1>
  <p class="text-center text-muted small mb-3">Log in to manage your rentals</p>
  <ul class="nav nav-pills nav-fill mb-3">
    <li class="nav-item"><a class="nav-link <?= $role === 'customer' ? 'active bg-brand' : '' ?>" href="?role=customer"><i class="fa-solid fa-user me-1"></i>Customer</a></li>
    <li class="nav-item"><a class="nav-link <?= $role === 'admin' ? 'active bg-brand' : '' ?>" href="?role=admin"><i class="fa-solid fa-warehouse me-1"></i>Fleet owner</a></li>
  </ul>
  <?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" novalidate data-once>
    <?= csrf_field() ?><input type="hidden" name="role" value="<?= e($role) ?>">
    <div class="mb-3"><label class="form-label" for="username">Username</label>
      <input class="form-control" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" autofocus required></div>
    <div class="mb-3"><label class="form-label" for="password">Password</label>
      <div class="input-group"><input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
        <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div></div>
    <div class="d-grid"><button class="btn btn-brand" type="submit">Log in</button></div>
  </form>
  <?php if ($role === 'admin' && !admin_signup_enabled()): ?>
    <p class="text-center small mt-3 mb-0 text-muted">Fleet-owner accounts are created by the site administrator.</p>
  <?php else: ?>
    <p class="text-center small mt-3 mb-0">New here? <a href="<?= e(url('register.php?role=' . $role)) ?>">Create an account</a></p>
  <?php endif; ?>
</div></div></div>
<?php render_footer();
