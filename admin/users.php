<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $field = (string) ($_POST['field'] ?? '');
    $value = (string) ($_POST['value'] ?? '');
    $allowed = ['status' => ['active', 'inactive'], 'role' => ['member', 'admin']];

    if (!$userId || !isset($allowed[$field]) || !in_array($value, $allowed[$field], true)) {
        flash('error', 'Invalid user update.');
        redirect('/admin/users.php');
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([(int) $userId]);
        $target = $stmt->fetch();
        if (!$target) {
            throw new RuntimeException('User not found.');
        }
        if ((int) $target['id'] === (int) $admin['id'] && (($field === 'status' && $value === 'inactive') || ($field === 'role' && $value === 'member'))) {
            throw new RuntimeException('You cannot deactivate or demote your own account.');
        }
        if ($target['role'] === 'admin' && $target['status'] === 'active' && (($field === 'status' && $value === 'inactive') || ($field === 'role' && $value === 'member'))) {
            $activeAdmins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' FOR UPDATE")->fetchAll();
            if (count($activeAdmins) <= 1) {
                throw new RuntimeException('At least one active administrator is required.');
            }
        }
        $oldValue = $target[$field];
        if ($oldValue !== $value) {
            $update = $pdo->prepare("UPDATE users SET {$field} = ? WHERE id = ?");
            $update->execute([$value, (int) $userId]);
            admin_log('user_' . $field . '_changed', 'user', (int) $userId, ['from' => $oldValue, 'to' => $value]);
        }
        $pdo->commit();
        flash('success', 'User account updated.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not update the user account.');
    }
    $returnQuery = http_build_query(array_filter([
        'q' => trim((string) ($_POST['return_q'] ?? '')),
        'status' => (string) ($_POST['return_status'] ?? ''),
        'role' => (string) ($_POST['return_role'] ?? ''),
        'page' => max(1, (int) ($_POST['return_page'] ?? 1)),
    ], static fn($value): bool => $value !== ''));
    redirect('/admin/users.php' . ($returnQuery ? '?' . $returnQuery : ''));
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? (string) $_GET['status'] : '';
$role = in_array($_GET['role'] ?? '', ['member', 'admin'], true) ? (string) $_GET['role'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($status !== '') {
    $where[] = 'u.status = ?';
    $params[] = $status;
}
if ($role !== '') {
    $where[] = 'u.role = ?';
    $params[] = $role;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$count = db()->prepare('SELECT COUNT(*) FROM users u' . $whereSql);
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$query = db()->prepare('SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, COUNT(a.id) AS application_count
    FROM users u LEFT JOIN applications a ON a.user_id = u.id' . $whereSql . '
    GROUP BY u.id ORDER BY u.created_at DESC LIMIT ? OFFSET ?');
$position = 1;
foreach ($params as $param) {
    $query->bindValue($position++, $param, PDO::PARAM_STR);
}
$query->bindValue($position++, $perPage, PDO::PARAM_INT);
$query->bindValue($position, ($page - 1) * $perPage, PDO::PARAM_INT);
$query->execute();
$users = $query->fetchAll();

render_admin_header('Users');
?>
<section class="page-heading admin-page-head"><div><p class="eyebrow">Account administration</p><h1>Users</h1><p><?= number_format($total) ?> account<?= $total === 1 ? '' : 's' ?> match the current filters.</p></div></section>
<section class="filter-panel">
  <form method="get" class="filter-form filter-bar">
    <label for="q">Search</label><input id="q" name="q" value="<?= e($search) ?>" placeholder="Name or email">
    <label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
    <label for="role">Role</label><select id="role" name="role"><option value="">All roles</option><option value="member" <?= $role === 'member' ? 'selected' : '' ?>>Member</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option></select>
    <button class="btn" type="submit">Filter</button><a class="btn btn-secondary" href="/admin/users.php">Reset</a>
  </form>
</section>
<section class="panel admin-card">
  <div class="table-wrap"><table><thead><tr><th>User</th><th>Role</th><th>Status</th><th>Applications</th><th>Joined</th><th>Controls</th></tr></thead><tbody>
  <?php if (!$users): ?><tr><td colspan="6">No users match these filters.</td></tr><?php endif; ?>
  <?php foreach ($users as $user): ?><tr>
    <td><a href="/admin/user-view.php?id=<?= (int) $user['id'] ?>"><strong><?= e($user['full_name']) ?></strong></a><small><?= e($user['email']) ?></small></td>
    <td><?= status_badge($user['role']) ?></td><td><?= status_badge($user['status']) ?></td><td><?= number_format((int) $user['application_count']) ?></td><td><?= e(date('M j, Y', strtotime($user['created_at']))) ?></td>
    <td><div class="table-actions">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="field" value="status"><input type="hidden" name="value" value="<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>"><input type="hidden" name="return_q" value="<?= e($search) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><input type="hidden" name="return_role" value="<?= e($role) ?>"><input type="hidden" name="return_page" value="<?= $page ?>"><button class="link-button" type="submit"><?= $user['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>"><input type="hidden" name="field" value="role"><input type="hidden" name="value" value="<?= $user['role'] === 'admin' ? 'member' : 'admin' ?>"><input type="hidden" name="return_q" value="<?= e($search) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>"><input type="hidden" name="return_role" value="<?= e($role) ?>"><input type="hidden" name="return_page" value="<?= $page ?>"><button class="link-button" type="submit"><?= $user['role'] === 'admin' ? 'Make member' : 'Make admin' ?></button></form>
    </div></td>
  </tr><?php endforeach; ?>
  </tbody></table></div>
  <?= pagination_links($page, $pages, ['q' => $search, 'status' => $status, 'role' => $role]) ?>
</section>
<?php render_admin_footer(); ?>
