# VerifyFlow — Secure PHP Registration + Email Verification Lifecycle

[![Quality](https://github.com/kooroosh1363/register-form-animated-php-mysql-PDO/actions/workflows/quality.yml/badge.svg)](https://github.com/kooroosh1363/register-form-animated-php-mysql-PDO/actions/workflows/quality.yml)

VerifyFlow modernizes the original 2023 animated PHP/PDO registration project into a secure account-activation lifecycle demo.

## What makes this repository distinct

This project focuses on the transition:

```text
pending -> verified -> authenticated
```

A newly registered account cannot sign in until a one-time verification token activates it.

## Security model

- passwords are hashed with PHP's native password APIs
- account status starts as `pending`
- unverified accounts cannot authenticate
- verification tokens are generated with `random_bytes()`
- only the SHA-256 hash of each verification token is stored
- tokens expire after 30 minutes
- tokens are single-use
- issuing a new token replaces the old token
- token consumption and account activation happen in one database transaction
- PDO prepared statements
- CSRF-protected registration/login/logout
- strict sessions and session ID rotation
- inactivity timeout
- login throttling
- security headers
- environment-based database configuration

## Demo verification delivery

This repository deliberately does not fake an email provider.

For local development, create a verification link with:

```bash
php bin/issue-verification.php user@example.com http://localhost:8000
```

That prints a one-time verification URL. In a production system, the same token would be delivered through a real transactional email service.

## Local setup

```bash
php bin/migrate.php
php -S localhost:8000
```

Then:

1. Register at `/register.php`
2. Issue the verification link from the CLI
3. Open the verification link
4. Sign in at `/login.php`
5. Access the protected dashboard

## Database support

- SQLite for zero-setup local use
- MySQL for the original PDO/MySQL stack

GitHub Actions runs the lifecycle tests against both engines.

## Lifecycle tests

The suite verifies:

- secure registration validation
- password hashing
- pending accounts cannot sign in
- token issuance
- successful activation
- token expiry
- one-time token consumption
- old-token invalidation after reissue
- successful login after verification
- login throttling
- the same end-to-end lifecycle on MySQL

## Performance cleanup

The old repository shipped a roughly 40 MB 2160p background video. The maintained version removes that asset and recreates the visual depth with CSS.

This keeps the project lightweight and avoids shipping a large binary for a login form.

## Scope

This is an email-verification lifecycle demo, not a complete identity platform. Actual email delivery, password reset, MFA, OAuth/OIDC, device/session management, and audit retention remain out of scope.

## Deployment

GitHub Pages cannot execute PHP. Deploy to a PHP-capable host and keep database credentials in environment configuration.

## License

No license is currently included.
