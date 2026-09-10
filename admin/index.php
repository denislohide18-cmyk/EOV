<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();

$stats = db()->query("SELECT
    (SELECT COUNT(*) FROM users) AS users_total,
    (SELECT COUNT(*) FROM users WHERE status = 'active') AS users_active,
    (SELECT COUNT(*) FROM opportunities WHERE status = 'active') AS opportunities_active,
    (SELECT COUNT(*) FROM applications WHERE status = 'pending') AS applications_pending,
    (SELECT COUNT(*) FROM applications WHERE status = 'completed') AS applications_completed,
    (SELECT COUNT(*) FROM rewards WHERE status = 'pending') AS rewards_pending,
    (SELECT COUNT(*) FROM rewards WHERE status = 'paid') AS rewards_paid")->fetch();

$recentApplications = db()->query("SELECT a.id, a.status, a.submitted_at, u.full_name, o.title
    FROM applications a JOIN users u ON u.id = a.user_id JOIN opportunities o ON o.id = a.opportunity_id
    ORDER BY a.submitted_at DESC LIMIT 8")->fetchAll();
$recentLogs = db()->query("SELECT l.action, l.entity_type, l.entity_id, l.created_at, u.full_name
    FROM admin_logs l JOIN users u ON u.id = l.admin_id ORDER BY l.created_at DESC LIMIT 8")->fetchAll();

render_admin_header('Overview');
?>
<section class="page-heading admin-page-head">
  <div><p class="eyebrow">Operations snapshot</p><h1>Welcome, <?= e($admin['full_name']) ?></h1><p>Review platform activity and items awaiting attention.</p></div>
  <a class="btn" href="/admin/opportunity-create.php">Create opportunity</a>
</section>
<section class="stat-grid admin-stats" aria-label="Platform statistics">
  <article class="stat-card admin-stat"><span>Total users</span><strong><?= number_format((int) $stats['users_total']) ?></strong><small><?= number_format((int) $stats['users_active']) ?> active</small></article>
  <article class="stat-card admin-stat"><span>Active opportunities</span><strong><?= number_format((int) $stats['opportunities_active']) ?></strong><a href="/admin/opportunities.php">Manage catalog</a></article>
  <article class="stat-card admin-stat"><span>Pending applications</span><strong><?= number_format((int) $stats['applications_pending']) ?></strong><small><?= number_format((int) $stats['applications_completed']) ?> completed overall</small></article>
  <article class="stat-card admin-stat"><span>Pending rewards</span><strong><?= number_format((int) $stats['rewards_pending']) ?></strong><small><?= number_format((int) $stats['rewards_paid']) ?> marked paid</small></article>
</section>
<div class="admin-grid admin-grid-two two-column dashboard-layout">
  <section class="panel admin-card">
    <div class="panel-heading admin-card-head"><h2>Recent applications</h2><a href="/admin/applications.php">View all</a></div>
    <div class="table-wrap"><table><thead><tr><th>Applicant</th><th>Opportunity</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
    <?php if (!$recentApplications): ?><tr><td colspan="4">No applications yet.</td></tr><?php endif; ?>
    <?php foreach ($recentApplications as $application): ?><tr><td><a href="/admin/application-view.php?id=<?= (int) $application['id'] ?>"><?= e($application['full_name']) ?></a></td><td><?= e($application['title']) ?></td><td><?= status_badge($application['status']) ?></td><td><time datetime="<?= e($application['submitted_at']) ?>"><?= e(date('M j, Y', strtotime($application['submitted_at']))) ?></time></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
  <section class="panel admin-card">
    <div class="panel-heading admin-card-head"><h2>Audit activity</h2></div>
    <div class="activity-list">
    <?php if (!$recentLogs): ?><p>No administrative changes recorded yet.</p><?php endif; ?>
    <?php foreach ($recentLogs as $log): ?><article><strong><?= e(ucwords(str_replace('_', ' ', $log['action']))) ?></strong><p><?= e($log['full_name']) ?> · <?= e($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></p><time datetime="<?= e($log['created_at']) ?>"><?= e(date('M j, Y H:i', strtotime($log['created_at']))) ?> UTC</time></article><?php endforeach; ?>
    </div>
  </section>
</div>
<?php render_admin_footer(); ?>
