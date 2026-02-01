<?php

declare(strict_types=1);

use Happycode\TenZeroAuth\Model\TenZeroUser;
use Happycode\TenZeroAuth\Routing\TenZeroAuthRouteLoader;
use Happycode\TenZeroAuth\Security\JwtOrLoginEntryPoint;
use Happycode\TenZeroAuth\Service\ConfigService;
use Happycode\TenZeroAuth\Service\TenZeroSecurityService;
use Happycode\TenZeroAuth\Service\UserService;
use Happycode\TenZeroAuth\Subscriber\IdentifierFieldInitializerSubscriber;
use Happycode\TenZeroAuth\Subscriber\IdentifierFieldMappingValidatorSubscriber;
use Happycode\TenZeroAuth\Subscriber\JwtAuthSuccessSubscriber;
use Happycode\TenZeroAuth\Subscriber\JwtPrerequisiteSubscriber;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->private();

    // ConfigService
    $services->set(ConfigService::class)
        ->arg('$userClass', '%happycode_tenzero_auth.user_class%')
        ->arg('$userField', '%happycode_tenzero_auth.user_field%')
        ->arg('$theme', '%happycode_tenzero_auth.theme%')
        ->arg('$loginRedirectUrl', '%happycode_tenzero_auth.login_redirect_url%')
        ->arg('$enableRegister', '%happycode_tenzero_auth.enable_register%')
        ->arg('$registerFields', '%happycode_tenzero_auth.register_fields%')
        ->arg('$resetPasswordLinkTtl', '%happycode_tenzero_auth.reset_password_link_ttl%')
        ->arg('$apiRoutePath', '%happycode_tenzero_auth.api_route_path%');
    $services->alias('happycode.tenzero_config_service', ConfigService::class)
        ->public();

    // Ensure TenZeroUser identifier field is initialized before the firewall runs
    $services->set(IdentifierFieldInitializerSubscriber::class)
        ->arg('$userField', '%happycode_tenzero_auth.user_field%');
    $services->set(IdentifierFieldMappingValidatorSubscriber::class)
        ->arg('$registry', service('doctrine')->nullOnInvalid())
        ->arg('$logger', service('logger')->nullOnInvalid())
        ->arg('$userClass', '%happycode_tenzero_auth.user_class%')
        ->arg('$userField', '%happycode_tenzero_auth.user_field%')
        ->arg('$debug', '%kernel.debug%')
        ->arg('$apiRoutePath', '%happycode_tenzero_auth.api_route_path%');
    $services->set(JwtPrerequisiteSubscriber::class)
        ->arg('$secretKeyPath', '%env(resolve:JWT_SECRET_KEY)%')
        ->arg('$publicKeyPath', '%env(resolve:JWT_PUBLIC_KEY)%')
        ->arg('$passPhrase', '%env(JWT_PASSPHRASE)%')
        ->arg('$apiRoutePath', '%happycode_tenzero_auth.api_route_path%')
        ->arg('$debug', '%kernel.debug%')
        ->arg('$logger', service('logger')->nullOnInvalid());
    $services->set(JwtAuthSuccessSubscriber::class)
        ->arg('$tokenTtl', '%happycode_tenzero_auth.token_ttl%');

    // TenZeroSecurityService
    $services->set(TenZeroSecurityService::class);
    $services->alias('happycode.tenzero_security_service', TenZeroSecurityService::class)
        ->public();

    // TenZeroUserService
    $services->set(UserService::class);
    $services->alias('happycode.tenzero_user_service', UserService::class)
        ->public();

    $services->set(TenZeroAuthRouteLoader::class)
        ->tag('routing.loader');

    // Register controllers in this bundle as services and bind scalar param for autowiring
    $services->load('Happycode\\TenZeroAuth\\Controller\\', __DIR__.'/../src/Controller/*')
        ->private();

    $services->set(JwtOrLoginEntryPoint::class)
        ->arg('$apiRoutePath', '%happycode_tenzero_auth.api_route_path%');
};
