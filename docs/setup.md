# Setup

## Requirements
- PHP >= 8.4
- Symfony 8.0+
- Doctrine ORM (doctrine/orm)

## Install
1) Require the bundle:

```bash
composer require happycode/tenzero-auth
```

2) Generate JWT keys (host app):

```bash
php bin/console lexik:jwt:generate-keypair
```

This creates:
- `config/jwt/private.pem`
- `config/jwt/public.pem`

Make sure `config/jwt/private.pem` is not world-readable.

3) Set environment variables (host app):

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase
```

## Routes
- `/_tz/login` (GET/POST)
- `/_tz/register` (GET/POST)
- `/_tz/logout` (GET)
- `/_tz/change_password` (GET/POST)
- `/_tz/forgot_password` (GET/POST)
- `/_tz/reset_password/{token}` (GET/POST)
- `/_tz{api_route_path}/auth/token` (POST, JSON login)

## JWT Troubleshooting
- Required env vars: `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE`.
- Symptoms: token endpoint returns a 500 or a runtime error about JWT config.
- Fix: run `php bin/console lexik:jwt:generate-keypair` and verify the key files exist and are readable.
- If the key paths are wrong or permissions are too strict, update the env vars or file permissions.
- Never commit private keys to source control.
