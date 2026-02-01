<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Happycode\TenZeroAuth\Model\TenZeroUser;
use Happycode\TenZeroAuth\Service\ConfigService;
use Happycode\TenZeroAuth\Service\UserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class UserServiceTest extends TestCase
{
    private ManagerRegistry $registry;
    private ObjectRepository $repository;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->registry = $this->createStub(ManagerRegistry::class);
        $this->repository = $this->createStub(ObjectRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->passwordHasher = $this->createStub(UserPasswordHasherInterface::class);
    }

    public function testGetUserRepositoryReturnsRegistryRepository(): void
    {
        $service = $this->createService();

        $this->assertSame($this->repository, $service->getUserRepository());
    }

    public function testFetchUserReturnsFoundUser(): void
    {
        $user = $this->createUser();

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'a@b.com'])
            ->willReturn($user);

        $service = $this->createService();

        $this->assertSame($user, $service->fetchUser('a@b.com'));
    }

    public function testFetchUserReturnsNullWhenMissing(): void
    {
        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'a@b.com'])
            ->willReturn(null);

        $service = $this->createService();

        $this->assertNull($service->fetchUser('a@b.com'));
    }

    public function testIsPasswordValidReturnsFalseWhenUserIsNull(): void
    {
        $passwordHasher = $this->usePasswordHasherMock();
        $passwordHasher->expects($this->never())->method('isPasswordValid');

        $service = $this->createService();

        $this->assertFalse($service->isPasswordValid(null, 'secret'));
    }

    public function testIsPasswordValidReturnsHasherResultForValidUser(): void
    {
        $user = $this->createUser();

        $passwordHasher = $this->usePasswordHasherMock();
        $passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'secret')
            ->willReturn(true);

        $service = $this->createService();

        $this->assertTrue($service->isPasswordValid($user, 'secret'));
    }

    public function testIsPasswordValidReturnsFalseWhenHasherReturnsFalse(): void
    {
        $user = $this->createUser();

        $passwordHasher = $this->usePasswordHasherMock();
        $passwordHasher
            ->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, 'secret')
            ->willReturn(false);

        $service = $this->createService();

        $this->assertFalse($service->isPasswordValid($user, 'secret'));
    }

    public function testSetUserPasswordPersistsAndFlushes(): void
    {
        $user = $this->createUser();

        $passwordHasher = $this->usePasswordHasherMock();
        $passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'plain-password')
            ->willReturn('hashed-password');

        $em = $this->useEntityManagerMock();
        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $service = $this->createService();

        $result = $service->setUserPassword($user, 'plain-password');

        $this->assertSame($user, $result);
        $this->assertSame('hashed-password', $user->getPassword());
    }

    public function testChangePasswordPersistsAndFlushes(): void
    {
        $user = $this->createUser();

        $passwordHasher = $this->usePasswordHasherMock();
        $passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'new-password')
            ->willReturn('hashed-new-password');

        $em = $this->useEntityManagerMock();
        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $service = $this->createService();

        $service->changePassword($user, 'new-password');

        $this->assertSame('hashed-new-password', $user->getPassword());
    }

    public function testSetResetPasswordTokenPersistsAndFlushes(): void
    {
        $user = $this->createUser();
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $em = $this->useEntityManagerMock();
        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $service = $this->createService();

        $service->setResetPasswordToken($user, 'token-hash', $expiresAt);

        $this->assertSame('token-hash', $user->getResetPasswordTokenHash());
        $this->assertSame($expiresAt, $user->getResetPasswordExpiresAt());
    }

    public function testClearResetPasswordTokenPersistsAndFlushes(): void
    {
        $user = $this->createUser('token-hash', new \DateTimeImmutable('+1 hour'));

        $em = $this->useEntityManagerMock();
        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $service = $this->createService();

        $service->clearResetPasswordToken($user);

        $this->assertNull($user->getResetPasswordTokenHash());
        $this->assertNull($user->getResetPasswordExpiresAt());
    }

    public function testFindUserByResetTokenReturnsNullWhenTokenIsEmpty(): void
    {
        $repository = $this->useRepositoryMock();
        $repository->expects($this->never())->method('findOneBy');

        $service = $this->createService();

        $this->assertNull($service->findUserByResetToken(''));
    }

    public function testFindUserByResetTokenQueriesByHashedToken(): void
    {
        $rawToken = 'raw-token';
        $tokenHash = hash('sha256', $rawToken);

        $user = $this->createUser($tokenHash, new \DateTimeImmutable('+1 hour'));

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordTokenHash' => $tokenHash])
            ->willReturn($user);

        $service = $this->createService();

        $this->assertSame($user, $service->findUserByResetToken($rawToken));
    }

    public function testFindUserByResetTokenReturnsNullWhenRepositoryReturnsNull(): void
    {
        $rawToken = 'raw-token';
        $tokenHash = hash('sha256', $rawToken);

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordTokenHash' => $tokenHash])
            ->willReturn(null);

        $service = $this->createService();

        $this->assertNull($service->findUserByResetToken($rawToken));
    }

    public function testFindUserByResetTokenReturnsNullWhenUserIsNotTenZeroUser(): void
    {
        $rawToken = 'raw-token';
        $tokenHash = hash('sha256', $rawToken);

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordTokenHash' => $tokenHash])
            ->willReturn(new \stdClass());

        $service = $this->createService();

        $this->assertNull($service->findUserByResetToken($rawToken));
    }

    public function testFindUserByResetTokenReturnsNullWhenExpiresAtIsNull(): void
    {
        $rawToken = 'raw-token';
        $tokenHash = hash('sha256', $rawToken);

        $user = $this->createUser($tokenHash, null);

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordTokenHash' => $tokenHash])
            ->willReturn($user);

        $service = $this->createService();

        $this->assertNull($service->findUserByResetToken($rawToken));
    }

    public function testFindUserByResetTokenReturnsNullWhenTokenIsExpired(): void
    {
        $rawToken = 'raw-token';
        $tokenHash = hash('sha256', $rawToken);

        $user = $this->createUser($tokenHash, new \DateTimeImmutable('-1 second'));

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordTokenHash' => $tokenHash])
            ->willReturn($user);

        $service = $this->createService();

        $this->assertNull($service->findUserByResetToken($rawToken));
    }

    public function testFindUserByResetTokenReturnsNullWhenTokenHashDoesNotMatch(): void
    {
        $rawToken = 'raw-token';
        $tokenHash = hash('sha256', $rawToken);

        $user = $this->createUser('different-hash', new \DateTimeImmutable('+1 hour'));

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordTokenHash' => $tokenHash])
            ->willReturn($user);

        $service = $this->createService();

        $this->assertNull($service->findUserByResetToken($rawToken));
    }

    public function testFindUserByResetTokenReturnsUserWhenValid(): void
    {
        $rawToken = 'raw-token';
        $tokenHash = hash('sha256', $rawToken);

        $user = $this->createUser($tokenHash, new \DateTimeImmutable('+1 hour'));

        $repository = $this->useRepositoryMock();
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['resetPasswordTokenHash' => $tokenHash])
            ->willReturn($user);

        $service = $this->createService();

        $this->assertSame($user, $service->findUserByResetToken($rawToken));
    }

    public function testGetUserFieldsReturnsEmptyWhenNoAllowedStrings(): void
    {
        $service = $this->createService(['registerFields' => [null, 123, false]]);

        $this->assertSame([], $service->getUserFields());
    }

    public function testGetUserFieldsReturnsAllowedMappedFields(): void
    {
        $em = $this->useEntityManagerMock();
        $service = $this->createService(['registerFields' => ['firstname', 'lastname'], 'userField' => 'email']);

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getFieldNames')->willReturn(['id', 'email', 'password', 'firstname', 'lastname']);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);

        $em->expects($this->once())->method('getClassMetadata')->with(FakeUser::class)->willReturn($metadata);

        $this->assertSame(['firstname' => 'firstname', 'lastname' => 'lastname'], $service->getUserFields());
    }

    public function testGetUserFieldsThrowsForInvalidRegisterFields(): void
    {
        $em = $this->useEntityManagerMock();
        $service = $this->createService(['registerFields' => ['firstname', 'doesNotExist']]);

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getFieldNames')->willReturn(['id', 'email', 'password', 'firstname', 'lastname']);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);

        $em->expects($this->once())->method('getClassMetadata')->with(FakeUser::class)->willReturn($metadata);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid register_fields');
        $this->expectExceptionMessage('doesNotExist');

        $service->getUserFields();
    }

    private function createService(array $overrides = []): UserService
    {
        $this->configureRegistry();
        $config = $this->createConfig($overrides);

        return new UserService($this->registry, $this->passwordHasher, $config);
    }

    private function createUser(?string $tokenHash = null, ?\DateTimeImmutable $expiresAt = null): FakeUser
    {
        $user = new FakeUser();
        $user->setResetPasswordTokenHash($tokenHash);
        $user->setResetPasswordExpiresAt($expiresAt);

        return $user;
    }

    private function createConfig(array $overrides = []): ConfigService
    {
        $data = array_merge([
            'userClass' => FakeUser::class,
            'userField' => 'email',
            'theme' => 'tz-theme-default',
            'loginRedirectUrl' => '/',
            'enableRegister' => true,
            'registerFields' => [],
            'resetPasswordLinkTtl' => 86400,
            'apiRoutePath' => '/api',
        ], $overrides);

        return new ConfigService(
            $data['userClass'],
            $data['userField'],
            $data['theme'],
            $data['loginRedirectUrl'],
            $data['enableRegister'],
            $data['registerFields'],
            $data['resetPasswordLinkTtl'],
            $data['apiRoutePath']
        );
    }

    private function useRepositoryMock(): ObjectRepository
    {
        $this->repository = $this->createMock(ObjectRepository::class);

        return $this->repository;
    }

    private function useEntityManagerMock(): EntityManagerInterface
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        return $this->em;
    }

    private function usePasswordHasherMock(): UserPasswordHasherInterface
    {
        $this->passwordHasher = $this->createMock(FakePasswordHasher::class);

        return $this->passwordHasher;
    }

    private function configureRegistry(): void
    {
        $this->registry->method('getManagerForClass')->with(FakeUser::class)->willReturn($this->em);
        $this->registry->method('getRepository')->with(FakeUser::class)->willReturn($this->repository);
    }
}

final class FakeUser extends TenZeroUser
{
    private string $email = 'user@example.com';
    private ?string $firstname = null;
    private ?string $lastname = null;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}

class FakePasswordHasher implements UserPasswordHasherInterface
{
    public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
    {
        return '';
    }

    public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool
    {
        return false;
    }

    public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
    {
        return false;
    }
}
