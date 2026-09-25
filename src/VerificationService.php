<?php

declare(strict_types=1);

final class VerificationService
{
    public function __construct(private AccountRepository $accounts) {}

    /** @return array{ok:bool,reason:string,token:?string,expires_at:int} */
    public function issueForEmail(string $email, int $now): array
    {
        $user = $this->accounts->findByEmail($email);

        if ($user === null) {
            return ['ok' => false, 'reason' => 'not_found', 'token' => null, 'expires_at' => 0];
        }

        if ($user['status'] === 'active') {
            return ['ok' => false, 'reason' => 'already_verified', 'token' => null, 'expires_at' => 0];
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = $now + Config::verificationTtlSeconds();

        $this->accounts->replaceVerificationToken(
            $user['id'],
            hash('sha256', $token),
            $expiresAt,
            $now,
        );

        return [
            'ok' => true,
            'reason' => 'issued',
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    /** @return array{ok:bool,reason:string} */
    public function verify(string $token, int $now): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        $activated = $this->accounts->activateByTokenHash(
            hash('sha256', $token),
            $now,
        );

        return $activated
            ? ['ok' => true, 'reason' => 'verified']
            : ['ok' => false, 'reason' => 'invalid_or_expired'];
    }
}
