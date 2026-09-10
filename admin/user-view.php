<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();
$userId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$userId) {
    http_response_code(404);
    exit('User not found.');
}

$stmt = db()->prepare('SELECT id, full_name, email, role, status, profile_bio, created_at, updated_at FROM users WHERE id = ?');
$stmt->execute([(int) $userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}
$applicationsStmt = db()->prepare('SELECT a.id, a.status, a.submitted_at, a.reviewed_at, o.title FROM applications a JOIN opportunities o ON o.id = a.opportunity_id WHERE a.user_id = ? ORDER BY a.submitted_at DESC');
$applicationsStmt->execute([(int) $userId]);
$applications = $applicationsStmt->fetchAll();
$rewardsStmt = db()->prepare('SELECT r.id, r.amount, r.currency, r.status, r.created_at, r.approved_at, r.application_id, o.title FROM rewards r JOIN applications a ON a.id = r.application_id JOIN opportunities o ON o.id = a.opportunity_id WHERE r.user_id = ? ORDER BY r.created_at DESC');
$rewardsStmt->execute([(int) $userId]);
$rewards = $rewardsStmt->fetchAll();
$logsStmt = db()->prepare("SELECT l.action, l.details, l.created_at, a.full_name AS admin_name FROM admin_logs l JOIN users a ON a.id = l.admin_id WHERE l.entity_type = 'user' AND l.entity_id = ? ORDER BY l.created_at DESC LIMIT 25");
$logsStmt->execute([(int) $userId]);
$logs = $logsStmt->fetchAll();
$rewardTotals = [];
foreach ($rewards as $reward) {
    $rewardTotals[$reward['currency']] = ($rewardTotals[$reward['currency']] ?? 0) + (float) $reward['amount'];
}
$rewardTotalLabels = [];
foreach ($rewardTotals as $currency => $amount) {
    $rewardTotalLabels[] = $currency . ' ' . number_format($amount, 2);
}

render_admin_header('User details');
?>
<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/admin/users.php">Users</a><span>/</span><span><?= e($user['full_name']) ?></span></nav>
<section class="page-heading admin-page-head"><div><p class="eyebrow">User #<?= (int) $user['id'] ?></p><h1><?= e($user['full_name']) ?></h1><p><?= e($user['email']) ?></p></div><div class="badge-row"><?= status_badge($user['role']) ?> <?= status_badge($user['status']) ?></div></section>
<div class="admin-grid admin-grid-two two-column">
  <section class="panel admin-card"><h2>Profile</h2><dl class="detail-list"><dt>Email</dt><dd><a href="mailto:<?= e($user['email']) ?>"><?= e($user['email']) ?></a></dd><dt>Joined</dt><dd><?= e(date('M j, Y H:i', strtotime($user['created_at']))) ?> UTC</dd><dt>Last updated</dt><dd><?= e(date('M j, Y H:i', strtotime($user['updated_at']))) ?> UTC</dd><dt>Bio</dt><dd><?= $user['profile_bio'] ? nl2br(e($user['profile_bio'])) : 'No profile bio provided.' ?></dd></dl></section>
  <section class="panel admin-card"><h2>Account totals</h2><dl class="detail-list"><dt>Applications</dt><dd><?= number_format(count($applications)) ?></dd><dt>Rewards</dt><dd><?= number_format(count($rewards)) ?></dd><dt>Reward value</dt><dd><?= $rewardTotalLabels ? e(implode(', ', $rewardTotalLabels)) : 'None' ?></dd></dl></section>
</div>
<section class="panel"><div class="panel-heading"><h2>Application history</h2></div><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Status</th><th>Submitted</th><th>Reviewed</th></tr></thead><tbody><?php if (!$applications): ?><tr><td colspan="4">No applications.</td></tr><?php endif; ?><?php foreach ($applications as $application): ?><tr><td><a href="/admin/application-view.php?id=<?= (int) $application['id'] ?>"><?= e($application['title']) ?></a></td><td><?= status_badge($application['status']) ?></td><td><?= e(date('M j, Y H:i', strtotime($application['submitted_at']))) ?></td><td><?= $application['reviewed_at'] ? e(date('M j, Y H:i', strtotime($application['reviewed_at']))) : 'Not reviewed' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><div class="panel-heading"><h2>Reward history</h2><a href="/admin/rewards.php?q=<?= urlencode($user['email']) ?>">Open rewards</a></div><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead><tbody><?php if (!$rewards): ?><tr><td colspan="4">No rewards.</td></tr><?php endif; ?><?php foreach ($rewards as $reward): ?><tr><td><a href="/admin/application-view.php?id=<?= (int) $reward['application_id'] ?>"><?= e($reward['title']) ?></a></td><td><?= e($reward['currency']) ?> <?= number_format((float) $reward['amount'], 2) ?></td><td><?= status_badge($reward['status']) ?></td><td><?= e(date('M j, Y', strtotime($reward['created_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><h2>Administrative history</h2><div class="activity-list"><?php if (!$logs): ?><p>No account changes have been recorded.</p><?php endif; ?><?php foreach ($logs as $log): ?><article><strong><?= e(ucwords(str_replace('_', ' ', $log['action']))) ?></strong><p>By <?= e($log['admin_name']) ?><?= $log['details'] ? ' · ' . e($log['details']) : '' ?></p><time datetime="<?= e($log['created_at']) ?>"><?= e(date('M j, Y H:i', strtotime($log['created_at']))) ?> UTC</time></article><?php endforeach; ?></div></section>
<?php render_admin_footer(); ?>
