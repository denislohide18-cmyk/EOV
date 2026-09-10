<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $newStatus = (string) ($_POST['status'] ?? '');
    $pdo = db();
    try {
        if (!$id) throw new RuntimeException('Invalid reward.');
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, status FROM rewards WHERE id=? FOR UPDATE');
        $stmt->execute([(int) $id]);
        $reward = $stmt->fetch();
        if (!$reward) throw new RuntimeException('Reward not found.');
        $transitions = ['pending' => ['approved', 'cancelled'], 'approved' => ['paid', 'cancelled'], 'paid' => [], 'cancelled' => []];
        if (!in_array($newStatus, $transitions[$reward['status']] ?? [], true)) throw new RuntimeException('That reward status transition is not allowed.');
        if ($newStatus === 'approved') {
            $update = $pdo->prepare('UPDATE rewards SET status=?, approved_at=NOW() WHERE id=?');
        } else {
            $update = $pdo->prepare('UPDATE rewards SET status=? WHERE id=?');
        }
        $update->execute([$newStatus, (int) $id]);
        admin_log('reward_status_changed', 'reward', (int) $id, ['from' => $reward['status'], 'to' => $newStatus]);
        $pdo->commit();
        flash('success', 'Reward status updated.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not update the reward.');
    }
    $return = http_build_query(array_filter(['q' => trim((string) ($_POST['return_q'] ?? '')), 'status' => (string) ($_POST['return_status'] ?? ''), 'currency' => (string) ($_POST['return_currency'] ?? ''), 'page' => max(1, (int) ($_POST['return_page'] ?? 1))], static fn($value): bool => $value !== ''));
    redirect('/admin/rewards.php' . ($return ? '?' . $return : ''));
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'paid', 'cancelled'], true) ? (string) $_GET['status'] : '';
$currency = preg_match('/^[A-Z]{3}$/', (string) ($_GET['currency'] ?? '')) ? (string) $_GET['currency'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$where = [];
$params = [];
if ($search !== '') { $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR o.title LIKE ? OR CAST(r.id AS CHAR) = ?)'; $params = ['%' . $search . '%', '%' . $search . '%', '%' . $search . '%', $search]; }
if ($status !== '') { $where[] = 'r.status=?'; $params[] = $status; }
if ($currency !== '') { $where[] = 'r.currency=?'; $params[] = $currency; }
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$joins = ' FROM rewards r JOIN users u ON u.id=r.user_id JOIN applications a ON a.id=r.application_id JOIN opportunities o ON o.id=a.opportunity_id';
$count = db()->prepare('SELECT COUNT(*)' . $joins . $whereSql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$stmt = db()->prepare('SELECT r.id, r.application_id, r.amount, r.currency, r.status, r.created_at, r.approved_at, u.full_name, u.email, o.title' . $joins . $whereSql . ' ORDER BY r.created_at DESC LIMIT ? OFFSET ?');
$position = 1;
foreach ($params as $param) $stmt->bindValue($position++, $param, PDO::PARAM_STR);
$stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($position, ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->execute();
$rewards = $stmt->fetchAll();
$currencies = db()->query('SELECT DISTINCT currency FROM rewards ORDER BY currency')->fetchAll(PDO::FETCH_COLUMN);
$transitions = ['pending' => ['approved', 'cancelled'], 'approved' => ['paid', 'cancelled'], 'paid' => [], 'cancelled' => []];

render_admin_header('Rewards');
?>
<section class="page-heading"><div><p class="eyebrow">Separate payment review</p><h1>Rewards</h1><p><?= number_format($total) ?> reward<?= $total === 1 ? '' : 's' ?> match the current filters. Status changes record administration only; this page does not send payments.</p></div></section>
<section class="panel filter-panel"><form method="get" class="filter-form"><label for="q">Search</label><input id="q" name="q" value="<?= e($search) ?>" placeholder="Reward ID, user, email, opportunity"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><?php foreach (['pending', 'approved', 'paid', 'cancelled'] as $option): ?><option value="<?= $option ?>" <?= $status === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select><label for="currency">Currency</label><select id="currency" name="currency"><option value="">All currencies</option><?php foreach ($currencies as $option): ?><option value="<?= e($option) ?>" <?= $currency === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select><button class="btn" type="submit">Filter</button><a class="btn btn-secondary" href="/admin/rewards.php">Reset</a></form></section>
<section class="panel"><div class="table-wrap"><table><thead><tr><th>Reward</th><th>Recipient</th><th>Opportunity</th><th>Amount</th><th>Status</th><th>Created</th><th>Transition</th></tr></thead><tbody><?php if (!$rewards): ?><tr><td colspan="7">No rewards match these filters.</td></tr><?php endif; ?><?php foreach ($rewards as $reward): ?><tr><td><strong>#<?= (int) $reward['id'] ?></strong><small><a href="/admin/application-view.php?id=<?= (int) $reward['application_id'] ?>">Application #<?= (int) $reward['application_id'] ?></a></small></td><td><?= e($reward['full_name']) ?><small><?= e($reward['email']) ?></small></td><td><?= e($reward['title']) ?></td><td><?= e($reward['currency']) ?> <?= number_format((float) $reward['amount'], 2) ?></td><td><?= status_badge($reward['status']) ?></td><td><?= e(date('M j, Y', strtotime($reward['created_at']))) ?></td><td><?php if ($transitions[$reward['status']]): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $reward['id'] ?>"><input type="hidden" name="return_q" value="<?= e($search) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><input type="hidden" name="return_currency" value="<?= e($currency) ?>"><input type="hidden" name="return_page" value="<?= $page ?>"><select name="status" required aria-label="New status for reward <?= (int) $reward['id'] ?>"><option value="">Choose</option><?php foreach ($transitions[$reward['status']] as $option): ?><option value="<?= $option ?>"><?= ucfirst($option) ?></option><?php endforeach; ?></select><button class="btn btn-small" type="submit">Apply</button></form><?php else: ?><span>Final</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?= pagination_links($page, $pages, ['q' => $search, 'status' => $status, 'currency' => $currency]) ?></section>
<?php render_admin_footer(); ?>
