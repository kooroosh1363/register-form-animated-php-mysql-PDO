<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Config.php';
require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/RegistrationValidator.php';
require_once dirname(__DIR__) . '/src/LoginValidator.php';
require_once dirname(__DIR__) . '/src/AccountRepository.php';
require_once dirname(__DIR__) . '/src/RegistrationService.php';
require_once dirname(__DIR__) . '/src/VerificationService.php';
require_once dirname(__DIR__) . '/src/AuthService.php';

$tests = [];

function test(string $name, callable $callback): void { global $tests; $tests[] = [$name, $callback]; }
function expect_true(bool $condition, string $message='Expected true.'): void { if (!$condition) throw new RuntimeException($message); }
function expect_same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) throw new RuntimeException(sprintf('Expected %s, got %s.', var_export($expected,true), var_export($actual,true)));
}

function repo_for(string $dsn, ?string $user=null, ?string $password=null): AccountRepository {
    $pdo = Database::connect($dsn, $user, $password);
    $repo = new AccountRepository($pdo);
    $repo->migrate();
    return $repo;
}

function exercise_verified_lifecycle(AccountRepository $repo): void {
    $registration = new RegistrationService($repo);
    $verification = new VerificationService($repo);
    $auth = new AuthService($repo);

    $email = 'user+' . bin2hex(random_bytes(3)) . '@example.com';
    $password = 'correct-horse-battery-staple';

    $payload = RegistrationValidator::normalize([
        'username' => 'user_' . bin2hex(random_bytes(2)),
        'email' => $email,
        'password' => $password,
        'password_confirm' => $password,
    ]);

    $created = $registration->register($payload, 1000);
    expect_same(true, $created['ok']);
    expect_same('pending', $created['user']['status']);

    $stored = $repo->findByEmail($email);
    expect_true(is_array($stored));
    expect_true($stored['password_hash'] !== $password);
    expect_true(password_verify($password, $stored['password_hash']));

    $before = $auth->attempt($email, $password, 1001);
    expect_same(false, $before['ok']);
    expect_same('unverified', $before['reason']);

    $issued = $verification->issueForEmail($email, 1002);
    expect_same(true, $issued['ok']);
    expect_true(is_string($issued['token']));
    expect_same(1002 + Config::verificationTtlSeconds(), $issued['expires_at']);

    $verified = $verification->verify($issued['token'], 1003);
    expect_same(true, $verified['ok']);

    $after = $auth->attempt($email, $password, 1004);
    expect_same(true, $after['ok']);
    expect_same('active', $after['user']['status']);

    $reused = $verification->verify($issued['token'], 1005);
    expect_same(false, $reused['ok']);
});

test('registration validation rejects weak input', function (): void {
    $errors = RegistrationValidator::validate(RegistrationValidator::normalize([
        'username' => 'x',
        'email' => 'bad',
        'password' => 'short',
        'password_confirm' => 'different',
    ]));
    foreach (['username','email','password','password_confirm'] as $field) expect_true(isset($errors[$field]));
});

test('verified account lifecycle works on SQLite', function (): void {
    expect_true(in_array('sqlite', PDO::getAvailableDrivers(), true));
    exercise_verified_lifecycle(repo_for('sqlite::memory:'));
});

test('expired verification tokens are rejected', function (): void {
    $repo = repo_for('sqlite::memory:');
    $registration = new RegistrationService($repo);
    $verification = new VerificationService($repo);

    $email = 'expired@example.com';
    $password = 'correct-horse-battery-staple';

    $registration->register([
        'username' => 'expireduser',
        'email' => $email,
        'password' => $password,
        'password_confirm' => $password,
    ], 2000);

    $issued = $verification->issueForEmail($email, 2000);
    expect_true(is_string($issued['token']));

    $expiredAt = 2000 + Config::verificationTtlSeconds() + 1;
    $result = $verification->verify($issued['token'], $expiredAt);
    expect_same(false, $result['ok']);

    $user = $repo->findByEmail($email);
    expect_same('pending', $user['status']);
});

test('issuing a new token invalidates the previous token', function (): void {
    $repo = repo_for('sqlite::memory:');
    $registration = new RegistrationService($repo);
    $verification = new VerificationService($repo);

    $email = 'rotate@example.com';
    $password = 'correct-horse-battery-staple';

    $registration->register([
        'username' => 'rotateuser',
        'email' => $email,
        'password' => $password,
        'password_confirm' => $password,
    ], 3000);

    $first = $verification->issueForEmail($email, 3001);
    $second = $verification->issueForEmail($email, 3002);

    expect_true($first['token'] !== $second['token']);
    expect_same(false, $verification->verify($first['token'], 3003)['ok']);
    expect_same(true, $verification->verify($second['token'], 3004)['ok']);
});

test('login throttling still applies to invalid credentials', function (): void {
    $repo = repo_for('sqlite::memory:');
    $auth = new AuthService($repo);

    for ($i=1; $i<=5; $i++) {
        $result = $auth->attempt('missing@example.com', 'wrong', 4000 + $i);
    }

    expect_same('locked', $result['reason']);
});

$mysqlDsn = getenv('TEST_MYSQL_DSN');
if (is_string($mysqlDsn) && $mysqlDsn !== '') {
    test('verified lifecycle works on MySQL', function () use ($mysqlDsn): void {
        expect_true(in_array('mysql', PDO::getAvailableDrivers(), true));
        exercise_verified_lifecycle(repo_for(
            $mysqlDsn,
            getenv('TEST_MYSQL_USER') ?: null,
            getenv('TEST_MYSQL_PASSWORD') ?: null,
        ));
    });
}

$failures = 0;
foreach ($tests as [$name,$callback]) {
    try { $callback(); fwrite(STDOUT, "[pass] {$name}\n"); }
    catch (Throwable $error) { $failures++; fwrite(STDERR, "[fail] {$name}: {$error->getMessage()}\n"); }
}
fwrite(STDOUT, sprintf("\n%d test(s), %d failure(s).\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);
