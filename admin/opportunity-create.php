<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/functions.php';
$admin = require_admin();
$values = ['title' => '', 'category' => '', 'short_description' => '', 'description' => '', 'reward_amount' => '', 'currency' => 'USD', 'eligibility' => '', 'requirements' => '', 'instructions' => '', 'terms' => '', 'estimated_minutes' => '', 'status' => 'draft', 'featured' => '0'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (array_keys($values) as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $values['featured'] = isset($_POST['featured']) ? '1' : '0';
    $required = ['title', 'category', 'short_description', 'description', 'reward_amount', 'currency', 'eligibility', 'requirements', 'instructions', 'terms', 'estimated_minutes'];
    foreach ($required as $field) {
        if ($values[$field] === '') {
            $error = 'Complete all required fields.';
            break;
        }
    }
    if (!$error && (strlen($values['title']) > 180 || strlen($values['category']) > 80 || strlen($values['short_description']) > 300)) $error = 'A title, category, or summary exceeds its maximum length.';
    if (!$error && (!is_numeric($values['reward_amount']) || (float) $values['reward_amount'] < 0 || (float) $values['reward_amount'] > 99999999.99)) $error = 'Enter a valid non-negative reward amount.';
    if (!$error && !preg_match('/^[A-Za-z]{3}$/', $values['currency'])) $error = 'Currency must be a three-letter code.';
    if (!$error && (!ctype_digit($values['estimated_minutes']) || (int) $values['estimated_minutes'] < 1 || (int) $values['estimated_minutes'] > 65535)) $error = 'Estimated minutes must be between 1 and 65,535.';
    if (!$error && !in_array($values['status'], ['draft', 'active', 'inactive'], true)) $error = 'Invalid status.';

    if (!$error) {
        try {
            $imagePath = save_opportunity_image($_FILES['image'] ?? []);
            $pdo = db();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO opportunities (title, category, short_description, description, reward_amount, currency, eligibility, requirements, instructions, terms, estimated_minutes, image_path, status, featured, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$values['title'], $values['category'], $values['short_description'], $values['description'], number_format((float) $values['reward_amount'], 2, '.', ''), strtoupper($values['currency']), $values['eligibility'], $values['requirements'], $values['instructions'], $values['terms'], (int) $values['estimated_minutes'], $imagePath, $values['status'], (int) $values['featured'], (int) $admin['id']]);
            $id = (int) $pdo->lastInsertId();
            admin_log('opportunity_created', 'opportunity', $id, ['status' => $values['status']]);
            $pdo->commit();
            flash('success', 'Opportunity created.');
            redirect('/admin/opportunity-edit.php?id=' . $id);
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Could not create the opportunity.';
        }
    }
}

render_admin_header('Create opportunity');
?>
<nav class="breadcrumbs" aria-label="Breadcrumb"><a href="/admin/opportunities.php">Opportunities</a><span>/</span><span>Create</span></nav>
<section class="page-heading admin-page-head"><div><p class="eyebrow">New listing</p><h1>Create opportunity</h1><p>Describe eligibility, work, and reward terms precisely.</p></div></section>
<?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="panel admin-card form-card form-grid two-column opportunity-form">
  <?= csrf_field() ?>
  <label for="title">Title</label><input id="title" name="title" maxlength="180" value="<?= e($values['title']) ?>" required>
  <label for="category">Category</label><input id="category" name="category" maxlength="80" value="<?= e($values['category']) ?>" required>
  <label for="short_description" class="full-field full">Short description</label><textarea id="short_description" name="short_description" maxlength="300" rows="3" class="full-field full" required><?= e($values['short_description']) ?></textarea>
  <label for="description" class="full-field full">Full description</label><textarea id="description" name="description" rows="6" class="full-field full" required><?= e($values['description']) ?></textarea>
  <label for="reward_amount">Reward amount</label><input id="reward_amount" name="reward_amount" type="number" min="0" max="99999999.99" step="0.01" value="<?= e($values['reward_amount']) ?>" required>
  <label for="currency">Currency</label><input id="currency" name="currency" maxlength="3" pattern="[A-Za-z]{3}" value="<?= e($values['currency']) ?>" required>
  <label for="estimated_minutes">Estimated minutes</label><input id="estimated_minutes" name="estimated_minutes" type="number" min="1" max="65535" value="<?= e($values['estimated_minutes']) ?>" required>
  <label for="status">Status</label><select id="status" name="status"><?php foreach (['draft', 'active', 'inactive'] as $option): ?><option value="<?= $option ?>" <?= $values['status'] === $option ? 'selected' : '' ?>><?= ucfirst($option) ?></option><?php endforeach; ?></select>
  <?php foreach (['eligibility' => 'Eligibility', 'requirements' => 'Requirements', 'instructions' => 'Instructions', 'terms' => 'Terms'] as $field => $label): ?><label for="<?= $field ?>" class="full-field full"><?= $label ?></label><textarea id="<?= $field ?>" name="<?= $field ?>" rows="5" class="full-field full" required><?= e($values[$field]) ?></textarea><?php endforeach; ?>
  <label for="image">Image <small>JPG, PNG, or WebP; maximum 3 MB</small></label><input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp">
  <label class="check-field"><input name="featured" type="checkbox" value="1" <?= $values['featured'] === '1' ? 'checked' : '' ?>> Feature this opportunity</label>
  <div class="button-row full-field"><button class="btn" type="submit">Create opportunity</button><a class="btn btn-secondary" href="/admin/opportunities.php">Cancel</a></div>
</form>
<?php render_admin_footer(); ?>
