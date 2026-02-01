<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class JwtOrLoginEntryPoint implements AuthenticationEntryPointInterface
{
    private string $apiPathPattern;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        string $apiRoutePath,
    ) {
        $apiRoutePath = '/'.ltrim($apiRoutePath, '/');
        $apiRoutePath = rtrim($apiRoutePath, '/');
        if ('' === $apiRoutePath) {
            $apiRoutePath = '/api';
        }
        $apiSegment = ltrim($apiRoutePath, '/');
        $this->apiPathPattern = sprintf('#^/(?:_tz/)?%s(?:/|$)#', preg_quote($apiSegment, '#'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (1 === preg_match($this->apiPathPattern, $request->getPathInfo())) {
            return new JsonResponse(['message' => 'Authentication Required'], Response::HTTP_UNAUTHORIZED);
        }

        return new RedirectResponse($this->urlGenerator->generate('tenzero_auth_login'));
    }
}
