<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';

if (authenticated_user() !== null) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = LoginValidator::normalize($_POST);
    $errors = LoginValidator::validate($data);

    if (!csrf_is_valid($_POST['_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    if ($errors === []) {
        $result = $authService->attempt($data['email'], $data['password'], time());

        if ($result['ok'] && is_array($result['user'])) {
            sign_in_session($result['user']);
            redirect('/dashboard.php');
        }

        $errors['form'] = match ($result['reason']) {
            'locked' => 'Too many sign-in attempts. Please wait a few minutes.',
            'unverified' => 'Verify your email before signing in.',
            default => 'The email or password is incorrect.',
        };
    }

    flash('login_errors', $errors);
    flash('login_email', $data['email']);
    redirect('/login.php');
}

$errors = pull_flash('login_errors', []);
$oldEmail = pull_flash('login_email', '');
$notice = pull_flash('notice');
$registered = pull_flash('registered');

function login_error(array $errors, string $field): ?string {
    return isset($errors[$field]) && is_string($errors[$field]) ? $errors[$field] : null;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="VerifyFlow secure PHP account login with email-verification lifecycle.">
<meta name="color-scheme" content="light dark">
<title>VerifyFlow — Sign in</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<a class="skip-link" href="#login-form">Skip to sign in</a>
<main class="auth-shell">
<section class="story-panel">
<a class="brand" href="/login.php"><span class="brand-mark">VF</span><span>VerifyFlow</span></a>
<div class="story-copy">
<p class="eyebrow">Account activation / PHP + PDO</p>
<h1>Registration is not complete until the identity is verified.</h1>
<p>This project models a secure pending → verified → authenticated account lifecycle with one-time expiring verification tokens stored only as hashes.</p>
</div>
<div class="lifecycle">
<div><span>01</span><strong>Register</strong><small>Account starts pending.</small></div>
<div><span>02</span><strong>Verify</strong><small>One-time token activates it.</small></div>
<div><span>03</span><strong>Sign in</strong><small>Only active accounts authenticate.</small></div>
</div>
</section>

<section class="form-panel">
<div class="form-card">
<p class="section-index">Access / 03</p>
<h2>Sign in</h2>
<p class="form-intro">Only verified accounts can enter the dashboard.</p>

<?php if (is_string($registered) && $registered !== ''): ?><div class="notice notice--success" role="status"><?= e($registered) ?></div><?php endif; ?>
<?php if (is_string($notice) && $notice !== ''): ?><div class="notice" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if (($errors['form'] ?? null) !== null): ?><div class="notice notice--error" role="alert"><?= e((string)$errors['form']) ?></div><?php endif; ?>

<form id="login-form" method="post" action="/login.php" novalidate>
<input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
<div class="field">
<label for="email">Email</label>
<input id="email" name="email" type="email" autocomplete="username" maxlength="254" required value="<?= e(is_string($oldEmail)?$oldEmail:'') ?>" <?= login_error($errors,'email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
<?php if ($error=login_error($errors,'email')): ?><p id="email-error" class="field-error"><?= e($error) ?></p><?php endif; ?>
</div>
<div class="field">
<label for="password">Password</label>
<input id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" required <?= login_error($errors,'password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
<?php if ($error=login_error($errors,'password')): ?><p id="password-error" class="field-error"><?= e($error) ?></p><?php endif; ?>
</div>
<button class="primary-button" type="submit">Sign in →</button>
</form>
<p class="switch-link">Need an account? <a href="/register.php">Register</a></p>
</div>
</section>
</main>
</body>
</html>