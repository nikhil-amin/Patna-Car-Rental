<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

if (is_post()) {
    csrf_check();
    logout();
    session_boot();                       // fresh session just to carry the message
    flash('success', 'You have been logged out.');
    redirect(url('index.php'));
}
if (!current_user()) { redirect(url('index.php')); }
// GET (e.g. from an old link): ask to confirm, so a stray link can't log you out.
render_header('Log out');
?>
<div class="container"><div class="card auth-card text-center"><div class="card-body p-4">
  <h1 class="h5">Log out of your account?</h1>
  <form method="post" class="mt-3"><?= csrf_field() ?><button class="btn btn-brand">Yes, log out</button>
  <a class="btn btn-outline-secondary" href="<?= e(url('index.php')) ?>">Stay</a></form>
</div></div></div>
<?php render_footer();
