<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$result = $verificationService->verify($token, time());
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="VerifyFlow account verification result.">
<meta name="color-scheme" content="light dark">
<title>Email verification — VerifyFlow</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="result-shell">
<section class="result-card">
<a class="brand" href="/login.php"><span class="brand-mark">VF</span><span>VerifyFlow</span></a>
<?php if ($result['ok']): ?>
<div class="result-mark result-mark--success">✓</div>
<p class="eyebrow">Verification / 02</p>
<h1>Email verified.</h1>
<p>Your account is active. The verification token has been consumed and cannot be reused.</p>
<a class="primary-link" href="/login.php">Continue to sign in →</a>
<?php else: ?>
<div class="result-mark result-mark--error">×</div>
<p class="eyebrow">Verification / failed</p>
<h1>Link invalid or expired.</h1>
<p>This token may be malformed, expired, already consumed, or no longer linked to a pending account.</p>
<a class="primary-link" href="/login.php">Return to sign in →</a>
<?php endif; ?>
</section>
</main>
</body>
</html>