<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Happycode\TenZeroAuth\Model\TenZeroUser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserService
{
    private ObjectRepository $userRepository;

    private EntityManagerInterface $em;

    public function __construct(
        ManagerRegistry $registry,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ConfigService $config,
    ) {
        $em = $registry->getManagerForClass($this->config->getUserClass());
        if (!$em instanceof EntityManagerInterface) {
            throw new \RuntimeException('User entity manager is not available.');
        }
        $this->em = $em;
        $this->userRepository = $registry->getRepository($this->config->getUserClass());
    }

    public function getUserRepository(): ObjectRepository
    {
        return $this->userRepository;
    }

    public function fetchUser(string $username): ?TenZeroUser
    {
        $userField = $this->config->getUserField();
        $user = $this->userRepository->findOneBy([$userField => $username]);

        return $user ?? null;
    }

    /**
     * @template T of TenZeroUser
     *
     * @param T $user
     *
     * @return T
     */
    public function setUserPassword(TenZeroUser $user, string $password): object
    {
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function changePassword(TenZeroUser $user, string $newPlainPassword): void
    {
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $newPlainPassword)
        );

        $this->em->persist($user);
        $this->em->flush();
    }

    public function isPasswordValid(?TenZeroUser $user, string $password): bool
    {
        return $user instanceof TenZeroUser && $this->passwordHasher->isPasswordValid($user, $password);
    }

    public function setResetPasswordToken(TenZeroUser $user, string $tokenHash, \DateTimeImmutable $expiresAt): void
    {
        $user->setResetPasswordTokenHash($tokenHash);
        $user->setResetPasswordExpiresAt($expiresAt);

        $this->em->persist($user);
        $this->em->flush();
    }

    public function clearResetPasswordToken(TenZeroUser $user): void
    {
        $user->setResetPasswordTokenHash(null);
        $user->setResetPasswordExpiresAt(null);

        $this->em->persist($user);
        $this->em->flush();
    }

    public function findUserByResetToken(string $rawToken): ?TenZeroUser
    {
        if ('' === $rawToken) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $user = $this->userRepository->findOneBy(['resetPasswordTokenHash' => $tokenHash]);
        if (!$user instanceof TenZeroUser) {
            return null;
        }
        $expiresAt = $user->getResetPasswordExpiresAt();
        if (!$expiresAt instanceof \DateTimeImmutable || $expiresAt <= new \DateTimeImmutable()) {
            return null;
        }
        if (!hash_equals((string) $user->getResetPasswordTokenHash(), $tokenHash)) {
            return null;
        }

        return $user;
    }

    /**
     * A method to return the host app fields for the user_class that can be populated.
     */
    public function getUserFields(): array
    {
        $userClass = $this->config->getUserClass();
        $userField = $this->config->getUserField();
        $allowedFields = array_values(array_filter($this->config->getRegisterFields(), 'is_string'));
        if (empty($allowedFields)) {
            return [];
        }
        // Use Doctrine metadata to discover mapped fields
        $metadata = $this->em->getClassMetadata($userClass);
        $fields = $metadata->getFieldNames();

        // Exclude identifier fields and any fields without a public setter
        $identifiers = $metadata->getIdentifierFieldNames();

        $candidateFields = array_values(array_filter($fields, fn (string $field) => !in_array($field, $identifiers, true)));

        // Keep only fields with a setter on the entity (e.g., setEmail, setFirstname)
        $result = [];
        $filterFields = ['password', $userField];
        $validFields = [];
        foreach ($candidateFields as $field) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }
            if (in_array($field, $filterFields)) {
                continue;
            }
            $setter = 'set'.ucfirst($field);
            if (method_exists($userClass, $setter)) {
                $alias = $field;
                $result[$alias] = $field;
                $validFields[] = $field;
            }
        }

        $invalidFields = array_values(array_diff($allowedFields, $validFields));
        if (!empty($invalidFields)) {
            throw new \RuntimeException(sprintf('Invalid register_fields: %s. Ensure each field exists on %s with a matching setter and correct case.', implode(', ', $invalidFields), $userClass));
        }

        return $result;
    }
}
