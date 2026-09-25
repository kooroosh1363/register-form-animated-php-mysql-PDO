<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command can only run from the CLI.\n");
    exit(1);
}

$email = trim((string) ($argv[1] ?? ''));
$baseUrl = rtrim((string) ($argv[2] ?? 'http://localhost:8000'), '/');

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Usage: php bin/issue-verification.php user@example.com [base-url]\n");
    exit(1);
}

$result = $verificationService->issueForEmail($email, time());

if (!$result['ok'] || !is_string($result['token'])) {
    $messages = [
        'not_found' => 'No account exists for that email.',
        'already_verified' => 'That account is already verified.',
    ];
    fwrite(STDERR, ($messages[$result['reason']] ?? 'Unable to issue verification token.') . "\n");
    exit(1);
}

$url = $baseUrl . '/verify.php?token=' . rawurlencode($result['token']);

fwrite(STDOUT, "Verification link:\n{$url}\n");
fwrite(STDOUT, "Expires in " . Config::verificationTtlSeconds() . " seconds.\n");
