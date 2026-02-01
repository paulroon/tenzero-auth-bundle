<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Service;

final class ConfigService
{
    public function __construct(
        private readonly string $userClass,
        private readonly string $userField,
        private readonly string $theme,
        private readonly string $loginRedirectUrl,
        private readonly bool $enableRegister,
        private readonly array $registerFields,
        private readonly int $resetPasswordLinkTtl,
        private readonly string $apiRoutePath,
    ) {
    }

    public function getUserClass(): string
    {
        return $this->userClass;
    }

    public function getUserField(): string
    {
        return $this->userField;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getLoginRedirectUrl(): string
    {
        return $this->loginRedirectUrl;
    }

    public function isRegisterEnabled(): bool
    {
        return $this->enableRegister;
    }

    public function getRegisterFields(): array
    {
        return $this->registerFields;
    }

    public function getResetPasswordLinkTtl(): int
    {
        return $this->resetPasswordLinkTtl;
    }

    public function getApiRoutePath(): string
    {
        return $this->apiRoutePath;
    }
}
