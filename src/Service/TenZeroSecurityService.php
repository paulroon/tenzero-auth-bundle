<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Service;

use Happycode\TenZeroAuth\Model\TenZeroUser;
use Symfony\Bundle\SecurityBundle\Security;

final class TenZeroSecurityService
{
    public array $lastErrors = [];

    public function __construct(
        private UserService $userService,
        private ConfigService $config,
        private Security $security,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->security->isGranted('IS_AUTHENTICATED_FULLY');
    }

    public function getUser(): TenZeroUser
    {
        $user = $this->security->getUser();
        if (!$this->isAuthenticated() || !($user instanceof TenZeroUser)) {
            throw new \RuntimeException('User is not authenticated');
        }

        return $user;
    }

    public function createUser(string $username, string $plainPassword, array $userValues = []): ?TenZeroUser
    {
        $userFields = $this->userService->getUserFields();
        $userData = [];
        if (!empty($userFields)) {
            $userData = array_combine(
                array_keys($userFields),
                array_map(fn ($field) => $userValues[$field] ?? null, $userFields)
            );
        }

        $userClass = $this->config->getUserClass();
        $user = new $userClass();

        $existingUserName = $this->userService->fetchUser($username);
        if ($existingUserName instanceof TenZeroUser) {
            $this->lastErrors[] = "User '$username' already exists.";

            return null;
        }

        $userData[$this->config->getUserField()] = $username;
        foreach ($userData as $prop => $propVal) {
            $setter = 'set'.ucfirst($prop);
            if (method_exists($user, $setter)) {
                $user->{$setter}($propVal);
            }
        }

        return $this->userService->setUserPassword($user, $plainPassword);
    }

    public function changeMyPassword(string $oldPlain, string $newPlain): void
    {
        $user = $this->getUser();
        if (!$this->userService->isPasswordValid($user, $oldPlain)) {
            throw new \RuntimeException('Current password is incorrect.');
        }

        $this->userService->changePassword($user, $newPlain);
    }
}
