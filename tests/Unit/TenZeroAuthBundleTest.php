<?php

declare(strict_types=1);

namespace Happycode\TenZeroAuth\Tests\Unit;

use Happycode\TenZeroAuth\TenZeroAuthBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class TenZeroAuthBundleTest extends TestCase
{
    public function testBundleIsInertWhenUserConfigIsMissing(): void
    {
        $bundle = new TenZeroAuthBundle();
        $builder = new ContainerBuilder();
        $instanceof = [];
        $loader = new PhpFileLoader($builder, new FileLocator([__DIR__]));
        $container = new ContainerConfigurator($builder, $loader, $instanceof, __FILE__, __FILE__);

        $bundle->prependExtension($container, $builder);

        $this->assertSame([], $builder->getExtensionConfig('security'));
        $this->assertSame([], $builder->getExtensionConfig('lexik_jwt_authentication'));
        $this->assertSame([], $builder->getExtensionConfig('twig'));
        $this->assertSame([], $builder->getExtensionConfig('framework'));
        $this->assertFalse($builder->hasParameter('happycode_tenzero_auth.user_class'));
        $this->assertFalse($builder->hasParameter('happycode_tenzero_auth.user_field'));

        $bundle->loadExtension(['user_class' => \stdClass::class], $container, $builder);

        $this->assertSame([], $builder->getDefinitions());
        $this->assertFalse($builder->hasParameter('happycode_tenzero_auth.user_class'));
        $this->assertFalse($builder->hasParameter('happycode_tenzero_auth.user_field'));
    }
}
