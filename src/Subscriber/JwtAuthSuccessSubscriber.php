<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Subscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class JwtAuthSuccessSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly int $tokenTtl)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [Events::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess'];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('+%d seconds', $this->tokenTtl));
        $data['expires'] = $expiresAt->format(DATE_ATOM);
        $event->setData($data);
    }
}
