<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class JwtPrerequisiteSubscriber implements EventSubscriberInterface
{
    private bool $validated = false;
    private readonly string $apiRoutePathWithTz;
    private readonly string $apiTokenPath;

    public function __construct(
        private readonly string $secretKeyPath,
        private readonly string $publicKeyPath,
        private readonly string $passPhrase,
        private readonly string $apiRoutePath,
        private readonly bool $debug,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->apiRoutePathWithTz = '/_tz'.$this->apiRoutePath;
        $this->apiTokenPath = $this->apiRoutePathWithTz.'/auth/token';
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 90],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->validated) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!$this->shouldValidateForPath($path)) {
            return;
        }

        $this->validateOrFail();
    }

    private function shouldValidateForPath(string $path): bool
    {
        if ($path === $this->apiTokenPath) {
            return true;
        }

        return $this->pathStartsWithSegment($path, $this->apiRoutePath)
            || $this->pathStartsWithSegment($path, $this->apiRoutePathWithTz);
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

    private function validateOrFail(): void
    {
        $errors = $this->collectErrors();
        if ([] === $errors) {
            $this->validated = true;

            return;
        }

        $message = $this->buildDetailedMessage($errors);
        if ($this->debug) {
            throw new \RuntimeException($message);
        }

        if (null !== $this->logger) {
            $this->logger->critical($message);
        }

        throw new \RuntimeException('JWT authentication is not configured.');
    }

    /**
     * @return string[]
     */
    private function collectErrors(): array
    {
        $errors = [];

        if ('' === trim($this->secretKeyPath)) {
            $errors[] = 'Missing env var JWT_SECRET_KEY.';
        }
        if ('' === trim($this->publicKeyPath)) {
            $errors[] = 'Missing env var JWT_PUBLIC_KEY.';
        }
        if ('' === trim($this->passPhrase)) {
            $errors[] = 'Missing env var JWT_PASSPHRASE.';
        }

        if ('' !== trim($this->secretKeyPath)) {
            $errors = array_merge(
                $errors,
                $this->validateKeyFile($this->secretKeyPath, 'Private key')
            );
        }

        if ('' !== trim($this->publicKeyPath)) {
            $errors = array_merge(
                $errors,
                $this->validateKeyFile($this->publicKeyPath, 'Public key')
            );
        }

        return $errors;
    }

    /**
     * @return string[]
     */
    private function validateKeyFile(string $path, string $label): array
    {
        if (!file_exists($path)) {
            return [sprintf('%s file not found at "%s".', $label, $path)];
        }

        if (!is_readable($path)) {
            return [sprintf('%s file is not readable at "%s".', $label, $path)];
        }

        if (!$this->looksLikePem($path)) {
            return [sprintf('%s file does not look like PEM at "%s".', $label, $path)];
        }

        return [];
    }

    private function looksLikePem(string $path): bool
    {
        $snippet = @file_get_contents($path, false, null, 0, 64);
        if (false === $snippet) {
            return false;
        }

        return str_starts_with(ltrim($snippet), '-----BEGIN');
    }

    /**
     * @param string[] $errors
     */
    private function buildDetailedMessage(array $errors): string
    {
        $lines = [
            'JWT authentication is not configured correctly:',
        ];

        foreach ($errors as $error) {
            $lines[] = '- '.$error;
        }

        $lines[] = 'Fix: run `php bin/console lexik:jwt:generate-keypair` and set JWT_SECRET_KEY, JWT_PUBLIC_KEY, JWT_PASSPHRASE.';

        return implode("\n", $lines);
    }
}
