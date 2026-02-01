<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Event;

use Happycode\TenZeroAuth\Model\TenZeroUser;
use Symfony\Contracts\EventDispatcher\Event;

final class PasswordResetRequestedEvent extends Event
{
    public function __construct(
        private readonly TenZeroUser $user,
        private readonly string $token,
        private readonly \DateTimeImmutable $expiresAt,
        private readonly ?string $resetUrl = null,
    ) {
    }

    public function getUser(): TenZeroUser
    {
        return $this->user;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getResetUrl(): ?string
    {
        return $this->resetUrl;
    }
}
