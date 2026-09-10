<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('eov_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Your session token has expired. Return to the previous page and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, full_name, email, role, status, auth_version, profile_bio, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    if (!$user || $user['status'] !== 'active' || !hash_equals((string) $user['auth_version'], (string) ($_SESSION['auth_version'] ?? ''))) {
        unset($_SESSION['user_id']);
        unset($_SESSION['auth_version']);
        $user = null;
    }
    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please sign in to continue.');
        redirect('/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        exit('Administrator access required.');
    }
    return $user;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
}

function login_is_throttled(string $email): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND successful = 0');
    $stmt->execute([strtolower($email), client_ip()]);
    return (int) $stmt->fetchColumn() >= 5;
}

function record_login_attempt(string $email, bool $successful): void
{
    $stmt = db()->prepare('INSERT INTO login_attempts (email, ip_address, successful) VALUES (?, ?, ?)');
    $stmt->execute([strtolower($email), client_ip(), $successful ? 1 : 0]);
    if ($successful) {
        $cleanup = db()->prepare('DELETE FROM login_attempts WHERE email = ? OR attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
        $cleanup->execute([strtolower($email)]);
    }
}

function login_user(int $userId): void
{
    $stmt = db()->prepare('SELECT auth_version FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $authVersion = $stmt->fetchColumn();
    if ($authVersion === false) {
        throw new RuntimeException('Unable to start a session for this account.');
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['auth_version'] = (int) $authVersion;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function admin_log(string $action, string $entityType, ?int $entityId = null, ?array $details = null): void
{
    $user = require_admin();
    $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$user['id'], $action, $entityType, $entityId, $details ? json_encode($details, JSON_THROW_ON_ERROR) : null, client_ip()]);
}

function record_application_status(int $applicationId, ?string $oldStatus, string $newStatus, int $changedBy): void
{
    $stmt = db()->prepare('INSERT INTO application_status_history (application_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)');
    $stmt->execute([$applicationId, $oldStatus, $newStatus, $changedBy]);
}

function validate_password(string $password): ?string
{
    if (strlen($password) < 10) {
        return 'Password must contain at least 10 characters.';
    }
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must include uppercase, lowercase, and numeric characters.';
    }
    return null;
}

function save_opportunity_image(array $file, ?string $existing = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Image upload failed or exceeded the 3 MB limit.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Upload a JPG, PNG, or WebP image.');
    }
    if (!is_dir(UPLOAD_PATH) && !mkdir(UPLOAD_PATH, 0755, true) && !is_dir(UPLOAD_PATH)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $name)) {
        throw new RuntimeException('Could not store the uploaded image.');
    }
    if ($existing && str_starts_with($existing, '/uploads/')) {
        $old = UPLOAD_PATH . '/' . basename($existing);
        if (is_file($old)) {
            unlink($old);
        }
    }
    return '/uploads/' . $name;
}

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function pagination_links(int $page, int $pages, array $query = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $query['page'] = $i;
        $html .= '<a class="' . ($i === $page ? 'active' : '') . '" href="?' . e(http_build_query($query)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

function render_header(string $title, string $description = '', string $bodyClass = ''): void
{
    $user = current_user();
    $flashes = take_flashes();
    $canonical = SITE_URL . ($_SERVER['SCRIPT_NAME'] ?? '/');
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description ?: 'Discover transparent feedback activities and opportunity-specific rewards with Earn On Venture.') ?>">
  <meta property="og:title" content="<?= e($title) ?>">
  <meta property="og:description" content="<?= e($description ?: 'Discover eligible opportunities with clear requirements and transparent rewards.') ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= e($canonical) ?>">
  <link rel="canonical" href="<?= e($canonical) ?>">
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-header">
  <div class="container nav-wrap">
    <a class="logo" href="/"><span>EV</span> Earn On Venture</a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav"><span></span><span></span><span></span><span class="sr-only">Menu</span></button>
    <nav class="site-nav" id="site-nav" aria-label="Primary">
      <a href="/opportunities.php">Opportunities</a>
      <a href="/#how-it-works">How it works</a>
      <a href="/contact.php">Contact</a>
      <?php if ($user): ?><a href="/dashboard.php">Dashboard</a><a class="btn btn-small" href="/logout.php">Logout</a>
      <?php else: ?><a href="/login.php">Log in</a><a class="btn btn-small" href="/register.php">Create account</a><?php endif; ?>
    </nav>
  </div>
</header>
<?php foreach ($flashes as $flash): ?><div class="toast toast-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endforeach; ?>
<main>
<?php
}

function render_footer(): void
{
    ?>
</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div><a class="logo logo-light" href="/"><span>EV</span> Earn On Venture</a><p>Transparent opportunities. Clear requirements. Rewards based on verified eligibility and completion.</p></div>
    <div><h2>Platform</h2><a href="/opportunities.php">Opportunities</a><a href="/dashboard.php">Member dashboard</a><a href="/contact.php">Contact</a></div>
    <div><h2>Legal</h2><a href="/privacy.php">Privacy</a><a href="/terms.php">Terms</a><p>No guaranteed income. Opportunity availability varies.</p></div>
  </div>
  <div class="container footer-bottom">&copy; <?= date('Y') ?> Earn On Venture. No deposit is required to create an account.</div>
</footer>
<script src="/assets/js/main.js"></script>
</body>
</html><?php
}

function render_admin_header(string $title): void
{
    $admin = require_admin();
    $flashes = take_flashes();
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title) ?> | EOV Admin</title><link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/style.css"><link rel="stylesheet" href="/assets/css/admin.css"></head><body class="admin-body">
<aside class="admin-sidebar"><a class="logo logo-light" href="/admin/"><span>EV</span> EOV Admin</a><nav><a href="/admin/">Overview</a><a href="/admin/users.php">Users</a><a href="/admin/opportunities.php">Opportunities</a><a href="/admin/applications.php">Applications</a><a href="/admin/rewards.php">Rewards</a><a href="/admin/settings.php">Settings</a><a href="/dashboard.php">Member view</a><a href="/admin/logout.php">Logout</a></nav></aside>
<div class="admin-main"><header class="admin-topbar"><button class="admin-menu" type="button" aria-label="Toggle admin navigation">Menu</button><div><strong><?= e($title) ?></strong><span><?= e($admin['full_name']) ?></span></div></header>
<?php foreach ($flashes as $flash): ?><div class="toast toast-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endforeach; ?><main class="admin-content"><?php
}

function render_admin_footer(): void
{
    ?></main></div><script src="/assets/js/main.js"></script><script src="/assets/js/admin.js"></script></body></html><?php
}
