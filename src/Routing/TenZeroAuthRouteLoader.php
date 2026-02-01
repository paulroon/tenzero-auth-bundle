<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Routing;

use Happycode\TenZeroAuth\Controller\SecurityController;
use Happycode\TenZeroAuth\Service\ConfigService;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class TenZeroAuthRouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(private readonly ConfigService $config)
    {
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('Do not add the TenZeroAuth routes loader twice.');
        }

        $routes = new RouteCollection();
        $prefix = '/_tz';

        $routes->add('tenzero_auth_login', new Route(
            $prefix.'/login',
            ['_controller' => SecurityController::class.'::login'],
            [],
            [],
            '',
            [],
            ['GET', 'POST']
        ));

        if ($this->config->isRegisterEnabled()) {
            $routes->add('tenzero_auth_register', new Route(
                $prefix.'/register',
                ['_controller' => SecurityController::class.'::register'],
                [],
                [],
                '',
                [],
                ['GET', 'POST']
            ));
        }

        $routes->add('tenzero_auth_forgot_password', new Route(
            $prefix.'/forgot_password',
            ['_controller' => SecurityController::class.'::forgotPassword'],
            [],
            [],
            '',
            [],
            ['GET', 'POST']
        ));

        $routes->add('tenzero_auth_reset_password', new Route(
            $prefix.'/reset_password/{token}',
            ['_controller' => SecurityController::class.'::resetPassword'],
            [],
            [],
            '',
            [],
            ['GET', 'POST']
        ));

        $routes->add('tenzero_auth_api_token', new Route(
            $prefix.$this->config->getApiRoutePath().'/auth/token',
            ['_controller' => SecurityController::class.'::apiToken'],
            [],
            [],
            '',
            [],
            ['POST']
        ));

        $routes->add('tenzero_auth_change_password', new Route(
            $prefix.'/change_password',
            ['_controller' => SecurityController::class.'::changePassword'],
            [],
            [],
            '',
            [],
            ['GET', 'POST']
        ));

        $routes->add('tenzero_auth_logout', new Route(
            $prefix.'/logout',
            ['_controller' => SecurityController::class.'::logout'],
            [],
            [],
            '',
            [],
            ['GET']
        ));

        $this->loaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'tenzero_auth' === $type;
    }
}
