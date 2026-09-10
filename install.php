<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, max-age=0');

if (PHP_VERSION_ID >= 70300) {
    session_name('eov_installer');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
session_start();

$requirements = [
    'PHP 8.1 or newer' => PHP_VERSION_ID >= 80100,
    'PDO extension' => extension_loaded('pdo'),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    'Mbstring extension' => extension_loaded('mbstring'),
    'Fileinfo extension' => extension_loaded('fileinfo'),
    'OpenSSL extension' => extension_loaded('openssl'),
    'Writable PHP sessions' => session_status() === PHP_SESSION_ACTIVE,
    'Readable database.sql' => is_readable(__DIR__ . '/database.sql'),
    'Readable config.php' => is_readable(__DIR__ . '/config.php'),
];

$errors = [];
$success = false;
$pdo = null;
$adminExists = false;
$configReady = false;

if ($requirements['Readable config.php']) {
    require_once __DIR__ . '/config.php';
    $configured = defined('DB_HOST') && defined('DB_PORT') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS');
    $placeholderValues = ['YOUR_DATABASE_NAME', 'YOUR_DATABASE_USER', 'YOUR_DATABASE_PASSWORD', ''];
    $configReady = $configured
        && !in_array((string) DB_NAME, $placeholderValues, true)
        && !in_array((string) DB_USER, $placeholderValues, true)
        && !in_array((string) DB_PASS, $placeholderValues, true);
    $requirements['Database configuration completed'] = $configReady;
}

if (!isset($_SESSION['installer_token'])) {
    $_SESSION['installer_token'] = bin2hex(random_bytes(32));
}

/** @return list<string> */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
                $buffer .= $char;
            }
            continue;
        }
        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $blockComment = false;
                $i++;
            }
            continue;
        }
        if ($quote === null && (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#')) {
            $lineComment = true;
            if ($char === '-') {
                $i++;
            }
            continue;
        }
        if ($quote === null && $char === '/' && $next === '*') {
            $blockComment = true;
            $i++;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $buffer .= $sql[++$i];
                } else {
                    $quote = null;
                }
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }

    if ($quote !== null || $blockComment) {
        throw new RuntimeException('database.sql contains an unterminated string or comment.');
    }
    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

function usersTableExists(PDO $pdo): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $statement->execute([(string) DB_NAME, 'users']);
    return (int) $statement->fetchColumn() > 0;
}

function administratorExists(PDO $pdo): bool
{
    if (!usersTableExists($pdo)) {
        return false;
    }
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
}

if ($configReady && extension_loaded('pdo_mysql')) {
    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->query('SELECT 1');
        $requirements['Database connection'] = true;
        $adminExists = administratorExists($pdo);
    } catch (Throwable $exception) {
        $requirements['Database connection'] = false;
        $errors[] = 'Database connection failed. Verify the database name, user, password, host, and assigned user privileges in config.php.';
    }
} elseif ($configReady) {
    $requirements['Database connection'] = false;
}

$canInstall = !in_array(false, $requirements, true) && $pdo instanceof PDO && !$adminExists;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['installer_token'] ?? '';
    if (!is_string($token) || !hash_equals((string) $_SESSION['installer_token'], $token)) {
        $errors[] = 'The installer session expired. Reload this page and submit the form again.';
    } elseif ($adminExists) {
        $errors[] = 'Installation is locked because an administrator already exists.';
    } elseif (!$canInstall) {
        $errors[] = 'Resolve every failed requirement before installing.';
    } else {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Enter an administrator name between 2 and 120 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            $errors[] = 'Enter a valid administrator email address.';
        }
        if (strlen($password) < 12
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/[a-z]/', $password)
            || !preg_match('/\d/', $password)
            || !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Use at least 12 characters with uppercase, lowercase, a number, and a symbol.';
        }
        if (!hash_equals($password, $confirmation)) {
            $errors[] = 'The password confirmation does not match.';
        }

        if (!$errors) {
            try {
                if (administratorExists($pdo)) {
                    throw new RuntimeException('An administrator was created in another session. Installation has been stopped.');
                }

                $sql = file_get_contents(__DIR__ . '/database.sql');
                if ($sql === false || trim($sql) === '') {
                    throw new RuntimeException('database.sql is empty or unreadable.');
                }
                $statements = splitSqlStatements($sql);
                if (!$statements) {
                    throw new RuntimeException('database.sql does not contain executable SQL.');
                }
                foreach ($statements as $index => $statement) {
                    try {
                        $pdo->exec($statement);
                    } catch (Throwable $exception) {
                        throw new RuntimeException('Database import failed at statement ' . ($index + 1) . '. No administrator was created.', 0, $exception);
                    }
                }

                $pdo->beginTransaction();
                if (administratorExists($pdo)) {
                    throw new RuntimeException('An administrator already exists. Installation has been stopped.');
                }
                $insert = $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, 'admin', 'active')");
                $insert->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $pdo->commit();

                session_regenerate_id(true);
                unset($_SESSION['installer_token']);
                $success = true;
                $adminExists = true;
                $canInstall = false;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                try {
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                } catch (Throwable $ignored) {
                    // The original error is more useful than a cleanup error.
                }
                $errors[] = $exception->getMessage();
            }
        }
    }
}

function out(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Install Earn On Venture</title>
  <link rel="icon" href="/assets/images/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/style.css">
  <style>
    .install-shell{min-height:100vh;padding:2rem 0 4rem}.install-wrap{width:min(calc(100% - 2rem),58rem);margin:auto}.install-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:2rem}.install-grid{display:grid;gap:1rem}.install-card{padding:clamp(1.2rem,4vw,2rem);border:1px solid var(--line);border-radius:var(--radius-lg);background:var(--surface);box-shadow:var(--shadow-sm)}.install-card h1{font-size:clamp(2rem,7vw,3.6rem)}.check-list{display:grid;gap:.55rem;margin:0;padding:0;list-style:none}.check-list li{display:flex;justify-content:space-between;gap:1rem;padding:.7rem 0;border-bottom:1px solid var(--line)}.check-list li:last-child{border:0}.check-pass{color:var(--success);font-weight:800}.check-fail{color:var(--danger);font-weight:800}.danger-panel{border:2px solid var(--danger);background:var(--danger-soft)}.success-panel{border:2px solid var(--success);background:var(--success-soft)}code{padding:.15rem .35rem;border-radius:.3rem;background:rgba(32,35,31,.09)}@media(min-width:48rem){.install-grid{grid-template-columns:minmax(18rem,.75fr) minmax(0,1.25fr)}}
  </style>
</head>
<body>
<main class="install-shell">
  <div class="install-wrap">
    <header class="install-head">
      <a class="logo" href="/"><span>EV</span> Earn On Venture</a>
      <span class="badge badge-info">Secure installer</span>
    </header>

    <?php if ($success): ?>
      <section class="install-card success-panel">
        <span class="eyebrow">Installation complete</span>
        <h1>Your platform is ready.</h1>
        <p>The database was imported and the first administrator was created. Sign in through the admin area using the credentials you just provided.</p>
        <div class="alert alert-error"><strong>Required:</strong>&nbsp; Delete <code>install.php</code> from the server now. Leaving an installer online is a security risk.</div>
        <a class="btn btn-dark" href="/admin/login.php">Open admin sign in</a>
      </section>
    <?php elseif ($adminExists): ?>
      <section class="install-card danger-panel">
        <span class="eyebrow">Installer locked</span>
        <h1>An administrator already exists.</h1>
        <p>Earn On Venture is already installed. This installer will not import SQL or create another account.</p>
        <div class="alert alert-error"><strong>Delete <code>install.php</code> from your server immediately.</strong></div>
        <a class="btn btn-dark" href="/admin/login.php">Go to admin sign in</a>
      </section>
    <?php else: ?>
      <?php foreach ($errors as $error): ?>
        <div class="alert alert-error" role="alert"><?= out($error) ?></div>
      <?php endforeach; ?>
      <div class="install-grid">
        <section class="install-card">
          <span class="eyebrow">System check</span>
          <h2>Requirements</h2>
          <ul class="check-list">
            <?php foreach ($requirements as $label => $passed): ?>
              <li><span><?= out($label) ?></span><span class="<?= $passed ? 'check-pass' : 'check-fail' ?>"><?= $passed ? 'Ready' : 'Fix' ?></span></li>
            <?php endforeach; ?>
          </ul>
          <?php if (!$configReady): ?>
            <div class="alert alert-warning">Update the database values in <code>config.php</code>, then reload this page.</div>
          <?php endif; ?>
        </section>

        <section class="install-card">
          <span class="eyebrow">First administrator</span>
          <h1>Finish setup.</h1>
          <p>No default credentials are created. Use a unique password that you do not use anywhere else.</p>
          <form method="post" autocomplete="off">
            <input type="hidden" name="installer_token" value="<?= out((string) $_SESSION['installer_token']) ?>">
            <div class="form-grid">
              <div class="field">
                <label for="full_name">Full name</label>
                <input id="full_name" name="full_name" type="text" maxlength="120" required autocomplete="name" value="<?= out((string) ($_POST['full_name'] ?? '')) ?>" <?= !$canInstall ? 'disabled' : '' ?>>
              </div>
              <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= out((string) ($_POST['email'] ?? '')) ?>" <?= !$canInstall ? 'disabled' : '' ?>>
              </div>
              <div class="field">
                <label for="password">Strong password</label>
                <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password" <?= !$canInstall ? 'disabled' : '' ?>>
                <p class="form-hint">At least 12 characters including uppercase, lowercase, a number, and a symbol.</p>
              </div>
              <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" required autocomplete="new-password" <?= !$canInstall ? 'disabled' : '' ?>>
              </div>
              <button class="btn btn-dark btn-large" type="submit" data-loading-text="Installing..." <?= !$canInstall ? 'disabled' : '' ?>>Import database and create admin</button>
            </div>
          </form>
        </section>
      </div>
    <?php endif; ?>
  </div>
</main>
<script src="/assets/js/main.js"></script>
</body>
</html>
