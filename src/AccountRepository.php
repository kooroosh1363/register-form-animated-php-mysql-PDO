<?php

declare(strict_types=1);

final class AccountRepository
{
    private string $driver;

    public function __construct(private PDO $pdo)
    {
        $this->driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function migrate(): void
    {
        if ($this->driver === 'mysql') {
            $this->pdo->exec(
                "CREATE TABLE IF NOT EXISTS users (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    username VARCHAR(32) NOT NULL,
                    email VARCHAR(254) NOT NULL,
                    normalized_email VARCHAR(254) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    status ENUM('pending','active') NOT NULL DEFAULT 'pending',
                    email_verified_at BIGINT UNSIGNED NULL,
                    created_at BIGINT UNSIGNED NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY users_normalized_email_unique (normalized_email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS email_verifications (
                    user_id BIGINT UNSIGNED NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at BIGINT UNSIGNED NOT NULL,
                    created_at BIGINT UNSIGNED NOT NULL,
                    consumed_at BIGINT UNSIGNED NULL,
                    PRIMARY KEY (user_id),
                    UNIQUE KEY verification_token_hash_unique (token_hash),
                    CONSTRAINT verification_user_fk
                        FOREIGN KEY (user_id) REFERENCES users(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS login_attempts (
                    identifier_hash CHAR(64) NOT NULL,
                    failures INT UNSIGNED NOT NULL,
                    first_failed_at BIGINT UNSIGNED NOT NULL,
                    locked_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (identifier_hash)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            return;
        }

        if ($this->driver !== 'sqlite') {
            throw new RuntimeException('Unsupported database driver: ' . $this->driver);
        }

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                email TEXT NOT NULL,
                normalized_email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','active')),
                email_verified_at INTEGER NULL,
                created_at INTEGER NOT NULL
            )"
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS email_verifications (
                user_id INTEGER PRIMARY KEY,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL,
                consumed_at INTEGER NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                identifier_hash TEXT PRIMARY KEY,
                failures INTEGER NOT NULL,
                first_failed_at INTEGER NOT NULL,
                locked_until INTEGER NOT NULL DEFAULT 0
            )'
        );
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE normalized_email = :normalized_email LIMIT 1'
        );
        $statement->execute([':normalized_email' => self::normalizeEmail($email)]);

        return $statement->fetchColumn() !== false;
    }

    public function createPendingUser(
        string $username,
        string $email,
        string $passwordHash,
        int $now,
    ): int {
        $statement = $this->pdo->prepare(
            "INSERT INTO users
                (username, email, normalized_email, password_hash, status, created_at)
             VALUES
                (:username, :email, :normalized_email, :password_hash, 'pending', :created_at)"
        );

        $statement->execute([
            ':username' => trim($username),
            ':email' => trim($email),
            ':normalized_email' => self::normalizeEmail($email),
            ':password_hash' => $passwordHash,
            ':created_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,username:string,email:string,password_hash:string,status:string,email_verified_at:?int,created_at:int}|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, password_hash, status, email_verified_at, created_at
             FROM users
             WHERE normalized_email = :normalized_email
             LIMIT 1'
        );
        $statement->execute([':normalized_email' => self::normalizeEmail($email)]);

        $row = $statement->fetch();
        return is_array($row) ? self::mapUser($row) : null;
    }

    /** @return array{id:int,username:string,email:string,password_hash:string,status:string,email_verified_at:?int,created_at:int}|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, password_hash, status, email_verified_at, created_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        $row = $statement->fetch();
        return is_array($row) ? self::mapUser($row) : null;
    }

    public function replaceVerificationToken(
        int $userId,
        string $tokenHash,
        int $expiresAt,
        int $now,
    ): void {
        if ($this->driver === 'mysql') {
            $statement = $this->pdo->prepare(
                'INSERT INTO email_verifications
                    (user_id, token_hash, expires_at, created_at, consumed_at)
                 VALUES
                    (:user_id, :token_hash, :expires_at, :created_at, NULL)
                 ON DUPLICATE KEY UPDATE
                    token_hash = VALUES(token_hash),
                    expires_at = VALUES(expires_at),
                    created_at = VALUES(created_at),
                    consumed_at = NULL'
            );
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO email_verifications
                    (user_id, token_hash, expires_at, created_at, consumed_at)
                 VALUES
                    (:user_id, :token_hash, :expires_at, :created_at, NULL)
                 ON CONFLICT(user_id) DO UPDATE SET
                    token_hash = excluded.token_hash,
                    expires_at = excluded.expires_at,
                    created_at = excluded.created_at,
                    consumed_at = NULL'
            );
        }

        $statement->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':created_at' => $now,
        ]);
    }

    /** @return array{user_id:int,expires_at:int,consumed_at:?int}|null */
    public function verificationByHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT user_id, expires_at, consumed_at
             FROM email_verifications
             WHERE token_hash = :token_hash
             LIMIT 1'
        );
        $statement->execute([':token_hash' => $tokenHash]);

        $row = $statement->fetch();
        if (!is_array($row)) return null;

        return [
            'user_id' => (int) $row['user_id'],
            'expires_at' => (int) $row['expires_at'],
            'consumed_at' => $row['consumed_at'] === null ? null : (int) $row['consumed_at'],
        ];
    }

    public function activateByTokenHash(string $tokenHash, int $now): bool
    {
        $verification = $this->verificationByHash($tokenHash);

        if (
            $verification === null
            || $verification['consumed_at'] !== null
            || $verification['expires_at'] < $now
        ) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $consume = $this->pdo->prepare(
                'UPDATE email_verifications
                 SET consumed_at = :consumed_at
                 WHERE token_hash = :token_hash
                   AND consumed_at IS NULL
                   AND expires_at >= :now'
            );
            $consume->execute([
                ':consumed_at' => $now,
                ':token_hash' => $tokenHash,
                ':now' => $now,
            ]);

            if ($consume->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $activate = $this->pdo->prepare(
                "UPDATE users
                 SET status = 'active',
                     email_verified_at = :verified_at
                 WHERE id = :id
                   AND status = 'pending'"
            );
            $activate->execute([
                ':verified_at' => $now,
                ':id' => $verification['user_id'],
            ]);

            if ($activate->rowCount() !== 1) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function updatePasswordHash(int $userId, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );
        $statement->execute([
            ':password_hash' => $passwordHash,
            ':id' => $userId,
        ]);
    }

    /** @return array{failures:int,first_failed_at:int,locked_until:int} */
    public function attemptState(string $identifierHash): array
    {
        $statement = $this->pdo->prepare(
            'SELECT failures, first_failed_at, locked_until
             FROM login_attempts
             WHERE identifier_hash = :identifier_hash
             LIMIT 1'
        );
        $statement->execute([':identifier_hash' => $identifierHash]);

        $row = $statement->fetch();
        if (!is_array($row)) {
            return ['failures' => 0, 'first_failed_at' => 0, 'locked_until' => 0];
        }

        return [
            'failures' => (int) $row['failures'],
            'first_failed_at' => (int) $row['first_failed_at'],
            'locked_until' => (int) $row['locked_until'],
        ];
    }

    public function recordFailure(
        string $identifierHash,
        int $now,
        int $maxAttempts,
        int $windowSeconds,
        int $lockSeconds,
    ): int {
        $state = $this->attemptState($identifierHash);

        if ($state['first_failed_at'] === 0 || ($now - $state['first_failed_at']) >= $windowSeconds) {
            $failures = 1;
            $firstFailedAt = $now;
        } else {
            $failures = $state['failures'] + 1;
            $firstFailedAt = $state['first_failed_at'];
        }

        $lockedUntil = $failures >= $maxAttempts ? $now + $lockSeconds : 0;

        if ($state['first_failed_at'] === 0) {
            $statement = $this->pdo->prepare(
                'INSERT INTO login_attempts
                    (identifier_hash, failures, first_failed_at, locked_until)
                 VALUES
                    (:identifier_hash, :failures, :first_failed_at, :locked_until)'
            );
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE login_attempts
                 SET failures = :failures,
                     first_failed_at = :first_failed_at,
                     locked_until = :locked_until
                 WHERE identifier_hash = :identifier_hash'
            );
        }

        $statement->execute([
            ':identifier_hash' => $identifierHash,
            ':failures' => $failures,
            ':first_failed_at' => $firstFailedAt,
            ':locked_until' => $lockedUntil,
        ]);

        return $lockedUntil;
    }

    public function clearFailures(string $identifierHash): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM login_attempts WHERE identifier_hash = :identifier_hash'
        );
        $statement->execute([':identifier_hash' => $identifierHash]);
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /** @param array<string,mixed> $row
     *  @return array{id:int,username:string,email:string,password_hash:string,status:string,email_verified_at:?int,created_at:int}
     */
    private static function mapUser(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'email' => (string) $row['email'],
            'password_hash' => (string) $row['password_hash'],
            'status' => (string) $row['status'],
            'email_verified_at' => $row['email_verified_at'] === null ? null : (int) $row['email_verified_at'],
            'created_at' => (int) $row['created_at'],
        ];
    }
}
