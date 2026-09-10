<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Opportunity not found.'); }
$stmt = db()->prepare('SELECT * FROM opportunities WHERE id = ?');
$stmt->execute([(int) $id]);
$opportunity = $stmt->fetch();
if (!$opportunity) { http_response_code(404); exit('Opportunity not found.'); }
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [];
    foreach (['title', 'category', 'short_description', 'description', 'reward_amount', 'currency', 'eligibility', 'requirements', 'instructions', 'terms', 'estimated_minutes', 'status'] as $key) $values[$key] = trim((string) ($_POST[$key] ?? ''));
    $values['featured'] = isset($_POST['featured']) ? 1 : 0;
    foreach (['title', 'category', 'short_description', 'description', 'reward_amount', 'currency', 'eligibility', 'requirements', 'instructions', 'terms', 'estimated_minutes'] as $field) if ($values[$field] === '') { $error = 'Complete all required fields.'; break; }
    if (!$error && (strlen($values['title']) > 180 || strlen($values['category']) > 80 || strlen($values['short_description']) > 300)) $error = 'A title, category, or summary exceeds its maximum length.';
    if (!$error && (!is_numeric($values['reward_amount']) || (float) $values['reward_amount'] < 0 || (float) $values['reward_amount'] > 99999999.99)) $error = 'Enter a valid non-negative reward amount.';
    if (!$error && !preg_match('/^[A-Za-z]{3}$/', $values['currency'])) $error = 'Currency must be a three-letter code.';
    if (!$error && (!ctype_digit($values['estimated_minutes']) || (int) $values['estimated_minutes'] < 1 || (int) $values['estimated_minutes'] > 65535)) $error = 'Estimated minutes must be between 1 and 65,535.';
    if (!$error && !in_array($values['status'], ['draft', 'active', 'inactive'], true)) $error = 'Invalid status.';
    if (!$error) {
        try {
            $imagePath = save_opportunity_image($_FILES['image'] ?? [], $opportunity['image_path']);
            $pdo = db();
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE opportunities SET title=?, category=?, short_description=?, description=?, reward_amount=?, currency=?, eligibility=?, requirements=?, instructions=?, terms=?, estimated_minutes=?, image_path=?, status=?, featured=? WHERE id=?');
            $update->execute([$values['title'], $values['category'], $values['short_description'], $values['description'], number_format((float) $values['reward_amount'], 2, '.', ''), strtoupper($values['currency']), $values['eligibility'], $values['requirements'], $values['instructions'], $values['terms'], (int) $values['estimated_minutes'], $imagePath, $values['status'], $values['featured'], (int) $id]);
            admin_log('opportunity_updated', 'opportunity', (int) $id, ['status_from' => $opportunity['status'], 'status_to' => $values['status']]);
            $pdo->commit();
            flash('success', 'Opportunity updated.');
            redirect('/admin/opportunity-edit.php?id=' . (int) $id);
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not update the opportunity.';
        }
    }
    $opportunity = array_merge($opportunity, $values);
}

render_admin_header('Edit opportunity');
?>
<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/admin/opportunities.php">Opportunities</a><span>/</span><span>Edit</span></nav>
<section class="page-heading admin-page-head"><div><p class="eyebrow">Opportunity #<?= (int) $id ?></p><h1>Edit <?= e($opportunity['title']) ?></h1><p>Changes to reward terms affect future reviews; existing rewards retain their recorded amount.</p></div><?= status_badge($opportunity['status']) ?></section>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="panel admin-card form-card form-grid two-column opportunity-form">
  <?= csrf_field() ?>
  <label for="title">Title</label><input id="title" name="title" maxlength="180" value="<?= e((string) $opportunity['title']) ?>" required>
  <label for="category">Category</label><input id="category" name="category" maxlength="80" value="<?= e((string) $opportunity['category']) ?>" required>
  <label for="short_description" class="full-field">Short description</label><textarea id="short_description" name="short_description" maxlength="300" rows="3" class="full-field" required><?= e((string) $opportunity['short_description']) ?></textarea>
  <label for="description" class="full-field">Full description</label><textarea id="description" name="description" rows="6" class="full-field" required><?= e((string) $opportunity['description']) ?></textarea>
  <label for="reward_amount">Reward amount</label><input id="reward_amount" name="reward_amount" type="number" min="0" max="99999999.99" step="0.01" value="<?= e((string) $opportunity['reward_amount']) ?>" required>
  <label for="currency">Currency</label><input id="currency" name="currency" maxlength="3" pattern="[A-Za-z]{3}" value="<?= e((string) $opportunity['currency']) ?>" required>
  <label for="estimated_minutes">Estimated minutes</label><input id="estimated_minutes" name="estimated_minutes" type="number" min="1" max="65535" value="<?= (int) $opportunity['estimated_minutes'] ?>" required>
  <label for="status">Status</label><select id="status" name="status"><?php foreach (['draft', 'active', 'inactive'] as $option): ?><option value="<?= $option ?>" <?= $opportunity['status'] === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select>
  <?php foreach (['eligibility' => 'Eligibility', 'requirements' => 'Requirements', 'instructions' => 'Instructions', 'terms' => 'Terms'] as $field => $label): ?><label for="<?= $field ?>" class="full-field"><?= $label ?></label><textarea id="<?= $field ?>" name="<?= $field ?>" rows="5" class="full-field" required><?= e((string) $opportunity[$field]) ?></textarea><?php endforeach; ?>
  <?php if ($opportunity['image_path']): ?><div><span>Current image</span><img class="admin-thumbnail" src="<?= e($opportunity['image_path']) ?>" alt=""></div><?php endif; ?>
  <div><label for="image">Replace image <small>JPG, PNG, or WebP; maximum 3 MB</small></label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp"></div>
  <label class="check-field"><input name="featured" type="checkbox" value="1" <?= (int) $opportunity['featured'] === 1 ? 'checked' : '' ?>> Feature this opportunity</label>
  <div class="button-row full-field"><button class="btn" type="submit">Save changes</button><a class="btn btn-secondary" href="/admin/opportunities.php">Back to opportunities</a></div>
</form>
<?php render_admin_footer(); ?>
