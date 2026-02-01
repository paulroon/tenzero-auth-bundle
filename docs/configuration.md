# Configuration

## Bundle config (host app)

```yaml
# config/packages/happycode_tenzero_auth.yaml
happycode_tenzero_auth:

    # The class that will be used to authenticate user.
    user_class:           ~ # Required, Example: \App\Entity\User

    # The field that will be used to identify the user.
    user_field:           ~ # Required, Example: 'username | email'

    # Optional theme class applied to the auth layout body.
    theme:                tz-theme-default

    # Enable or disable the register route and UI.
    enable_register:      true

    # Explicit allowlist of user fields permitted during registration.
    register_fields:      []

    # Optional app name displayed in the auth layout.
    app_name:             TenZero

    # Optional app description displayed in the auth layout.
    app_description:      'A comprehensive business management platform designed to streamline your operations and boost productivity.'

    # Access control rules, same structure as security.access_control. Overrides defaults by matching path or adds new rules.
    access_control:

        # Prototype
        -
            path:                 ~
            roles:                ~

    # URL to redirect to after successful login.
    login_redirect_url:   /

    # URL to redirect to after logout.
    logout_redirect_url:  /login

    # JWT token lifetime in seconds.
    token_ttl:            3600

    # Reset password link lifetime in seconds.
    reset_password_link_ttl: 86400

    # Base API route path used for the API firewall (default: /api).
    api_route_path:       /api
```

## Host User requirements
- Your User entity must extend `Happycode\TenZeroAuth\Model\TenZeroUser`.
- You MUST define and Doctrine-map the identifier field configured in `user_field` (e.g. `email`).
  - The Doctrine entity provider loads users by this field, so it must be queryable.
  - Add a UNIQUE index/constraint on this field.
- Base auth fields (`password`, `roles`) are mapped by the bundle (`TenZeroUser.orm.xml`) and should appear in migrations automatically.
  - Ensure the bundle mapping is loaded so the mapped-superclass fields are included.
  - You can add additional fields freely on your User entity.
- If you override roles handling, you must still provide `getRoles(): array` for Symfony Security and persist roles somewhere the entity can read.

## User class setup
See `docs/user-class.md` for the full example and guidance.

## Notes
- `user_class` must extend `Happycode\TenZeroAuth\Model\TenZeroUser`.
- `access_control` merges with defaults by matching `path`:
  - Same `path` overrides the default rule.
  - New rules are inserted before the catch-all `^/`.
- Set `enable_register: false` to disable the register route and hide register links.
- `register_fields` is an allowlist for extra user fields that can be shown/accepted on the registration form.
  - If empty or omitted, only the identifier + password fields are used.
  - Fields must be mapped Doctrine fields with setters on the user entity.
- The login and register forms submit CSRF tokens; ensure the host app has CSRF enabled (default in Symfony).
- Web login uses the `web` firewall and redirects to `login_redirect_url`.
- API login uses `POST /_tz{api_route_path}/auth/token` with JSON login (Lexik JWT).
- Set `token_ttl` to control JWT expiration time (seconds).
- Set `reset_password_link_ttl` to control reset link expiration time (seconds).
- `api_route_path` controls the API firewall base path (defaults to `/api`), matching both `/_tz{api_route_path}/...` and `{api_route_path}/...`.
- Bundle frontend assets are served via AssetMapper under `/assets/tenzero-auth/*` (ensure `/assets` is publicly accessible).

## Troubleshooting
- Login always fails / user not found: `user_field` is not mapped or not unique on your User entity.
- Migration missing `password`/`roles` columns: the `TenZeroUser` mapped-superclass is not applied (ensure your User extends it and the bundle mapping is enabled).
