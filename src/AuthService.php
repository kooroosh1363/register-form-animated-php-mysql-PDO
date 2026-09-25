<?php

declare(strict_types=1);

final class AuthService
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 300;
    private const LOCK_SECONDS = 300;
    private const DUMMY_HASH = '$2y$12$GRb9IvPaIdWgIODuip8/j.4IfzbDcqkvLgxcMa8pYSlk8tz656rGW';

    public function __construct(private AccountRepository $accounts) {}

    /** @return array{ok:bool,reason:string,user:?array{id:int,username:string,email:string,status:string},locked_until:int} */
    public function attempt(string $email, string $password, int $now): array
    {
        $identifierHash = hash('sha256', strtolower(trim($email)));
        $state = $this->accounts->attemptState($identifierHash);

        if ($state['locked_until'] > $now) {
            return [
                'ok' => false,
                'reason' => 'locked',
                'user' => null,
                'locked_until' => $state['locked_until'],
            ];
        }

        $user = $this->accounts->findByEmail($email);
        $hash = $user['password_hash'] ?? self::DUMMY_HASH;
        $matches = password_verify($password, $hash);

        if ($user === null || !$matches) {
            $lockedUntil = $this->accounts->recordFailure(
                $identifierHash,
                $now,
                self::MAX_ATTEMPTS,
                self::WINDOW_SECONDS,
                self::LOCK_SECONDS,
            );

            return [
                'ok' => false,
                'reason' => $lockedUntil > $now ? 'locked' : 'invalid',
                'user' => null,
                'locked_until' => $lockedUntil,
            ];
        }

        $this->accounts->clearFailures($identifierHash);

        if ($user['status'] !== 'active') {
            return [
                'ok' => false,
                'reason' => 'unverified',
                'user' => null,
                'locked_until' => 0,
            ];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->accounts->updatePasswordHash(
                $user['id'],
                password_hash($password, PASSWORD_DEFAULT),
            );
        }

        return [
            'ok' => true,
            'reason' => 'authenticated',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'status' => $user['status'],
            ],
            'locked_until' => 0,
        ];
    }
}
