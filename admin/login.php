<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';

$existing = !empty($_SESSION['user_id']) ? current_user() : null;
if ($existing) {
    if ($existing['role'] === 'admin') {
        redirect('/admin/');
    }
    http_response_code(403);
    exit('Sign out of the member account before using administrator sign in.');
}

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } elseif (login_is_throttled($email)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? AND role = ? AND status = ? LIMIT 1');
        $stmt->execute([$email, 'admin', 'active']);
        $account = $stmt->fetch();
        $valid = $account && password_verify($password, $account['password_hash']);
        record_login_attempt($email, (bool) $valid);

        if ($valid) {
            login_user((int) $account['id']);
            admin_log('login', 'session', null);
            redirect('/admin/');
        }
        $error = 'The supplied credentials are not valid for an administrator account.';
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Administrator sign in | <?= e(SITE_NAME) ?></title>
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="/assets/css/auth.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-body admin-login-body">
<main class="auth-shell">
  <section class="auth-intro"><p class="eyebrow">Earn On Venture</p><h1>Clear oversight for every opportunity.</h1><p>Review participation, verification, and reward records from one secure workspace.</p></section>
  <div class="auth-main"><section class="auth-card" aria-labelledby="login-title">
    <header class="auth-card-head"><a class="logo" href="/"><span>EV</span> <?= e(SITE_NAME) ?></a><p class="eyebrow">Secure administration</p><h1 id="login-title">Administrator sign in</h1><p>Use an active administrator account to continue.</p></header>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="username" maxlength="190" required autofocus><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><div class="form-actions"><button class="btn" type="submit">Sign in</button></div></form>
    <div class="auth-links"><a href="/">Return to the site</a></div>
  </section></div>
</main>
</body>
</html>
