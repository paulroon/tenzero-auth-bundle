<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Subscriber;

use Happycode\TenZeroAuth\Model\TenZeroUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class IdentifierFieldInitializerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $userField,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Run early (before the Security Firewall listener).
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        TenZeroUser::setIdentifierField($this->userField);
    }
}
