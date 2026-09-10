<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = require_auth();
$detailsErrors = [];
$passwordErrors = [];
$fullName = $user['full_name'];
$email = $user['email'];
$bio = $user['profile_bio'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $passwordStmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $passwordStmt->execute([$user['id']]);
    $currentHash = (string) $passwordStmt->fetchColumn();

    if ($action === 'details') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $bio = trim((string) ($_POST['profile_bio'] ?? ''));
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        if (strlen($fullName) < 2 || strlen($fullName) > 120) {
            $detailsErrors[] = 'Enter your full name (2 to 120 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $detailsErrors[] = 'Enter a valid email address.';
        }
        if (strlen($bio) > 500) {
            $detailsErrors[] = 'Your profile bio cannot exceed 500 characters.';
        }
        if ($email !== $user['email'] && !password_verify($currentPassword, $currentHash)) {
            $detailsErrors[] = 'Enter your current password to change your email address.';
        }
        if (!$detailsErrors) {
            $duplicate = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $duplicate->execute([$email, $user['id']]);
            if ($duplicate->fetch()) {
                $detailsErrors[] = 'That email address is already in use.';
            } else {
                try {
                    $update = db()->prepare('UPDATE users SET full_name = ?, email = ?, profile_bio = ? WHERE id = ?');
                    $update->execute([$fullName, $email, $bio !== '' ? $bio : null, $user['id']]);
                    flash('success', 'Your profile has been updated.');
                    redirect('/profile.php');
                } catch (PDOException $exception) {
                    if ((string) $exception->getCode() === '23000') {
                        $detailsErrors[] = 'That email address is already in use.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
    } elseif ($action === 'password') {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (!password_verify($currentPassword, $currentHash)) {
            $passwordErrors[] = 'Your current password is incorrect.';
        }
        if ($passwordError = validate_password($newPassword)) {
            $passwordErrors[] = $passwordError;
        }
        if ($newPassword !== $confirmation) {
            $passwordErrors[] = 'New passwords do not match.';
        }
        if (!$passwordErrors) {
            $update = db()->prepare('UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?');
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            login_user((int) $user['id']);
            session_regenerate_id(true);
            flash('success', 'Your password has been changed.');
            redirect('/profile.php');
        }
    } else {
        http_response_code(400);
        $detailsErrors[] = 'Invalid profile action.';
    }
}

render_header('Profile and security | ' . SITE_NAME);
?>
<section class="page-hero"><div class="container"><p class="eyebrow">Account settings</p><h1>Profile and security</h1><p>Member since <?= e(date('F Y', strtotime($user['created_at']))) ?></p></div></section>
<section class="section"><div class="container form-grid">
  <div><h2>Profile details</h2><?php if ($detailsErrors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($detailsErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?><form method="post" class="card form-card">
    <?= csrf_field() ?><input type="hidden" name="action" value="details">
    <label>Full name<input type="text" name="full_name" maxlength="120" autocomplete="name" required value="<?= e($fullName) ?>"></label>
    <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($email) ?>"></label>
    <label>Profile bio (optional)<textarea name="profile_bio" maxlength="500" rows="5"><?= e($bio) ?></textarea></label>
    <label>Current password <small>Required only when changing your email.</small><input type="password" name="current_password" autocomplete="current-password"></label>
    <button class="btn" type="submit">Save profile</button>
  </form></div>
  <div id="security"><h2>Change password</h2><?php if ($passwordErrors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($passwordErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?><form method="post" class="card form-card">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
    <label>New password<input type="password" name="new_password" minlength="10" autocomplete="new-password" required><small>At least 10 characters with uppercase, lowercase, and a number.</small></label>
    <label>Confirm new password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
    <button class="btn" type="submit">Change password</button>
  </form></div>
</div></section>
<?php render_footer();
