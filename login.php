<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (login_is_throttled($email)) {
        $error = 'Too many unsuccessful attempts. Try again in 15 minutes.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash, status FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        $valid = $account && password_verify($password, $account['password_hash']);
        $canLogin = $valid && $account['status'] === 'active';
        record_login_attempt($email, (bool) $canLogin);
        if (!$valid) {
            $error = 'The email address or password is incorrect.';
        } elseif ($account['status'] !== 'active') {
            $error = 'This account is not active. Contact support for assistance.';
        } else {
            if (password_needs_rehash($account['password_hash'], PASSWORD_DEFAULT)) {
                $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $update->execute([password_hash($password, PASSWORD_DEFAULT), $account['id']]);
            }
            login_user((int) $account['id']);
            redirect('/dashboard.php');
        }
    }
}

render_header('Log in | ' . SITE_NAME, 'Sign in to your Earn On Venture member account.');
?>
<section class="section auth-section"><div class="container narrow">
  <div class="section-heading"><p class="eyebrow">Welcome back</p><h1>Log in</h1></div>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="card form-card">
    <?= csrf_field() ?>
    <label>Email address<input type="email" name="email" autocomplete="email" required value="<?= e($email) ?>"></label>
    <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="btn" type="submit">Log in</button>
    <p><a href="/forgot-password.php">Forgot your password?</a></p>
    <p>New here? <a href="/register.php">Create an account</a>.</p>
  </form>
</div></section>
<?php render_footer();
