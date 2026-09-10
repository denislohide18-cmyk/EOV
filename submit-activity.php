<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$user = require_auth();
$applicationId = filter_input(INPUT_GET, 'application_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
if (!$applicationId) {
    http_response_code(404);
    render_header('Application not found | ' . SITE_NAME);
    ?><section class="section"><div class="container narrow"><h1>Application not found</h1><a class="btn" href="/dashboard.php">Return to dashboard</a></div></section><?php render_footer(); exit;
}

$stmt = db()->prepare("SELECT a.id, a.status, o.id AS opportunity_id, o.title, o.instructions, o.requirements, o.status AS opportunity_status, s.id AS submission_id FROM applications a JOIN opportunities o ON o.id = a.opportunity_id LEFT JOIN activity_submissions s ON s.application_id = a.id WHERE a.id = ? AND a.user_id = ? LIMIT 1");
$stmt->execute([$applicationId, $user['id']]);
$application = $stmt->fetch();
if (!$application) {
    http_response_code(404);
    render_header('Application not found | ' . SITE_NAME);
    ?><section class="section"><div class="container narrow"><h1>Application not found</h1><p>This application does not belong to your account.</p><a class="btn" href="/dashboard.php">Return to dashboard</a></div></section><?php render_footer(); exit;
}

$eligible = $application['status'] === 'approved' && $application['opportunity_status'] === 'active' && !$application['submission_id'];
$error = '';
$responseText = trim((string) ($_POST['response_text'] ?? ''));
$referenceUrl = trim((string) ($_POST['reference_url'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$eligible) {
        $error = 'This application is not currently eligible for an activity submission.';
    } elseif (strlen($responseText) < 20 || strlen($responseText) > 10000) {
        $error = 'Your response must be between 20 and 10,000 characters.';
    } elseif ($referenceUrl !== '' && (!filter_var($referenceUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($referenceUrl, PHP_URL_SCHEME)), ['http', 'https'], true) || strlen($referenceUrl) > 500)) {
        $error = 'Enter a valid HTTP or HTTPS reference URL, or leave it blank.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare("SELECT a.id FROM applications a JOIN opportunities o ON o.id = a.opportunity_id LEFT JOIN activity_submissions s ON s.application_id = a.id WHERE a.id = ? AND a.user_id = ? AND a.status = 'approved' AND o.status = 'active' AND s.id IS NULL LIMIT 1 FOR UPDATE");
            $lock->execute([$applicationId, $user['id']]);
            if (!$lock->fetch()) {
                throw new RuntimeException('This application is no longer eligible for submission.');
            }
            $insert = $pdo->prepare('INSERT INTO activity_submissions (application_id, user_id, response_text, reference_url) VALUES (?, ?, ?, ?)');
            $insert->execute([$applicationId, $user['id'], $responseText, $referenceUrl !== '' ? $referenceUrl : null]);
            $pdo->commit();
            flash('success', 'Activity submitted and pending administrator review.');
            redirect('/dashboard.php');
        } catch (RuntimeException $exception) {
            $pdo->rollBack();
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            $pdo->rollBack();
            if ((string) $exception->getCode() === '23000') {
                $error = 'An activity has already been submitted for this application.';
            } else {
                throw $exception;
            }
        }
    }
}

render_header('Submit activity | ' . SITE_NAME);
?>
<section class="page-hero"><div class="container"><p class="eyebrow">Approved application</p><h1><?= e($application['title']) ?></h1><p>Submit your completed activity for administrator review.</p></div></section>
<section class="section"><div class="container detail-grid">
  <article class="prose"><h2>Instructions</h2><p><?= nl2br(e($application['instructions'])) ?></p><h2>Requirements</h2><p><?= nl2br(e($application['requirements'])) ?></p></article>
  <aside><div class="card form-card">
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($application['submission_id']): ?><h2>Submission received</h2><p>Your activity is pending review. A reward is created only if an administrator verifies completion.</p><a class="btn" href="/dashboard.php">View dashboard</a>
    <?php elseif ($application['opportunity_status'] !== 'active'): ?><h2>Opportunity closed</h2><p>This opportunity is no longer active, so it cannot accept a submission.</p><a class="btn" href="/dashboard.php">View dashboard</a>
    <?php elseif ($application['status'] !== 'approved'): ?><h2>Not eligible to submit</h2><p>Your application must be approved before you can submit activity.</p><a class="btn" href="/dashboard.php">View dashboard</a>
    <?php else: ?><form method="post">
      <?= csrf_field() ?><input type="hidden" name="application_id" value="<?= (int) $applicationId ?>">
      <label>Your response<textarea name="response_text" minlength="20" maxlength="10000" rows="10" required><?= e($responseText) ?></textarea><small>Provide complete, original work that addresses the requirements.</small></label>
      <label>Reference URL (optional)<input type="url" name="reference_url" maxlength="500" placeholder="https://" value="<?= e($referenceUrl) ?>"></label>
      <button class="btn" type="submit">Submit for review</button><p class="fine-print">After submission, your work is pending review. Submission does not guarantee a reward.</p>
    </form><?php endif; ?>
  </div></aside>
</div></section>
<?php render_footer();
