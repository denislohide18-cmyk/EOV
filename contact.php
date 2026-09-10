<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = current_user();
$name = $user['full_name'] ?? '';
$email = $user['email'] ?? '';
$subject = '';
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));
    $website = trim((string) ($_POST['website'] ?? ''));
    if ($website !== '') {
        $errors[] = 'Unable to submit this message.';
    }
    if (strlen($name) < 2 || strlen($name) > 120) {
        $errors[] = 'Enter your name (2 to 120 characters).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($subject) < 3 || strlen($subject) > 180) {
        $errors[] = 'Enter a subject (3 to 180 characters).';
    }
    if (strlen($message) < 20 || strlen($message) > 10000) {
        $errors[] = 'Enter a message between 20 and 10,000 characters.';
    }
    if (!$errors) {
        $stmt = db()->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $subject, $message]);
        flash('success', 'Your message has been received. We will respond using the email you provided.');
        redirect('/contact.php');
    }
}

$supportStmt = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = 'support_email' LIMIT 1");
$supportStmt->execute();
$supportEmail = $supportStmt->fetchColumn() ?: 'support@earnonventure.com';

render_header('Contact | ' . SITE_NAME, 'Contact Earn On Venture about account, opportunity, privacy, or reward questions.');
?>
<section class="page-hero"><div class="container"><p class="eyebrow">Support</p><h1>Contact us</h1><p>Ask about your account, an opportunity, a review decision, or your privacy rights.</p></div></section>
<section class="section"><div class="container detail-grid">
  <div class="prose"><h2>Before sending</h2><p>Include the relevant opportunity or application name, but never send your password or full payment credentials.</p><h3>Email</h3><p><a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a></p><h3>Response expectations</h3><p>Messages are stored for support follow-up. Response times are not guaranteed, and this form is not a live-chat service.</p></div>
  <div><?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?><form method="post" class="card form-card">
    <?= csrf_field() ?><div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <label>Name<input type="text" name="name" maxlength="120" autocomplete="name" required value="<?= e($name) ?>"></label>
    <label>Email address<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?= e($email) ?>"></label>
    <label>Subject<input type="text" name="subject" maxlength="180" required value="<?= e($subject) ?>"></label>
    <label>Message<textarea name="message" minlength="20" maxlength="10000" rows="8" required><?= e($message) ?></textarea></label>
    <button class="btn" type="submit">Send message</button>
  </form></div>
</div></section>
<?php render_footer();
