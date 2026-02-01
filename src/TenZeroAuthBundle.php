<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Happycode\TenZeroAuth\Model\TenZeroUser;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class TenZeroAuthBundle extends AbstractBundle
{
    protected string $extensionAlias = 'happycode_tenzero_auth';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            DoctrineOrmMappingsPass::createXmlMappingDriver(
                [__DIR__.'/../config/doctrine/mapping' => 'Happycode\TenZeroAuth\Model']
            )
        );
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $rootNode = $definition->rootNode();
        $children = $rootNode->children();

                // user_class
        $this->stringNode($children, 'user_class')
                    ->info('The class that will be used to authenticate user.')
                    ->example('\App\Entity\User')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(fn ($v) => is_string($v) && '' !== $v && !class_exists($v))
                        ->thenInvalid('Configured user_class "%s" was not found or could not be autoloaded.')
                    ->end()
                    ->validate()
                        ->ifTrue(fn ($v) => is_string($v) && '' !== $v && !is_a($v, TenZeroUser::class, true))
                        ->thenInvalid('Configured user_class "%s" must extend Happycode\\TenZeroAuth\\Model\\TenZeroUser.')
                    ->end() // end user_class validate block
                ->end(); // end user_class block

                // user_field
        $this->stringNode($children, 'user_field')
                    ->info('The field that will be used to identify the user.')
                    ->example('username | email')
                    ->defaultNull()
                ->end(); // end user_field block

                // theme
        $this->stringNode($children, 'theme')
                    ->info('Optional theme class applied to the auth layout body.')
                    ->defaultValue('tz-theme-default')
                    ->cannotBeEmpty()
                ->end(); // end theme block
                // enable_register
        $children->booleanNode('enable_register')
                    ->info('Enable or disable the register route and UI.')
                    ->defaultTrue()
                ->end(); // end enable_register block
                // register_fields
        $children->arrayNode('register_fields')
                    ->info('Explicit allowlist of user fields permitted during registration.')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end(); // end register_fields block

                // app_name
        $this->stringNode($children, 'app_name')
                    ->info('Optional app name displayed in the auth layout.')
                    ->defaultValue('TenZero')
                    ->cannotBeEmpty()
                ->end(); // end app_name block

                // app_description
        $this->stringNode($children, 'app_description')
                    ->info('Optional app description displayed in the auth layout.')
                    ->defaultValue('A comprehensive business management platform designed to streamline your operations and boost productivity.')
                    ->cannotBeEmpty()
                ->end(); // end app_description block
                // access_control
        $children->arrayNode('access_control')
                    ->info('Access control rules, same structure as security.access_control. Overrides defaults by matching path or adds new rules.')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('path')->end()
                            ->variableNode('roles')->end()
                        ->end()
                    ->end()
                ->end(); // end access_control block
                // login_redirect_url
        $this->stringNode($children, 'login_redirect_url')
                    ->info('URL to redirect to after successful login.')
                    ->defaultValue('/')
                    ->cannotBeEmpty()
                ->end(); // end login_redirect_url block
                // logout_redirect_url
        $this->stringNode($children, 'logout_redirect_url')
                    ->info('URL to redirect to after logout.')
                    ->defaultValue('/login')
                    ->cannotBeEmpty()
                ->end(); // end logout_redirect_url block
                // token_ttl
        $children->integerNode('token_ttl')
                    ->info('JWT token lifetime in seconds.')
                    ->defaultValue(3600)
                ->end(); // end token_ttl block
                // reset_password_link_ttl
        $children->integerNode('reset_password_link_ttl')
                    ->info('Reset password link lifetime in seconds.')
                    ->defaultValue(86400)
                ->end(); // end reset_password_link_ttl block
                // api_route_path
        $this->stringNode($children, 'api_route_path')
                    ->info('Base API route path used for the API firewall (default: /api).')
                    ->defaultValue('/api')
                    ->cannotBeEmpty()
                ->end(); // end api_route_path block

        $children->end(); // end root children

        $rootNode->validate() // Validate the whole block
                ->ifTrue(static function (array $v): bool {
                    $userClass = $v['user_class'] ?? null;
                    $userField = $v['user_field'] ?? null;
                    if (!is_string($userClass) || '' === $userClass) {
                        return false;
                    }
                    if (!is_string($userField) || '' === $userField) {
                        return false;
                    }
                    if (!class_exists($userClass)) {
                        return false;
                    }
                    if (!is_a($userClass, TenZeroUser::class, true)) {
                        return false;
                    }
                    $getter = 'get'.ucfirst($userField);

                    return !method_exists($userClass, $getter)
                        && !\property_exists($userClass, $userField);
                })
                ->then(static function (array $v): array {
                    $userClass = (string) ($v['user_class'] ?? '');
                    $userField = (string) ($v['user_field'] ?? '');
                    $getter = 'get'.ucfirst($userField);
                    throw new InvalidConfigurationException(sprintf('Configured user_field "%s" is not valid for user_class "%s". Add %s() or a "%s" property.', $userField, $userClass, $getter, $userField));
                })
            ->end();
    }

    private function stringNode(NodeBuilder $builder, string $name): ScalarNodeDefinition
    {
        if (method_exists($builder, 'stringNode')) {
            return $builder->stringNode($name);
        }

        return $builder->scalarNode($name)
            ->validate()
                ->ifTrue(static fn ($v): bool => null !== $v && !is_string($v))
                ->thenInvalid(sprintf('Configuration "%s" must be a string.', $name))
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $userClass = $config['user_class'] ?? null;
        $userField = $config['user_field'] ?? null;
        if (!is_string($userClass) || '' === $userClass || !is_string($userField) || '' === $userField) {
            return;
        }

        // Use PHP-based service configuration to avoid requiring YAML in host apps
        $builder->setParameter('happycode_tenzero_auth.user_class', $userClass);
        $builder->setParameter('happycode_tenzero_auth.user_field', $userField);
        if (isset($config['theme'])) {
            $builder->setParameter('happycode_tenzero_auth.theme', $config['theme']);
        }
        if (isset($config['enable_register'])) {
            $builder->setParameter('happycode_tenzero_auth.enable_register', (bool) $config['enable_register']);
        }
        if (isset($config['register_fields'])) {
            $builder->setParameter('happycode_tenzero_auth.register_fields', (array) $config['register_fields']);
        }
        if (isset($config['app_name'])) {
            $builder->setParameter('happycode_tenzero_auth.app_name', $config['app_name']);
        }
        if (isset($config['app_description'])) {
            $builder->setParameter('happycode_tenzero_auth.app_description', $config['app_description']);
        }
        if (isset($config['login_redirect_url'])) {
            $builder->setParameter('happycode_tenzero_auth.login_redirect_url', $config['login_redirect_url']);
        }
        if (isset($config['logout_redirect_url'])) {
            $builder->setParameter('happycode_tenzero_auth.logout_redirect_url', $config['logout_redirect_url']);
        }
        if (isset($config['token_ttl'])) {
            $builder->setParameter('happycode_tenzero_auth.token_ttl', (int) $config['token_ttl']);
        }
        if (isset($config['reset_password_link_ttl'])) {
            $builder->setParameter('happycode_tenzero_auth.reset_password_link_ttl', (int) $config['reset_password_link_ttl']);
        }
        if (isset($config['api_route_path'])) {
            $builder->setParameter('happycode_tenzero_auth.api_route_path', (string) $config['api_route_path']);
        }
        $container->import(__DIR__.'/../config/services.php');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $bundleConfig = [];
        foreach ($builder->getExtensionConfig($this->extensionAlias) as $config) {
            $bundleConfig = array_replace($bundleConfig, $config);
        }
        $userClass = $bundleConfig['user_class'] ?? null;
        $userField = $bundleConfig['user_field'] ?? null;
        if (!is_string($userClass) || '' === $userClass || !is_string($userField) || '' === $userField) {
            return;
        }
        $theme = $bundleConfig['theme'] ?? 'tz-theme-default';
        $enableRegister = $bundleConfig['enable_register'] ?? true;
        $registerFields = (array) ($bundleConfig['register_fields'] ?? []);
        $appName = $bundleConfig['app_name'] ?? 'TenZero';
        $appDescription = $bundleConfig['app_description']
            ?? 'A comprehensive business management platform designed to streamline your operations and boost productivity.';
        $loginRedirectUrl = $bundleConfig['login_redirect_url'] ?? '/';
        $logoutRedirectUrl = $bundleConfig['logout_redirect_url'] ?? '/login';
        $tokenTtl = $bundleConfig['token_ttl'] ?? 3600;
        $resetPasswordLinkTtl = $bundleConfig['reset_password_link_ttl'] ?? 86400;
        $apiRoutePath = $bundleConfig['api_route_path'] ?? '/api';
        if (!is_string($apiRoutePath)) {
            $apiRoutePath = '/api';
        }
        $apiRoutePath = '/'.ltrim($apiRoutePath, '/');
        $apiRoutePath = rtrim($apiRoutePath, '/');
        if ('' === $apiRoutePath) {
            $apiRoutePath = '/api';
        }
        $apiSegment = ltrim($apiRoutePath, '/');
        $apiFirewallPattern = sprintf('^/(?:_tz/)?%s(?:/|$)', preg_quote($apiSegment, '#'));
        $apiAuthTokenPath = '/_tz'.$apiRoutePath.'/auth/token';
        $apiAuthTokenAccessPath = '^'.preg_quote($apiAuthTokenPath, '#').'$';
        $defaultAccessControl = [
            ['path' => '^/(_profiler|_wdt|assets|build)/', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => $apiAuthTokenAccessPath, 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/_tz/login$', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/_tz/logout$', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/_tz/forgot_password$', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/_tz/reset_password/.+$', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/', 'roles' => 'IS_AUTHENTICATED_FULLY'],
        ];
        if ($enableRegister) {
            array_splice($defaultAccessControl, 3, 0, [['path' => '^/_tz/register$', 'roles' => 'PUBLIC_ACCESS']]);
        }
        $accessControl = $defaultAccessControl;
        if (array_key_exists('access_control', $bundleConfig)) {
            $customAccessControl = (array) $bundleConfig['access_control'];
            foreach ($customAccessControl as $rule) {
                $rulePath = is_array($rule) ? ($rule['path'] ?? null) : null;
                if (!is_string($rulePath) || '' === $rulePath) {
                    continue;
                }
                if (!$enableRegister && '^/_tz/register$' === $rulePath) {
                    continue;
                }
                $replaced = false;
                foreach ($accessControl as $index => $existingRule) {
                    if (($existingRule['path'] ?? null) === $rulePath) {
                        $accessControl[$index] = $rule;
                        $replaced = true;
                        break;
                    }
                }
                if ($replaced) {
                    continue;
                }
                $catchAllIndex = null;
                foreach ($accessControl as $index => $existingRule) {
                    if (($existingRule['path'] ?? null) === '^/') {
                        $catchAllIndex = $index;
                        break;
                    }
                }
                if (null !== $catchAllIndex) {
                    array_splice($accessControl, $catchAllIndex, 0, [$rule]);
                } else {
                    $accessControl[] = $rule;
                }
            }
        }
        if (!$enableRegister) {
            $accessControl = array_values(array_filter(
                $accessControl,
                static fn (array $rule): bool => ($rule['path'] ?? null) !== '^/_tz/register$'
            ));
        }
        $builder->setParameter('happycode_tenzero_auth.user_class', $userClass);
        $builder->setParameter('happycode_tenzero_auth.user_field', $userField);
        if (is_string($theme) && '' !== $theme) {
            $builder->setParameter('happycode_tenzero_auth.theme', $theme);
        }
        $builder->setParameter('happycode_tenzero_auth.enable_register', (bool) $enableRegister);
        $builder->setParameter('happycode_tenzero_auth.register_fields', $registerFields);
        if (is_string($appName) && '' !== $appName) {
            $builder->setParameter('happycode_tenzero_auth.app_name', $appName);
        }
        if (is_string($appDescription) && '' !== $appDescription) {
            $builder->setParameter('happycode_tenzero_auth.app_description', $appDescription);
        }
        if (is_string($loginRedirectUrl) && '' !== $loginRedirectUrl) {
            $builder->setParameter('happycode_tenzero_auth.login_redirect_url', $loginRedirectUrl);
        }
        if (is_string($logoutRedirectUrl) && '' !== $logoutRedirectUrl) {
            $builder->setParameter('happycode_tenzero_auth.logout_redirect_url', $logoutRedirectUrl);
        }
        $builder->setParameter('happycode_tenzero_auth.token_ttl', (int) $tokenTtl);
        $builder->setParameter('happycode_tenzero_auth.reset_password_link_ttl', (int) $resetPasswordLinkTtl);
        $builder->setParameter('happycode_tenzero_auth.api_route_path', $apiRoutePath);

        // Expose this bundle's templates under the @TenZeroAuth namespace
        $container->extension('twig', [
            'paths' => [
                __DIR__.'/../templates' => 'TenZeroAuth',
            ],
            'globals' => [
                'tenzero_auth_theme' => '%happycode_tenzero_auth.theme%',
                'tenzero_auth_app_name' => '%happycode_tenzero_auth.app_name%',
                'tenzero_auth_app_description' => '%happycode_tenzero_auth.app_description%',
                'tenzero_auth_enable_register' => '%happycode_tenzero_auth.enable_register%',
            ],
        ]);

        // Register this bundle's assets with AssetMapper so CSS/JS can be referenced via asset()
        $container->extension('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/../assets' => 'tenzero-auth',
                ],
            ],
        ]);

        $builder->prependExtensionConfig('lexik_jwt_authentication', [
            'secret_key' => '%env(resolve:JWT_SECRET_KEY)%',
            'public_key' => '%env(resolve:JWT_PUBLIC_KEY)%',
            'pass_phrase' => '%env(JWT_PASSPHRASE)%',
            'token_ttl' => '%happycode_tenzero_auth.token_ttl%',
        ]);

        $builder->prependExtensionConfig('security', [
            'password_hashers' => [
                TenZeroUser::class => 'auto',
            ],
            'providers' => [
                'tenzero_user_provider' => [
                    'entity' => [
                        'class' => $userClass,
                        'property' => $userField,
                    ],
                ],
            ],
            'firewalls' => [
                'dev' => [
                    'pattern' => '^/(_profiler|_wdt|assets|build)/',
                    'security' => false,
                ],
                'api' => [
                    'pattern' => $apiFirewallPattern,
                    'stateless' => true,
                    'provider' => 'tenzero_user_provider',
                    'entry_point' => Security\JwtOrLoginEntryPoint::class,
                    'json_login' => [
                        'check_path' => $apiAuthTokenPath,
                        'username_path' => '%happycode_tenzero_auth.user_field%',
                        'password_path' => 'password',
                        'success_handler' => 'lexik_jwt_authentication.handler.authentication_success',
                        'failure_handler' => 'lexik_jwt_authentication.handler.authentication_failure',
                    ],
                    'jwt' => null,
                ],
                'web' => [
                    'pattern' => '^/',
                    'provider' => 'tenzero_user_provider',
                    'form_login' => [
                        'login_path' => '/_tz/login',
                        'check_path' => '/_tz/login',
                        'default_target_path' => '%happycode_tenzero_auth.login_redirect_url%',
                        'username_parameter' => '%happycode_tenzero_auth.user_field%',
                        'password_parameter' => 'password',
                        'enable_csrf' => true,
                        'csrf_token_id' => 'authenticate',
                    ],
                    'logout' => [
                        'path' => '/_tz/logout',
                        'target' => '%happycode_tenzero_auth.logout_redirect_url%',
                    ],
                ],
            ],
            'access_control' => $accessControl,
        ]);
    }
}
