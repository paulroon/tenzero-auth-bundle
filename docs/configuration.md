# Configuration

[Back to README](../README.md)

## Bundle config (host app)

```yaml
# config/packages/happycode_tenzero_auth.yaml
happycode_tenzero_auth:
  # The class that will be used to authenticate user.
  user_class: ~ # Required, Example: \App\Entity\User

  # The field that will be used to identify the user.
  user_field: ~ # Required, Example: 'username | email'

  # Optional theme class applied to the auth layout body.
  theme: tz-theme-default

  # Enable or disable the register route and UI.
  enable_register: true

  # Explicit allowlist of user fields permitted during registration.
  register_fields: []

  # Optional app name displayed in the auth layout.
  app_name: TenZero

  # Optional app description displayed in the auth layout.
  app_description: "Built in authentication for TenZero."

  # Access control rules, same structure as security.access_control. Overrides defaults by matching path or adds new rules.
  access_control:
    # Prototype
    - path: ~
      roles: ~

  # URL to redirect to after successful login.
  login_redirect_url: /

  # URL to redirect to after logout.
  logout_redirect_url: /login

  # JWT token lifetime in seconds.
  token_ttl: 3600

  # Reset password link lifetime in seconds.
  reset_password_link_ttl: 86400

  # Base API route path used for the API firewall (default: /api).
  api_route_path: /api
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
