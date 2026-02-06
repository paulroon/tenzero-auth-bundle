# Setup

## Requirements
- PHP >= 8.4
- Symfony 8.0+
- Doctrine ORM (doctrine/orm)

## Install
1) Enable contrib recipes in the host app `composer.json` (required to apply the Flex recipe):

```json
{
  "extra": {
    "symfony": {
      "allow-contrib": true
    }
  }
}
```

2) Require the bundle:

```bash
composer require happycode/tenzero-auth
```

3) Add the TenZero firewalls to `config/packages/security.yaml` (host app):

```yaml
security:
    firewalls:
        api:
            pattern: ^/(?:_tz/)?api(?:/|$)
            stateless: true
            provider: tenzero_user_provider
            entry_point: Happycode\TenZeroAuth\Security\JwtOrLoginEntryPoint
            json_login:
                check_path: /_tz/api/auth/token
                username_path: '%happycode_tenzero_auth.user_field%'
                password_path: 'password'
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
            jwt: ~
        web:
            pattern: ^/
            provider: tenzero_user_provider
            form_login:
                login_path: /_tz/login
                check_path: /_tz/login
                default_target_path: '%happycode_tenzero_auth.login_redirect_url%'
                username_parameter: '%happycode_tenzero_auth.user_field%'
                password_parameter: 'password'
                enable_csrf: true
                csrf_token_id: authenticate
            logout:
                path: /_tz/logout
                target: '%happycode_tenzero_auth.logout_redirect_url%'
```

If you change `api_route_path`, update the `pattern` and `check_path` accordingly.

4) Generate JWT keys (host app):

```bash
php bin/console lexik:jwt:generate-keypair
```

This creates:
- `config/jwt/private.pem`
- `config/jwt/public.pem`

Make sure `config/jwt/private.pem` is not world-readable.

5) Set environment variables (host app):

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
