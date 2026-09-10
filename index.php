<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$stmt = db()->prepare("SELECT id, title, category, short_description, reward_amount, currency, estimated_minutes, image_path FROM opportunities WHERE status = 'active' ORDER BY featured DESC, created_at DESC LIMIT 3");
$stmt->execute();
$featured = $stmt->fetchAll();

render_header('Earn On Venture | Discover Opportunities & Earn Rewards', 'Discover eligible feedback activities with clearly stated requirements and rewards subject to verified completion.', 'home-page');
?>
<section class="hero"><div class="container hero-grid">
  <div><p class="eyebrow">Research and feedback activities</p><h1>Your experience.<br>Your opportunity.</h1><p class="lead">Members can discover eligible feedback activities and receive rewards according to clearly stated requirements and verified completion.</p><div class="button-row"><?php if (!current_user()): ?><a class="btn" href="/register.php">Create Free Account</a><?php endif; ?><a class="btn btn-secondary" href="/opportunities.php">Explore Opportunities</a></div><p class="fine-print">No deposit required. Applying or submitting does not guarantee approval or payment.</p></div>
  <aside class="hero-card"><p class="eyebrow">What to expect</p><ul class="check-list"><li>Review eligibility before applying</li><li>Wait for application approval</li><li>Submit original work through your dashboard</li><li>Rewards appear only when actually created and verified</li></ul></aside>
</div></section>
<section class="section"><div class="container">
  <div class="section-heading"><p class="eyebrow">Opportunity marketplace</p><h2>Explore what is available</h2><p>Every listing comes from our live opportunity catalog and explains eligibility, timing, requirements, and the potential reward before you apply.</p></div>
  <?php if ($featured): ?><div class="card-grid"><?php foreach ($featured as $item): ?><article class="card opportunity-card">
    <?php if ($item['image_path']): ?><img src="<?= e($item['image_path']) ?>" alt="" loading="lazy"><?php endif; ?><p class="eyebrow"><?= e($item['category']) ?></p><h3><a href="/opportunity.php?id=<?= (int) $item['id'] ?>"><?= e($item['title']) ?></a></h3><p><?= e($item['short_description']) ?></p><div class="opportunity-meta"><strong><?= e($item['currency']) ?> <?= e(number_format((float) $item['reward_amount'], 2)) ?></strong><span>About <?= (int) $item['estimated_minutes'] ?> minutes</span></div><a class="btn btn-secondary" href="/opportunity.php?id=<?= (int) $item['id'] ?>">View Details</a>
  </article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><h3>No active opportunities right now</h3><p>Availability changes. Please check again later.</p></div><?php endif; ?>
</div></section>
<section class="section" id="how-it-works"><div class="container">
  <div class="section-heading"><p class="eyebrow">A clear process</p><h2>How it works</h2></div>
  <div class="steps"><article><span>01</span><h3>Choose carefully</h3><p>Read the activity scope, eligibility rules, and terms before applying.</p></article><article><span>02</span><h3>Complete honestly</h3><p>Approved members follow the instructions and submit original, relevant responses.</p></article><article><span>03</span><h3>Verification first</h3><p>Administrators review completion. Rewards are not guaranteed and their real status is shown on your dashboard.</p></article></div>
</div></section>
<section class="section section-dark"><div class="container trust-grid">
  <div class="section-heading"><p class="eyebrow">Trust through transparency</p><h2>Know the terms before you participate.</h2><p>Earn On Venture is designed around visible requirements and human review, not promises of guaranteed income.</p></div>
  <div class="card-grid"><article class="content-card"><h3>No pay-to-join requirement</h3><p>Creating a member account does not require a deposit.</p></article><article class="content-card"><h3>Opportunity-specific eligibility</h3><p>Availability and eligibility vary. Review every listing before applying.</p></article><article class="content-card"><h3>Verified reward records</h3><p>Rewards are recorded only after completed activity is reviewed. Payment is never automatic.</p></article></div>
</div></section>
<section class="section section-alt"><div class="container faq-grid">
  <div class="section-heading"><p class="eyebrow">Frequently asked questions</p><h2>Clear answers, upfront.</h2></div>
  <div class="faq-list"><details><summary>Does Earn On Venture guarantee income?</summary><p>No. Opportunity availability, eligibility, approval, and rewards vary. Applying does not guarantee selection or payment.</p></details><details><summary>Do I need to make a deposit?</summary><p>No deposit is required merely to create an account or browse opportunities.</p></details><details><summary>When is a reward created?</summary><p>A reward record is created only after an administrator verifies that an approved activity was completed according to its requirements.</p></details><details><summary>Can I apply to every opportunity?</summary><p>You can review every active listing, but you should apply only when you meet its stated eligibility criteria.</p></details></div>
</div></section>
<section class="section"><div class="container cta-card"><div><p class="eyebrow">Start with the details</p><h2>Find an opportunity that fits.</h2><p>Compare active listings and decide whether the requirements are right for you.</p></div><div class="button-row"><?php if (!current_user()): ?><a class="btn" href="/register.php">Create Free Account</a><?php endif; ?><a class="btn btn-secondary" href="/opportunities.php">Explore Opportunities</a></div></div>
</div></section>
<?php render_footer();
