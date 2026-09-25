<?php

declare(strict_types=1);

final class Config
{
    public static function dsn(): string
    {
        return getenv('DB_DSN') ?: 'sqlite:' . dirname(__DIR__) . '/storage/accounts.sqlite';
    }

    public static function dbUser(): ?string
    {
        $value = getenv('DB_USER');
        return $value === false || $value === '' ? null : $value;
    }

    public static function dbPassword(): ?string
    {
        $value = getenv('DB_PASSWORD');
        return $value === false ? null : $value;
    }

    public static function verificationTtlSeconds(): int
    {
        return 1800;
    }

    public static function idleTimeoutSeconds(): int
    {
        return 1800;
    }

    public static function sessionRegenerationSeconds(): int
    {
        return 600;
    }
}
