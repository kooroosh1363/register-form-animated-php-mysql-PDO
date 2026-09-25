<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';

$sessionUser = require_authenticated_user();
$account = $accountRepository->findById($sessionUser['id']);

if ($account === null || $account['status'] !== 'active') {
    sign_out_session();
    header('Location: /login.php', true, 303);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="VerifyFlow protected verified-account dashboard.">
<meta name="color-scheme" content="light dark">
<title>Dashboard — VerifyFlow</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header class="dashboard-header">
<a class="brand" href="/dashboard.php"><span class="brand-mark">VF</span><span>VerifyFlow</span></a>
<form method="post" action="/logout.php"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><button class="secondary-button" type="submit">Sign out</button></form>
</header>
<main class="dashboard-shell">
<p class="eyebrow">Verified account / authenticated</p>
<h1>Welcome, <?= e($account['username']) ?>.</h1>
<p class="dashboard-lead">This dashboard is available only after registration, successful email verification, and authenticated session creation.</p>
<section class="account-grid">
<article><span>Email</span><strong><?= e($account['email']) ?></strong></article>
<article><span>Status</span><strong><?= e($account['status']) ?></strong></article>
<article><span>Verified at</span><strong><?= e(gmdate('Y-m-d H:i:s', (int)$account['email_verified_at'])) ?> UTC</strong></article>
<article><span>Database</span><strong><?= e($accountRepository->driver()) ?></strong></article>
</section>
</main>
</body>
</html>