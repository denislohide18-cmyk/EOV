<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$submitted = false;
$localResetUrl = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190) {
        $stmt = db()->prepare("SELECT id FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if ($account) {
            $token = bin2hex(random_bytes(32));
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $expireOld = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
                $expireOld->execute([$account['id']]);
                $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
                $insert->execute([$account['id'], hash('sha256', $token)]);
                $pdo->commit();
            } catch (Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }
            $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
            $hostName = explode(':', $host)[0];
            $isAlloy = filter_var(getenv('IS_ALLOY') ?: 'false', FILTER_VALIDATE_BOOLEAN);
            $isLocal = $isAlloy || $hostName === 'localhost' || $hostName === '127.0.0.1' || str_ends_with($hostName, '.local');
            if ($isLocal) {
                $localResetUrl = '/reset-password.php?token=' . rawurlencode($token);
            } else {
                $resetUrl = SITE_URL . '/reset-password.php?token=' . rawurlencode($token);
                $subject = 'Reset your Earn On Venture password';
                $message = "A password reset was requested for your Earn On Venture account.\n\nReset your password within one hour:\n" . $resetUrl . "\n\nIf you did not request this, ignore this message.";
                $headers = "From: Earn On Venture <no-reply@earnonventure.com>\r\nContent-Type: text/plain; charset=UTF-8";
                mail($email, $subject, $message, $headers);
            }
        }
    }
    $submitted = true;
}

render_header('Reset your password | ' . SITE_NAME);
?>
<section class="section auth-section"><div class="container narrow">
  <div class="section-heading"><p class="eyebrow">Account recovery</p><h1>Reset your password</h1></div>
  <?php if ($submitted): ?>
    <div class="alert alert-success" role="status">If an active account matches that email, a reset link has been generated. It expires in one hour.</div>
    <?php if ($localResetUrl): ?><div class="card"><p><strong>Local development reset link:</strong></p><p><a href="<?= e($localResetUrl) ?>"><?= e($localResetUrl) ?></a></p><p>This link is shown only in the local Alloy environment because email delivery is not configured.</p></div><?php endif; ?>
  <?php else: ?>
    <form method="post" class="card form-card">
      <?= csrf_field() ?>
      <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($email) ?>"></label>
      <button class="btn" type="submit">Request reset link</button>
      <p><a href="/login.php">Return to login</a></p>
    </form>
  <?php endif; ?>
</div></section>
<?php render_footer();
