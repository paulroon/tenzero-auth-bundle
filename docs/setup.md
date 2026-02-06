# Setup

## Requirements

- PHP >= 8.4
- Symfony 8.0+
- Doctrine ORM (doctrine/orm)

## Install

1. Require the bundle:

```bash
composer require happycode/tenzero-auth
```

Note: If you want the Symfony Flex recipe from `recipes-contrib` to apply automatically,
set `"extra.symfony.allow-contrib": true` in your host app's `composer.json`.

2. Generate JWT keys (host app):

```bash
php bin/console lexik:jwt:generate-keypair
```

This creates:

- `config/jwt/private.pem`
- `config/jwt/public.pem`

Make sure `config/jwt/private.pem` is not world-readable.

3. Set environment variables (host app):

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your-passphrase
```
