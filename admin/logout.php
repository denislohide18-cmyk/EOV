<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    admin_log('logout', 'session', null);
    logout_user();
    redirect('/admin/login.php');
}

render_admin_header('Sign out');
?>
<section class="panel admin-card narrow-panel">
  <p class="eyebrow">Session security</p>
  <h1>Sign out of administration?</h1>
  <p>This ends the administrative session for <?= e($admin['email']) ?>.</p>
  <form method="post" class="button-row">
    <?= csrf_field() ?>
    <button class="btn btn-danger" type="submit">Sign out</button>
    <a class="btn btn-secondary" href="/admin/">Cancel</a>
  </form>
</section>
<?php render_admin_footer(); ?>
