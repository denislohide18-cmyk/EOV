<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$minRewardInput = trim((string) ($_GET['min_reward'] ?? ''));
$maxRewardInput = trim((string) ($_GET['max_reward'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;

$sortOptions = [
    'newest' => 'created_at DESC',
    'reward_high' => 'reward_amount DESC, created_at DESC',
    'reward_low' => 'reward_amount ASC, created_at DESC',
    'time_low' => 'estimated_minutes ASC, created_at DESC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}

$categoryStmt = db()->prepare("SELECT DISTINCT category FROM opportunities WHERE status = 'active' ORDER BY category");
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

$where = ["status = 'active'"];
$params = [];
if ($search !== '') {
    $where[] = '(title LIKE ? OR short_description LIKE ? OR description LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
if ($category !== '') {
    $where[] = 'category = ?';
    $params[] = $category;
}
if ($minRewardInput !== '' && is_numeric($minRewardInput) && (float) $minRewardInput >= 0) {
    $where[] = 'reward_amount >= ?';
    $params[] = (float) $minRewardInput;
}
if ($maxRewardInput !== '' && is_numeric($maxRewardInput) && (float) $maxRewardInput >= 0) {
    $where[] = 'reward_amount <= ?';
    $params[] = (float) $maxRewardInput;
}

$whereSql = implode(' AND ', $where);
$countStmt = db()->prepare('SELECT COUNT(*) FROM opportunities WHERE ' . $whereSql);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$listStmt = db()->prepare('SELECT id, title, category, short_description, reward_amount, currency, eligibility, estimated_minutes, image_path, status FROM opportunities WHERE ' . $whereSql . ' ORDER BY ' . $sortOptions[$sort] . ' LIMIT ? OFFSET ?');
$position = 1;
foreach ($params as $value) {
    $listStmt->bindValue($position++, $value, is_float($value) ? PDO::PARAM_STR : PDO::PARAM_STR);
}
$listStmt->bindValue($position++, $perPage, PDO::PARAM_INT);
$listStmt->bindValue($position, $offset, PDO::PARAM_INT);
$listStmt->execute();
$opportunities = $listStmt->fetchAll();

$query = array_filter([
    'search' => $search,
    'category' => $category,
    'min_reward' => $minRewardInput,
    'max_reward' => $maxRewardInput,
    'sort' => $sort,
], static fn(string $value): bool => $value !== '');

render_header('Opportunities | ' . SITE_NAME, 'Search active feedback and research opportunities by category, reward, and time.');
?>
<section class="page-hero"><div class="container"><p class="eyebrow">Browse activities</p><h1>Opportunities</h1><p>Compare requirements and potential rewards before deciding whether to apply.</p></div></section>
<section class="section"><div class="container">
  <form method="get" class="filters card">
    <label>Search<input type="search" name="search" value="<?= e($search) ?>" placeholder="Title or keyword"></label>
    <label>Category<select name="category"><option value="">All categories</option><?php foreach ($categories as $option): ?><option value="<?= e($option) ?>"<?= $category === $option ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
    <label>Minimum reward<input type="number" name="min_reward" min="0" step="0.01" value="<?= e($minRewardInput) ?>"></label>
    <label>Maximum reward<input type="number" name="max_reward" min="0" step="0.01" value="<?= e($maxRewardInput) ?>"></label>
    <label>Sort<select name="sort"><option value="newest"<?= $sort === 'newest' ? ' selected' : '' ?>>Newest</option><option value="reward_high"<?= $sort === 'reward_high' ? ' selected' : '' ?>>Reward: high to low</option><option value="reward_low"<?= $sort === 'reward_low' ? ' selected' : '' ?>>Reward: low to high</option><option value="time_low"<?= $sort === 'time_low' ? ' selected' : '' ?>>Shortest first</option></select></label>
    <div class="filter-actions"><button class="btn" type="submit">Apply filters</button><a href="/opportunities.php">Clear</a></div>
  </form>
  <div class="results-heading"><h2><?= $total ?> active opportunit<?= $total === 1 ? 'y' : 'ies' ?></h2></div>
  <?php if ($opportunities): ?><div class="card-grid"><?php foreach ($opportunities as $item): ?><article class="card opportunity-card">
    <?php if ($item['image_path']): ?><img src="<?= e($item['image_path']) ?>" alt="" loading="lazy"><?php endif; ?><div class="card-meta"><span class="eyebrow"><?= e($item['category']) ?></span><?= status_badge($item['status']) ?></div><h3><a href="/opportunity.php?id=<?= (int) $item['id'] ?>"><?= e($item['title']) ?></a></h3><p><?= e($item['short_description']) ?></p><p class="eligibility"><strong>Eligibility:</strong> <?= e($item['eligibility']) ?></p><div class="opportunity-meta"><strong><?= e($item['currency']) ?> <?= e(number_format((float) $item['reward_amount'], 2)) ?></strong><span>About <?= (int) $item['estimated_minutes'] ?> minutes</span></div><a class="btn btn-secondary" href="/opportunity.php?id=<?= (int) $item['id'] ?>">View Details</a>
  </article><?php endforeach; ?></div><?= pagination_links($page, $pages, $query) ?><?php else: ?><div class="empty-state"><h2>No matching opportunities</h2><p>Try broadening your search or clearing a filter.</p></div><?php endif; ?>
</div></section>
<?php render_footer();
