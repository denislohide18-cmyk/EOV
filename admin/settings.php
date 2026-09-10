<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();

$stmt = db()->prepare("SELECT setting_key, setting_value, updated_at FROM settings WHERE setting_key IN ('support_email', 'platform_notice')");
$stmt->execute();
$settings = [];
foreach ($stmt->fetchAll() as $row) $settings[$row['setting_key']] = $row;
$supportEmail = $settings['support_email']['setting_value'] ?? '';
$platformNotice = $settings['platform_notice']['setting_value'] ?? '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $supportEmail = strtolower(trim((string) ($_POST['support_email'] ?? '')));
    $platformNotice = trim((string) ($_POST['platform_notice'] ?? ''));
    if (!filter_var($supportEmail, FILTER_VALIDATE_EMAIL) || strlen($supportEmail) > 190) $error = 'Enter a valid support email address.';
    elseif ($platformNotice === '' || strlen($platformNotice) > 2000) $error = 'Platform notice must contain between 1 and 2,000 characters.';
    if (!$error) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $upsert = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_by=VALUES(updated_by)');
            $upsert->execute(['support_email', $supportEmail, (int) $admin['id']]);
            $upsert->execute(['platform_notice', $platformNotice, (int) $admin['id']]);
            admin_log('settings_updated', 'settings', null, ['keys' => ['support_email', 'platform_notice']]);
            $pdo->commit();
            flash('success', 'Platform settings updated.');
            redirect('/admin/settings.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Could not update platform settings.';
        }
    }
}

render_admin_header('Settings');
?>
<section class="page-heading admin-page-head"><div><p class="eyebrow">Platform configuration</p><h1>Settings</h1><p>Manage public support and notice content stored by the platform.</p></div></section>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="panel admin-card form-card form-stack narrow-panel">
  <?= csrf_field() ?>
  <label for="support_email">Support email</label><input id="support_email" name="support_email" type="email" maxlength="190" value="<?= e($supportEmail) ?>" required><p class="form-help">Used for platform support contact information.</p>
  <label for="platform_notice">Platform notice</label><textarea id="platform_notice" name="platform_notice" rows="6" maxlength="2000" required><?= e($platformNotice) ?></textarea><p class="form-help">Keep availability, eligibility, and reward expectations factual.</p>
  <div class="button-row"><button class="btn" type="submit">Save settings</button></div>
</form>
<?php render_admin_footer(); ?>
