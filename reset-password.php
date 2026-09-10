<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = strlen($token) === 64 && ctype_xdigit($token) ? hash('sha256', $token) : '';
$error = '';
$valid = false;

if ($tokenHash !== '') {
    $lookup = db()->prepare('SELECT id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $lookup->execute([$tokenHash]);
    $valid = (bool) $lookup->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (!$valid) {
        $error = 'This reset link is invalid or has expired.';
    } elseif ($passwordError = validate_password($password)) {
        $error = $passwordError;
    } elseif ($password !== $confirmation) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE');
            $lock->execute([$tokenHash]);
            $reset = $lock->fetch();
            if (!$reset) {
                throw new RuntimeException('This reset link has already been used or has expired.');
            }
            $update = $pdo->prepare('UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);
            $consume = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL');
            $consume->execute([$reset['id']]);
            $pdo->commit();
            flash('success', 'Your password has been updated. You can now log in.');
            redirect('/login.php');
        } catch (RuntimeException $exception) {
            $pdo->rollBack();
            $error = $exception->getMessage();
            $valid = false;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}

render_header('Choose a new password | ' . SITE_NAME);
?>
<section class="section auth-section"><div class="container narrow">
  <div class="section-heading"><p class="eyebrow">Account recovery</p><h1>Choose a new password</h1></div>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <?php if ($valid): ?><form method="post" class="card form-card">
    <?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
    <label>New password<input type="password" name="password" minlength="10" autocomplete="new-password" required><small>At least 10 characters with uppercase, lowercase, and a number.</small></label>
    <label>Confirm new password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
    <button class="btn" type="submit">Update password</button>
  </form><?php else: ?><div class="card"><p>This reset link is invalid or has expired.</p><a class="btn" href="/forgot-password.php">Request another link</a></div><?php endif; ?>
</div></section>
<?php render_footer();
