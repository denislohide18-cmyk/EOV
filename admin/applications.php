<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();

$search = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'completed'], true) ? (string) $_GET['status'] : '';
$opportunityId = filter_var($_GET['opportunity_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$where = [];
$params = [];
if ($search !== '') { $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR o.title LIKE ?)'; $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%']; }
if ($status !== '') { $where[] = 'a.status = ?'; $params[] = $status; }
if ($opportunityId) { $where[] = 'a.opportunity_id = ?'; $params[] = $opportunityId; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = db()->prepare('SELECT COUNT(*) FROM applications a JOIN users u ON u.id=a.user_id JOIN opportunities o ON o.id=a.opportunity_id' . $whereSql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$stmt = db()->prepare('SELECT a.id, a.status, a.submitted_at, a.reviewed_at, u.full_name, u.email, o.title FROM applications a JOIN users u ON u.id=a.user_id JOIN opportunities o ON o.id=a.opportunity_id' . $whereSql . ' ORDER BY a.submitted_at DESC LIMIT ? OFFSET ?');
$position = 1;
foreach ($params as $param) $stmt->bindValue($position++, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
$stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($position, ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$applications = $stmt->fetchAll();
$opportunities = db()->query('SELECT id, title FROM opportunities ORDER BY title')->fetchAll();

render_admin_header('Applications');
?>
<section class="page-heading"><div><p class="eyebrow">Review queue</p><h1>Applications</h1><p><?= number_format($total) ?> application<?= $total === 1 ? '' : 's' ?> match the current filters.</p></div></section>
<section class="panel filter-panel"><form method="get" class="filter-form"><label for="q">Search</label><input id="q" name="q" value="<?= e($search) ?>" placeholder="Applicant, email, or opportunity"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach (['pending', 'approved', 'rejected', 'completed'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select><label for="opportunity_id">Opportunity</label><select id="opportunity_id" name="opportunity_id"><option value="">All opportunities</option><?php foreach ($opportunities as $opportunity): ?><option value="<?= (int) $opportunity['id'] ?>" <?= $opportunityId === (int) $opportunity['id'] ? 'selected' : '' ?>><?= e($opportunity['title']) ?></option><?php endforeach; ?></select><button class="btn" type="submit">Filter</button><a class="btn btn-secondary" href="/admin/applications.php">Reset</a></form></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Applicant</th><th>Opportunity</th><th>Status</th><th>Submitted</th><th>Reviewed</th><th></th></tr></thead><tbody><?php if (!$applications): ?><tr><td colspan="6">No applications match these filters.</td></tr><?php endif; ?><?php foreach ($applications as $application): ?><tr><td><strong><?= e($application['full_name']) ?></strong><small><?= e($application['email']) ?></small></td><td><?= e($application['title']) ?></td><td><?= status_badge($application['status']) ?></td><td><?= e(date('M j, Y H:i', strtotime($application['submitted_at']))) ?></td><td><?= $application['reviewed_at'] ? e(date('M j, Y H:i', strtotime($application['reviewed_at']))) : 'Not reviewed' ?></td><td><a href="/admin/application-view.php?id=<?= (int) $application['id'] ?>">Review</a></td></tr><?php endforeach; ?></tbody></table></div><?= pagination_links($page, $pages, ['q' => $search, 'status' => $status, 'opportunity_id' => $opportunityId ?: '']) ?></section>
<?php render_admin_footer(); ?>
