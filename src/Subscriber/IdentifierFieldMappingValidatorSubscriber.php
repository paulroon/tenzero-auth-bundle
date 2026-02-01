<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class IdentifierFieldMappingValidatorSubscriber implements EventSubscriberInterface
{
    private static bool $checked = false;

    public function __construct(
        private readonly ?ManagerRegistry $registry,
        private readonly ?LoggerInterface $logger,
        private readonly string $userClass,
        private readonly string $userField,
        private readonly bool $debug,
        private readonly string $apiRoutePath = '',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 95],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || self::$checked) {
            return;
        }

        if ('' !== $this->apiRoutePath && !$this->shouldValidateForPath($event->getRequest()->getPathInfo())) {
            return;
        }

        self::$checked = true;

        if (null === $this->registry || '' === $this->userClass || '' === $this->userField) {
            return;
        }

        if (!interface_exists(EntityManagerInterface::class)) {
            return;
        }

        $em = $this->registry->getManagerForClass($this->userClass);
        if (!$em instanceof EntityManagerInterface) {
            return;
        }

        try {
            $metadata = $em->getClassMetadata($this->userClass);
        } catch (MappingException) {
            $detail = sprintf(
                'Configured user_class "%s" is not a mapped Doctrine entity. Ensure your User entity is mapped and extends Happycode\\TenZeroAuth\\Model\\TenZeroUser.',
                $this->userClass
            );
            $this->logWarning($detail);
            $this->maybeThrow($detail);

            return;
        }

        if ($metadata->hasField($this->userField)) {
            return;
        }

        $detail = sprintf(
            'Configured user_field "%s" is not a mapped Doctrine field on user_class "%s". Map this field as a Doctrine column on your User entity and add a unique index.',
            $this->userField,
            $this->userClass
        );
        $this->logWarning($detail);
        $this->maybeThrow($detail);
    }

    private function logWarning(string $detail): void
    {
        if (null === $this->logger) {
            return;
        }

        $this->logger->warning($detail, [
            'user_class' => $this->userClass,
            'user_field' => $this->userField,
        ]);
    }

    private function maybeThrow(string $detail): void
    {
        if ($this->debug) {
            throw new \RuntimeException($detail);
        }
    }

    private function shouldValidateForPath(string $path): bool
    {
        if ($this->pathStartsWithSegment($path, '/_tz')) {
            return true;
        }

        $apiRoutePathWithTz = '/_tz'.$this->apiRoutePath;

        return $this->pathStartsWithSegment($path, $this->apiRoutePath)
            || $this->pathStartsWithSegment($path, $apiRoutePathWithTz);
    }

    private function pathStartsWithSegment(string $path, string $prefix): bool
    {
        if (!str_starts_with($path, $prefix)) {
            return false;
        }

        if ($path === $prefix) {
            return true;
        }

        return '/' === $path[strlen($prefix)];
    }
}
