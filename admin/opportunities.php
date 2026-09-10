<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string) ($_POST['action'] ?? '');
    if (!$id) {
        flash('error', 'Invalid opportunity.');
        redirect('/admin/opportunities.php');
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, title, status FROM opportunities WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $opportunity = $stmt->fetch();
        if (!$opportunity) {
            throw new RuntimeException('Opportunity not found.');
        }
        if ($action === 'delete') {
            $count = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE opportunity_id = ?');
            $count->execute([(int) $id]);
            if ((int) $count->fetchColumn() > 0) {
                throw new RuntimeException('This opportunity has applications and cannot be deleted. Set it inactive instead.');
            }
            $delete = $pdo->prepare('DELETE FROM opportunities WHERE id = ?');
            $delete->execute([(int) $id]);
            admin_log('opportunity_deleted', 'opportunity', (int) $id, ['title' => $opportunity['title']]);
            $message = 'Opportunity deleted.';
        } elseif ($action === 'status') {
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
                throw new RuntimeException('Invalid opportunity status.');
            }
            $update = $pdo->prepare('UPDATE opportunities SET status = ? WHERE id = ?');
            $update->execute([$status, (int) $id]);
            admin_log('opportunity_status_changed', 'opportunity', (int) $id, ['from' => $opportunity['status'], 'to' => $status]);
            $message = 'Opportunity status updated.';
        } else {
            throw new RuntimeException('Invalid opportunity action.');
        }
        $pdo->commit();
        flash('success', $message);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not update the opportunity.');
    }
    redirect('/admin/opportunities.php');
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['draft', 'active', 'inactive'], true) ? (string) $_GET['status'] : '';
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(o.title LIKE ? OR o.category LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
$sql = 'SELECT o.id, o.title, o.category, o.reward_amount, o.currency, o.status, o.featured, o.updated_at, COUNT(a.id) AS application_count FROM opportunities o LEFT JOIN applications a ON a.opportunity_id = o.id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' GROUP BY o.id ORDER BY o.updated_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$opportunities = $stmt->fetchAll();

render_admin_header('Opportunities');
?>
<section class="page-heading"><div><p class="eyebrow">Catalog management</p><h1>Opportunities</h1><p>Publish and maintain clear participation requirements.</p></div><a class="btn" href="/admin/opportunity-create.php">Create opportunity</a></section>
<section class="panel filter-panel"><form method="get" class="filter-form"><label for="q">Search</label><input id="q" name="q" value="<?= e($search) ?>" placeholder="Title or category"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach (['draft', 'active', 'inactive'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select><button class="btn" type="submit">Filter</button><a class="btn btn-secondary" href="/admin/opportunities.php">Reset</a></form></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Reward</th><th>Status</th><th>Applications</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
<?php if (!$opportunities): ?><tr><td colspan="6">No opportunities match these filters.</td></tr><?php endif; ?>
<?php foreach ($opportunities as $opportunity): ?><tr><td><strong><?= e($opportunity['title']) ?></strong><small><?= e($opportunity['category']) ?><?= $opportunity['featured'] ? ' · Featured' : '' ?></small></td><td><?= e($opportunity['currency']) ?> <?= number_format((float) $opportunity['reward_amount'], 2) ?></td><td><?= status_badge($opportunity['status']) ?></td><td><?= number_format((int) $opportunity['application_count']) ?></td><td><?= e(date('M j, Y', strtotime($opportunity['updated_at']))) ?></td><td><div class="table-actions"><a href="/admin/opportunity-edit.php?id=<?= (int) $opportunity['id'] ?>">Edit</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $opportunity['id'] ?>"><input type="hidden" name="action" value="status"><select name="status" aria-label="Change status for <?= e($opportunity['title']) ?>" onchange="this.form.submit()"><?php foreach (['draft', 'active', 'inactive'] as $option): ?><option value="<?= $option ?>" <?= $opportunity['status'] === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select></form><form method="post" onsubmit="return confirm('Delete this opportunity? This cannot be undone.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $opportunity['id'] ?>"><input type="hidden" name="action" value="delete"><button class="link-button danger-link" type="submit" <?= (int) $opportunity['application_count'] > 0 ? 'disabled title="Applications prevent deletion"' : '' ?>>Delete</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php render_admin_footer(); ?>
