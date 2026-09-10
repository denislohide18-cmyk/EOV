<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(404);
    render_header('Opportunity not found | ' . SITE_NAME);
    ?><section class="section"><div class="container narrow"><h1>Opportunity not found</h1><p>The requested opportunity is unavailable.</p><a class="btn" href="/opportunities.php">Browse opportunities</a></div></section><?php
    render_footer();
    exit;
}

$stmt = db()->prepare("SELECT * FROM opportunities WHERE id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$id]);
$opportunity = $stmt->fetch();
if (!$opportunity) {
    http_response_code(404);
    render_header('Opportunity not found | ' . SITE_NAME);
    ?><section class="section"><div class="container narrow"><h1>Opportunity not found</h1><p>This opportunity does not exist or is no longer active.</p><a class="btn" href="/opportunities.php">Browse active opportunities</a></div></section><?php
    render_footer();
    exit;
}

$user = current_user();
$application = null;
if ($user) {
    $applicationStmt = db()->prepare('SELECT id, status FROM applications WHERE user_id = ? AND opportunity_id = ? LIMIT 1');
    $applicationStmt->execute([$user['id'], $id]);
    $application = $applicationStmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_auth();
    verify_csrf();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $activeStmt = $pdo->prepare("SELECT id FROM opportunities WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE");
        $activeStmt->execute([$id]);
        if (!$activeStmt->fetch()) {
            throw new RuntimeException('This opportunity is no longer active.');
        }
        $duplicate = $pdo->prepare('SELECT id FROM applications WHERE user_id = ? AND opportunity_id = ? LIMIT 1');
        $duplicate->execute([$user['id'], $id]);
        if ($duplicate->fetch()) {
            throw new RuntimeException('You have already applied for this opportunity.');
        }
        $insert = $pdo->prepare("INSERT INTO applications (user_id, opportunity_id, status) VALUES (?, ?, 'pending')");
        $insert->execute([$user['id'], $id]);
        $applicationId = (int) $pdo->lastInsertId();
        record_application_status($applicationId, null, 'pending', (int) $user['id']);
        $pdo->commit();
        flash('success', 'Application submitted. Its review status is available on your dashboard.');
        redirect('/opportunity.php?id=' . $id);
    } catch (RuntimeException $exception) {
        $pdo->rollBack();
        flash('error', $exception->getMessage());
        redirect('/opportunity.php?id=' . $id);
    } catch (PDOException $exception) {
        $pdo->rollBack();
        if ((string) $exception->getCode() === '23000') {
            flash('error', 'You have already applied for this opportunity.');
            redirect('/opportunity.php?id=' . $id);
        }
        throw $exception;
    }
}

render_header($opportunity['title'] . ' | ' . SITE_NAME, $opportunity['short_description']);
?>
<section class="page-hero"><div class="container"><p class="eyebrow"><?= e($opportunity['category']) ?></p><h1><?= e($opportunity['title']) ?></h1><p><?= e($opportunity['short_description']) ?></p></div></section>
<section class="section"><div class="container detail-grid">
  <article class="prose">
    <?php if ($opportunity['image_path']): ?><img class="detail-image" src="<?= e($opportunity['image_path']) ?>" alt="" loading="lazy"><?php endif; ?>
    <h2>About this opportunity</h2><p><?= nl2br(e($opportunity['description'])) ?></p>
    <h2>Eligibility</h2><p><?= nl2br(e($opportunity['eligibility'])) ?></p>
    <h2>Requirements</h2><p><?= nl2br(e($opportunity['requirements'])) ?></p>
    <h2>Instructions</h2><p><?= nl2br(e($opportunity['instructions'])) ?></p>
    <h2>Terms for this activity</h2><p><?= nl2br(e($opportunity['terms'])) ?></p>
  </article>
  <aside><div class="card sticky-card"><p class="eyebrow">Opportunity summary</p><dl><dt>Potential reward</dt><dd><?= e($opportunity['currency']) ?> <?= e(number_format((float) $opportunity['reward_amount'], 2)) ?></dd><dt>Estimated time</dt><dd>About <?= (int) $opportunity['estimated_minutes'] ?> minutes</dd><dt>Availability</dt><dd>Active now; may close at any time</dd></dl>
    <?php if ($application): ?><p>Your application: <?= status_badge($application['status']) ?></p><?php if ($application['status'] === 'approved'): ?><a class="btn" href="/submit-activity.php?application_id=<?= (int) $application['id'] ?>">Submit activity</a><?php else: ?><a class="btn btn-secondary" href="/dashboard.php">View dashboard</a><?php endif; ?>
    <?php elseif ($user): ?><form method="post"><?= csrf_field() ?><button class="btn" type="submit">Apply for this opportunity</button></form>
    <?php else: ?><a class="btn" href="/login.php">Log in to apply</a><p><small>Applications are reviewed for eligibility before activity submission.</small></p><?php endif; ?>
    <p class="fine-print">A listed reward is not a balance or guarantee. Approval and payment require verified eligibility and completion.</p>
  </div></aside>
</div></section>
<?php render_footer();
