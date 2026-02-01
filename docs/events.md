# Events

## Password reset requested
When a reset is requested, the bundle dispatches `Happycode\TenZeroAuth\Event\PasswordResetRequestedEvent`.

Payload:
- `getUser(): TenZeroUser`
- `getToken(): string` (raw token for email)
- `getExpiresAt(): \DateTimeImmutable`
- `getResetUrl(): ?string`

### Example subscriber

```php
<?php

namespace App\EventSubscriber;

use Happycode\TenZeroAuth\Event\PasswordResetRequestedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class PasswordResetSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PasswordResetRequestedEvent::class => 'onPasswordResetRequested',
        ];
    }

    public function onPasswordResetRequested(PasswordResetRequestedEvent $event): void
    {
        $user = $event->getUser();
        $resetUrl = $event->getResetUrl();
        $expiresAt = $event->getExpiresAt();

        // Send your email here using $resetUrl and $expiresAt.
    }
}
```

## Password reset flow (web UI)
Routes:
- `/_tz/change_password` (GET/POST, requires login)
- `/_tz/forgot_password` (GET/POST, public)
- `/_tz/reset_password/{token}` (GET/POST, public)

Behavior:
- Change password validates the current password and logs the user out after updating.
- Forgot password always responds with a generic success message to prevent enumeration.
- Reset password validates the token + expiry, sets the new password, then clears the token.

Token storage:
- Only a hash of the token is stored on the user record (`reset_password_token_hash`).
- Expiry is stored in `reset_password_expires_at` and is enforced on reset.
