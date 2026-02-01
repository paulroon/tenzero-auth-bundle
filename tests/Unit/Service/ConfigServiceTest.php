<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Tests\Unit\Service;

use Happycode\TenZeroAuth\Model\TenZeroUser;
use Happycode\TenZeroAuth\Service\ConfigService;
use Happycode\TenZeroAuth\TenZeroAuthBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;

final class ConfigServiceTest extends TestCase
{
    public function testDefaultsAreAppliedForConfigService(): void
    {
        $config = $this->processConfig([
            'user_class' => TestUser::class,
            'user_field' => 'email',
        ]);

        $this->assertSame('tz-theme-default', $config['theme']);
        $this->assertTrue($config['enable_register']);
        $this->assertSame([], $config['register_fields']);
        $this->assertSame('/', $config['login_redirect_url']);
        $this->assertSame(86400, $config['reset_password_link_ttl']);
        $this->assertSame('/api', $config['api_route_path']);
    }

    public function testConfigServiceReturnsConfiguredValues(): void
    {
        $config = $this->processConfig([
            'user_class' => TestUser::class,
            'user_field' => 'email',
            'theme' => 'tz-theme-custom',
            'enable_register' => false,
            'register_fields' => ['email', 'firstName'],
            'login_redirect_url' => '/dashboard',
            'reset_password_link_ttl' => 7200,
            'api_route_path' => '/v1',
        ]);

        $service = new ConfigService(
            $config['user_class'],
            $config['user_field'],
            $config['theme'],
            $config['login_redirect_url'],
            $config['enable_register'],
            $config['register_fields'],
            $config['reset_password_link_ttl'],
            $config['api_route_path']
        );

        $this->assertSame(TestUser::class, $service->getUserClass());
        $this->assertSame('email', $service->getUserField());
        $this->assertSame('tz-theme-custom', $service->getTheme());
        $this->assertSame('/dashboard', $service->getLoginRedirectUrl());
        $this->assertFalse($service->isRegisterEnabled());
        $this->assertSame(['email', 'firstName'], $service->getRegisterFields());
        $this->assertSame(7200, $service->getResetPasswordLinkTtl());
        $this->assertSame('/v1', $service->getApiRoutePath());
    }

    public function testInvalidUserClassIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'user_class' => 'Missing\\UserClass',
            'user_field' => 'email',
        ]);
    }

    public function testNonTenZeroUserClassIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'user_class' => \stdClass::class,
            'user_field' => 'email',
        ]);
    }

    public function testInvalidUserFieldIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processConfig([
            'user_class' => TestUser::class,
            'user_field' => 'username',
        ]);
    }

    private function processConfig(array $input): array
    {
        $treeBuilder = new TreeBuilder('happycode_tenzero_auth');
        $loader = new DefinitionFileLoader($treeBuilder, new FileLocator([__DIR__]));
        $definition = new DefinitionConfigurator($treeBuilder, $loader, __FILE__, __FILE__);

        $bundle = new TenZeroAuthBundle();
        $bundle->configure($definition);

        $processor = new Processor();

        return $processor->process($treeBuilder->buildTree(), [$input]);
    }
}

final class TestUser extends TenZeroUser
{
    public string $email = 'user@example.com';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }
}
