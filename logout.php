<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = require_auth();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_user();
    redirect('/login.php');
}

render_header('Log out | ' . SITE_NAME);
?>
<section class="section"><div class="container narrow"><div class="card form-card">
  <h1>Log out</h1><p>Log out of <?= e($user['email']) ?>?</p>
  <form method="post"><?= csrf_field() ?><button class="btn" type="submit">Log out</button> <a class="btn btn-secondary" href="/dashboard.php">Cancel</a></form>
</div></div></section>
<?php render_footer();
