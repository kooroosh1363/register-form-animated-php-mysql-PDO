<?php

declare(strict_types=1);

final class RegistrationValidator
{
    public const USERNAME_MIN = 3;
    public const USERNAME_MAX = 32;
    public const EMAIL_MAX = 254;
    public const PASSWORD_MIN = 12;
    public const PASSWORD_MAX = 128;

    /** @param array<string,mixed> $input
     *  @return array{username:string,email:string,password:string,password_confirm:string}
     */
    public static function normalize(array $input): array
    {
        return [
            'username' => trim((string) ($input['username'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'password' => (string) ($input['password'] ?? ''),
            'password_confirm' => (string) ($input['password_confirm'] ?? ''),
        ];
    }

    /** @param array{username:string,email:string,password:string,password_confirm:string} $data
     *  @return array<string,string>
     */
    public static function validate(array $data): array
    {
        $errors = [];

        $usernameLength = self::length($data['username']);
        if ($usernameLength < self::USERNAME_MIN || $usernameLength > self::USERNAME_MAX) {
            $errors['username'] = sprintf(
                'Username must be between %d and %d characters.',
                self::USERNAME_MIN,
                self::USERNAME_MAX,
            );
        } elseif (!preg_match('/^[A-Za-z0-9._-]+$/', $data['username'])) {
            $errors['username'] = 'Username may use letters, numbers, dots, underscores, and hyphens.';
        }

        if (
            $data['email'] === ''
            || self::length($data['email']) > self::EMAIL_MAX
            || filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false
        ) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $passwordLength = self::length($data['password']);
        if ($passwordLength < self::PASSWORD_MIN || $passwordLength > self::PASSWORD_MAX) {
            $errors['password'] = sprintf(
                'Password must be between %d and %d characters.',
                self::PASSWORD_MIN,
                self::PASSWORD_MAX,
            );
        }

        if ($data['password_confirm'] !== $data['password']) {
            $errors['password_confirm'] = 'Passwords do not match.';
        }

        return $errors;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
