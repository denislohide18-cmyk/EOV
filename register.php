<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$errors = [];
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (strlen($fullName) < 2 || strlen($fullName) > 120) {
        $errors[] = 'Enter your full name (2 to 120 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($passwordError = validate_password($password)) {
        $errors[] = $passwordError;
    }
    if ($password !== $confirmation) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors[] = 'An account already exists for that email address.';
        } else {
            try {
                $stmt = db()->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'member', 'active')");
                $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT)]);
                login_user((int) db()->lastInsertId());
                flash('success', 'Your account has been created.');
                redirect('/dashboard.php');
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    $errors[] = 'An account already exists for that email address.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

render_header('Create account | ' . SITE_NAME, 'Create a free Earn On Venture member account.');
?>
<section class="section auth-section"><div class="container narrow">
  <div class="section-heading"><p class="eyebrow">Member access</p><h1>Create your account</h1><p>No deposit is required. Opportunities and rewards depend on eligibility and verified completion.</p></div>
  <?php if ($errors): ?><div class="alert alert-error" role="alert"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form method="post" class="card form-card">
    <?= csrf_field() ?>
    <label>Full name<input type="text" name="full_name" maxlength="120" autocomplete="name" required value="<?= e($fullName) ?>"></label>
    <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($email) ?>"></label>
    <label>Password<input type="password" name="password" minlength="10" autocomplete="new-password" required><small>At least 10 characters with uppercase, lowercase, and a number.</small></label>
    <label>Confirm password<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
    <button class="btn" type="submit">Create account</button>
    <p>Already a member? <a href="/login.php">Log in</a>.</p>
  </form>
</div></section>
<?php render_footer();
