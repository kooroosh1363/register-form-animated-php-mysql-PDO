<?php

declare(strict_types=1);

final class Database
{
    public static function connect(
        ?string $dsn = null,
        ?string $username = null,
        ?string $password = null,
    ): PDO {
        $dsn ??= Config::dsn();
        $username ??= Config::dbUser();
        $password ??= Config::dbPassword();

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }
}
