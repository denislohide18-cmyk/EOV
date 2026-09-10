<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

render_header('Privacy policy | ' . SITE_NAME, 'How Earn On Venture collects, uses, stores, and shares personal information.');
?>
<section class="page-hero"><div class="container narrow"><p class="eyebrow">Last updated September 10, 2026</p><h1>Privacy policy</h1><p>This policy explains the data used to operate Earn On Venture. It does not claim practices the platform cannot provide.</p></div></section>
<section class="section"><div class="container narrow prose">
  <h2>Information we collect</h2><p>We collect the name, email address, password hash, optional profile bio, and account timestamps you provide. We also store opportunity applications, activity responses, optional reference URLs, reward records, contact messages, login-attempt metadata, IP addresses used for security logging, and password-reset records.</p>
  <h2>How we use information</h2><p>We use this information to authenticate members, assess applications and submissions, administer rewards, prevent duplicate or abusive activity, respond to support requests, maintain platform security, and comply with legal obligations.</p>
  <h2>Passwords and reset links</h2><p>Passwords are stored as one-way hashes rather than readable text. Password-reset tokens are random, stored only as hashes, expire after one hour, and can be used once. In local development, a reset link may be displayed because email delivery is not configured; production users should receive links through a configured delivery service.</p>
  <h2>Sharing and processors</h2><p>We do not sell personal information. Information may be available to authorized administrators and infrastructure or service providers that operate the application. We may disclose information when legally required or necessary to protect users, the platform, or others.</p>
  <h2>Retention</h2><p>Records are retained while needed to operate accounts, document application and reward decisions, resolve disputes, prevent fraud, and meet legal requirements. Retention periods may differ by record type. You may request deletion, but some records may be retained where law or legitimate operational needs require it.</p>
  <h2>Your choices</h2><p>You can update your profile and email from the profile page. You may contact us to request access, correction, or deletion of personal information. Rights vary by jurisdiction, and we may need to verify your identity before acting.</p>
  <h2>Cookies and security</h2><p>The application uses a session cookie required for authentication, security, CSRF protection, and flash messages. It is configured as HTTP-only and SameSite=Lax, and as Secure when HTTPS is detected. No system can promise absolute security.</p>
  <h2>International use and children</h2><p>Data may be processed where the platform or its providers operate. The service is intended for adults and is not directed to children.</p>
  <h2>Changes and contact</h2><p>We may update this policy and will revise the date above. Questions or privacy requests can be sent through the <a href="/contact.php">contact page</a>.</p>
</div></section>
<?php render_footer();
