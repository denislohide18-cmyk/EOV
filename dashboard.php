<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = require_auth();

$applicationStmt = db()->prepare('SELECT a.id, a.status, a.submitted_at, o.id AS opportunity_id, o.title, o.category, o.reward_amount, o.currency, o.status AS opportunity_status, s.id AS submission_id, s.submitted_at AS activity_submitted_at, r.id AS reward_id, r.amount AS reward_amount_actual, r.currency AS reward_currency, r.status AS reward_status FROM applications a JOIN opportunities o ON o.id = a.opportunity_id LEFT JOIN activity_submissions s ON s.application_id = a.id LEFT JOIN rewards r ON r.application_id = a.id AND r.user_id = a.user_id WHERE a.user_id = ? ORDER BY a.submitted_at DESC');
$applicationStmt->execute([$user['id']]);
$applications = $applicationStmt->fetchAll();

$rewardStmt = db()->prepare('SELECT id, application_id, amount, currency, status, created_at, approved_at FROM rewards WHERE user_id = ? ORDER BY created_at DESC');
$rewardStmt->execute([$user['id']]);
$rewards = $rewardStmt->fetchAll();

$availableCount = (int) db()->query("SELECT COUNT(*) FROM opportunities WHERE status = 'active'")->fetchColumn();
$eligibleRewardCount = 0;
foreach ($rewards as $reward) {
    if (in_array($reward['status'], ['approved', 'paid'], true)) {
        $eligibleRewardCount++;
    }
}

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
foreach ($applications as $application) {
    $counts[$application['status']]++;
}

render_header('Member dashboard | ' . SITE_NAME, 'View your applications, activity submissions, and actual reward records.');
?>
<section class="page-hero"><div class="container"><p class="eyebrow">Member dashboard</p><h1>Welcome, <?= e($user['full_name']) ?></h1><p>Your account shows only applications, submissions, and reward records owned by you.</p></div></section>
<section class="section"><div class="container">
  <nav class="member-nav" aria-label="Dashboard"><a class="active" href="/dashboard.php">Overview</a><a href="/opportunities.php">Opportunities</a><a href="#activities">My Activities</a><a href="#rewards">Rewards</a><a href="/profile.php">Profile</a><a href="/profile.php#security">Security</a><a href="/logout.php">Logout</a></nav>
  <div class="stats-grid"><div class="stat-card"><span>Account status</span><strong><?= e(ucfirst($user['status'])) ?></strong></div><div class="stat-card"><span>Available opportunities</span><strong><?= $availableCount ?></strong></div><div class="stat-card"><span>Active applications</span><strong><?= $counts['pending'] + $counts['approved'] ?></strong></div><div class="stat-card"><span>Completed activities</span><strong><?= $counts['completed'] ?></strong></div><div class="stat-card"><span>Eligible reward records</span><strong><?= $eligibleRewardCount ?></strong></div><div class="stat-card"><span>Profile</span><strong><?= e($user['email']) ?></strong></div></div>
  <div class="section-heading split" id="activities"><div><p class="eyebrow">Your activity</p><h2>Applications</h2></div><a class="btn btn-small" href="/opportunities.php">Find opportunities</a></div>
  <?php if ($applications): ?><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Application</th><th>Activity</th><th>Reward record</th><th>Next step</th></tr></thead><tbody><?php foreach ($applications as $application): ?><tr>
    <td><a href="/opportunity.php?id=<?= (int) $application['opportunity_id'] ?>"><?= e($application['title']) ?></a><small><?= e($application['category']) ?> &middot; Applied <?= e(date('M j, Y', strtotime($application['submitted_at']))) ?></small></td>
    <td><?= status_badge($application['status']) ?></td>
    <td><?php if ($application['submission_id']): ?><?= status_badge('pending') ?> <small>Submitted <?= e(date('M j, Y', strtotime($application['activity_submitted_at']))) ?></small><?php elseif ($application['status'] === 'approved' && $application['opportunity_status'] === 'active'): ?>Ready to submit<?php else: ?>Not submitted<?php endif; ?></td>
    <td><?php if ($application['reward_id']): ?><strong><?= e($application['reward_currency']) ?> <?= e(number_format((float) $application['reward_amount_actual'], 2)) ?></strong> <?= status_badge($application['reward_status']) ?><?php else: ?>No reward created<?php endif; ?></td>
    <td><?php if ($application['status'] === 'approved' && !$application['submission_id'] && $application['opportunity_status'] === 'active'): ?><a href="/submit-activity.php?application_id=<?= (int) $application['id'] ?>">Submit activity</a><?php else: ?><a href="/opportunity.php?id=<?= (int) $application['opportunity_id'] ?>">View details</a><?php endif; ?></td>
  </tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><h3>No applications yet</h3><p>Browse active opportunities and review eligibility before applying.</p><a class="btn" href="/opportunities.php">Explore opportunities</a></div><?php endif; ?>

  <div class="section-heading" id="rewards"><p class="eyebrow">Recorded amounts only</p><h2>Your rewards</h2><p>Amounts appear here only after a reward record is created. Pending or approved is not the same as paid.</p></div>
  <?php if ($rewards): ?><div class="table-wrap"><table><thead><tr><th>Created</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php foreach ($rewards as $reward): ?><tr><td><?= e(date('M j, Y', strtotime($reward['created_at']))) ?></td><td><?= e($reward['currency']) ?> <?= e(number_format((float) $reward['amount'], 2)) ?></td><td><?= status_badge($reward['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><p>No reward records have been created for your account.</p></div><?php endif; ?>
  <div class="account-links"><a href="/profile.php">Update profile and security</a></div>
</div></section>
<?php render_footer();
