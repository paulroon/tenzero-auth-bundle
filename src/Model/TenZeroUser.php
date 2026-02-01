<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Model;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

abstract class TenZeroUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    private static ?string $identifierField = null;
    private ?string $password = null;
    private array $roles = [];
    private ?string $resetPasswordTokenHash = null;
    private ?\DateTimeImmutable $resetPasswordExpiresAt = null;

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): TenZeroUser
    {
        $this->password = $password;

        return $this;
    }

    public function getResetPasswordTokenHash(): ?string
    {
        return $this->resetPasswordTokenHash;
    }

    public function setResetPasswordTokenHash(?string $tokenHash): TenZeroUser
    {
        $this->resetPasswordTokenHash = $tokenHash;

        return $this;
    }

    public function getResetPasswordExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetPasswordExpiresAt;
    }

    public function setResetPasswordExpiresAt(?\DateTimeImmutable $expiresAt): TenZeroUser
    {
        $this->resetPasswordExpiresAt = $expiresAt;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function getUserIdentifierFieldName(): string
    {
        return self::$identifierField;
    }

    public function getUserIdentifier(): string
    {
        $userField = self::$identifierField;
        if (null === $userField || '' === $userField) {
            throw new \LogicException('User identifier field is not configured.');
        }
        $getter = 'get'.ucfirst($userField);
        $value = null;
        if (method_exists($this, $getter)) {
            $value = $this->{$getter}();
        } elseif (property_exists($this, $userField)) {
            $value = $this->{$userField};
        } else {
            throw new \LogicException(sprintf('User identifier field "%s" is not accessible.', $userField));
        }
        if (!is_scalar($value)) {
            throw new \LogicException(sprintf('User identifier field "%s" must be scalar.', $userField));
        }
        $identifier = (string) $value;
        if ('' === $identifier) {
            throw new \LogicException(sprintf('User identifier field "%s" cannot be empty.', $userField));
        }

        return $identifier;
    }

    public static function setIdentifierField(string $field): void
    {
        self::$identifierField = $field;
    }
}
