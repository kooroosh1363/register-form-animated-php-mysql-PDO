<?php

declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RegistrationValidator.php';
require_once __DIR__ . '/LoginValidator.php';
require_once __DIR__ . '/AccountRepository.php';
require_once __DIR__ . '/RegistrationService.php';
require_once __DIR__ . '/VerificationService.php';
require_once __DIR__ . '/AuthService.php';
require_once __DIR__ . '/helpers.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    refresh_authenticated_session(time());
}

$pdo = Database::connect();
$accountRepository = new AccountRepository($pdo);
$accountRepository->migrate();
$registrationService = new RegistrationService($accountRepository);
$verificationService = new VerificationService($accountRepository);
$authService = new AuthService($accountRepository);
