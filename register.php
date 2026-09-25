<?php
declare(strict_types=1);
require_once __DIR__ . '/src/bootstrap.php';

if (authenticated_user() !== null) {
    redirect('/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = RegistrationValidator::normalize($_POST);
    $errors = RegistrationValidator::validate($data);

    if (!csrf_is_valid($_POST['_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    if ($errors === []) {
        $result = $registrationService->register($data, time());

        if ($result['ok'] && is_array($result['user'])) {
            flash(
                'registered',
                'Account created in pending state. Issue a verification link from the CLI, verify the email, then sign in.'
            );
            redirect('/login.php');
        }

        $errors['form'] = $result['error'] ?? 'Unable to create the account.';
    }

    flash('register_errors', $errors);
    flash('register_old', ['username'=>$data['username'],'email'=>$data['email']]);
    redirect('/register.php');
}

$errors = pull_flash('register_errors', []);
$old = pull_flash('register_old', []);

function reg_value(array $old, string $field): string {
    return isset($old[$field]) && is_string($old[$field]) ? $old[$field] : '';
}
function reg_error(array $errors, string $field): ?string {
    return isset($errors[$field]) && is_string($errors[$field]) ? $errors[$field] : null;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Create a pending VerifyFlow account that must be email-verified before login.">
<meta name="color-scheme" content="light dark">
<title>VerifyFlow — Register</title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<a class="skip-link" href="#register-form">Skip to registration</a>
<main class="register-shell">
<section class="register-copy">
<a class="brand" href="/login.php"><span class="brand-mark">VF</span><span>VerifyFlow</span></a>
<p class="eyebrow">Registration / 01</p>
<h1>Create first. Activate second.</h1>
<p>New accounts are stored as <strong>pending</strong>. Passwords are hashed immediately, and login remains blocked until a valid one-time verification token activates the account.</p>
<div class="verification-note"><strong>Demo delivery model</strong><p>Instead of pretending to send email, the repository exposes a CLI command that generates the verification link locally.</p></div>
</section>

<section class="register-card">
<p class="section-index">Pending account / 01</p>
<h2>Register</h2>
<p class="form-intro">Passwords must be at least 12 characters.</p>
<?php if (($errors['form'] ?? null) !== null): ?><div class="notice notice--error" role="alert"><?= e((string)$errors['form']) ?></div><?php endif; ?>

<form id="register-form" method="post" action="/register.php" novalidate>
<input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
<div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="username" maxlength="32" required value="<?= e(reg_value($old,'username')) ?>" <?= reg_error($errors,'username') ? 'aria-invalid="true" aria-describedby="username-error"' : '' ?>><?php if($e=reg_error($errors,'username')):?><p id="username-error" class="field-error"><?=e($e)?></p><?php endif;?></div>
<div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?= e(reg_value($old,'email')) ?>" <?= reg_error($errors,'email') ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>><?php if($e=reg_error($errors,'email')):?><p id="email-error" class="field-error"><?=e($e)?></p><?php endif;?></div>
<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required <?= reg_error($errors,'password') ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>><?php if($e=reg_error($errors,'password')):?><p id="password-error" class="field-error"><?=e($e)?></p><?php endif;?></div>
<div class="field"><label for="password_confirm">Confirm password</label><input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="12" maxlength="128" required <?= reg_error($errors,'password_confirm') ? 'aria-invalid="true" aria-describedby="password-confirm-error"' : '' ?>><?php if($e=reg_error($errors,'password_confirm')):?><p id="password-confirm-error" class="field-error"><?=e($e)?></p><?php endif;?></div>
<button class="primary-button" type="submit">Create pending account →</button>
</form>
<p class="switch-link">Already verified? <a href="/login.php">Sign in</a></p>
</section>
</main>
</body>
</html>