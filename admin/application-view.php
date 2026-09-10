<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Application not found.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $lock = $pdo->prepare('SELECT a.id, a.user_id, a.opportunity_id, a.status, a.admin_notes, o.reward_amount, o.currency, EXISTS(SELECT 1 FROM activity_submissions s WHERE s.application_id = a.id) AS has_submission FROM applications a JOIN opportunities o ON o.id=a.opportunity_id WHERE a.id=? FOR UPDATE');
        $lock->execute([(int) $id]);
        $application = $lock->fetch();
        if (!$application) throw new RuntimeException('Application not found.');

        if ($action === 'notes') {
            $notes = trim((string) ($_POST['admin_notes'] ?? ''));
            if (strlen($notes) > 20000) throw new RuntimeException('Internal notes are too long.');
            $update = $pdo->prepare('UPDATE applications SET admin_notes=? WHERE id=?');
            $update->execute([$notes !== '' ? $notes : null, (int) $id]);
            admin_log('application_notes_updated', 'application', (int) $id, ['had_notes' => $application['admin_notes'] !== null, 'has_notes' => $notes !== '']);
            $message = 'Internal notes saved.';
        } elseif ($action === 'status') {
            $newStatus = (string) ($_POST['status'] ?? '');
            $transitions = ['pending' => ['approved', 'rejected'], 'approved' => ['completed', 'rejected'], 'rejected' => [], 'completed' => []];
            if (!in_array($newStatus, $transitions[$application['status']] ?? [], true)) throw new RuntimeException('That status transition is not allowed.');
            if ($newStatus === 'completed' && !(bool) $application['has_submission']) throw new RuntimeException('An activity submission is required before completion.');
            $update = $pdo->prepare('UPDATE applications SET status=?, reviewed_at=NOW() WHERE id=?');
            $update->execute([$newStatus, (int) $id]);
            record_application_status((int) $id, $application['status'], $newStatus, (int) $admin['id']);
            if ($newStatus === 'completed') {
                $reward = $pdo->prepare("INSERT INTO rewards (user_id, application_id, amount, currency, status) VALUES (?, ?, ?, ?, 'pending')");
                $reward->execute([(int) $application['user_id'], (int) $id, $application['reward_amount'], $application['currency']]);
                admin_log('reward_created', 'reward', (int) $pdo->lastInsertId(), ['application_id' => (int) $id, 'status' => 'pending']);
            }
            admin_log('application_status_changed', 'application', (int) $id, ['from' => $application['status'], 'to' => $newStatus]);
            $message = $newStatus === 'completed' ? 'Application completed and a pending reward was created for separate review.' : 'Application status updated.';
        } else {
            throw new RuntimeException('Invalid application action.');
        }
        $pdo->commit();
        flash('success', $message);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not update the application.');
    }
    redirect('/admin/application-view.php?id=' . (int) $id);
}

$stmt = db()->prepare('SELECT a.*, u.full_name, u.email, u.status AS user_status, o.title, o.description, o.eligibility, o.requirements, o.instructions, o.reward_amount, o.currency FROM applications a JOIN users u ON u.id=a.user_id JOIN opportunities o ON o.id=a.opportunity_id WHERE a.id=?');
$stmt->execute([(int) $id]);
$application = $stmt->fetch();
if (!$application) { http_response_code(404); exit('Application not found.'); }
$submissionStmt = db()->prepare('SELECT response_text, reference_url, submitted_at, updated_at FROM activity_submissions WHERE application_id=?');
$submissionStmt->execute([(int) $id]);
$submission = $submissionStmt->fetch();
$referenceUrl = $submission ? (string) ($submission['reference_url'] ?? '') : '';
$referenceUrlIsSafe = filter_var($referenceUrl, FILTER_VALIDATE_URL)
    && in_array(strtolower((string) parse_url($referenceUrl, PHP_URL_SCHEME)), ['http', 'https'], true);
$historyStmt = db()->prepare('SELECT h.old_status, h.new_status, h.changed_at, u.full_name FROM application_status_history h JOIN users u ON u.id=h.changed_by WHERE h.application_id=? ORDER BY h.changed_at DESC, h.id DESC');
$historyStmt->execute([(int) $id]);
$history = $historyStmt->fetchAll();
$rewardStmt = db()->prepare('SELECT id, amount, currency, status, created_at, approved_at FROM rewards WHERE application_id=?');
$rewardStmt->execute([(int) $id]);
$reward = $rewardStmt->fetch();
$transitions = ['pending' => ['approved', 'rejected'], 'approved' => ['completed', 'rejected'], 'rejected' => [], 'completed' => []];

render_admin_header('Application review');
?>
<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/admin/applications.php">Applications</a><span>/</span><span>#<?= (int) $id ?></span></nav>
<section class="page-heading"><div><p class="eyebrow">Application #<?= (int) $id ?></p><h1><?= e($application['title']) ?></h1><p>Submitted by <a href="/admin/user-view.php?id=<?= (int) $application['user_id'] ?>"><?= e($application['full_name']) ?></a> on <?= e(date('M j, Y H:i', strtotime($application['submitted_at']))) ?> UTC.</p></div><?= status_badge($application['status']) ?></section>
<div class="admin-grid admin-grid-two">
  <section class="panel"><h2>Applicant</h2><dl class="detail-list"><dt>Name</dt><dd><?= e($application['full_name']) ?></dd><dt>Email</dt><dd><a href="mailto:<?= e($application['email']) ?>"><?= e($application['email']) ?></a></dd><dt>Account</dt><dd><?= status_badge($application['user_status']) ?></dd><dt>Reviewed</dt><dd><?= $application['reviewed_at'] ? e(date('M j, Y H:i', strtotime($application['reviewed_at']))) . ' UTC' : 'Not reviewed' ?></dd></dl></section>
  <section class="panel"><h2>Opportunity terms</h2><dl class="detail-list"><dt>Reward</dt><dd><?= e($application['currency']) ?> <?= number_format((float) $application['reward_amount'], 2) ?></dd><dt>Eligibility</dt><dd><?= nl2br(e($application['eligibility'])) ?></dd><dt>Requirements</dt><dd><?= nl2br(e($application['requirements'])) ?></dd></dl></section>
</div>
<section class="panel"><h2>Activity submission</h2><?php if (!$submission): ?><p>No activity submission has been provided.</p><?php else: ?><dl class="detail-list"><dt>Response</dt><dd class="prose"><?= nl2br(e($submission['response_text'])) ?></dd><dt>Reference URL</dt><dd><?php if ($referenceUrlIsSafe): ?><a href="<?= e($referenceUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($referenceUrl) ?></a><?php elseif ($referenceUrl !== ''): ?><?= e($referenceUrl) ?><?php else: ?>None<?php endif; ?></dd><dt>Submitted</dt><dd><?= e(date('M j, Y H:i', strtotime($submission['submitted_at']))) ?> UTC</dd></dl><?php endif; ?></section>
<div class="admin-grid admin-grid-two">
  <section class="panel"><h2>Review decision</h2><?php if ($transitions[$application['status']]): ?><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="status"><label for="status">New status</label><select id="status" name="status" required><option value="">Choose a decision</option><?php foreach ($transitions[$application['status']] as $option): ?><option value="<?= $option ?>"><?= ucfirst($option) ?></option><?php endforeach; ?></select><p class="form-help">Completing an approved application creates a pending reward only. Payment is never automatic.</p><button class="btn" type="submit">Update status</button></form><?php else: ?><p>This application is in a terminal state and has no further transitions.</p><?php endif; ?><?php if ($reward): ?><hr><h3>Reward</h3><p><a href="/admin/rewards.php?q=<?= (int) $reward['id'] ?>"><?= e($reward['currency']) ?> <?= number_format((float) $reward['amount'], 2) ?></a> <?= status_badge($reward['status']) ?></p><?php endif; ?></section>
  <section class="panel"><h2>Internal notes</h2><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="notes"><label for="admin_notes">Visible to administrators only</label><textarea id="admin_notes" name="admin_notes" rows="10" maxlength="20000"><?= e($application['admin_notes']) ?></textarea><button class="btn" type="submit">Save notes</button></form></section>
</div>
<section class="panel"><h2>Status history</h2><div class="activity-list"><?php if (!$history): ?><p>No status changes recorded.</p><?php endif; ?><?php foreach ($history as $entry): ?><article><strong><?= $entry['old_status'] ? e(ucfirst($entry['old_status'])) . ' to ' : '' ?><?= e(ucfirst($entry['new_status'])) ?></strong><p>Changed by <?= e($entry['full_name']) ?></p><time datetime="<?= e($entry['changed_at']) ?>"><?= e(date('M j, Y H:i', strtotime($entry['changed_at']))) ?> UTC</time></article><?php endforeach; ?></div></section>
<?php render_admin_footer(); ?>
