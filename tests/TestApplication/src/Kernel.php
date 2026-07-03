<?php

declare(strict_types=1);

namespace Tests\Keirontw\SyliusRelayPointPlugin;

use FriendsOfBehat\SymfonyExtension\Bundle\FriendsOfBehatSymfonyExtensionBundle;
use Keirontw\SyliusRelayPointPlugin\KeirontwSyliusRelayPointPlugin;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal kernel for Behat integration tests.
 * Only loads what the relay point plugin actually needs: no Sylius store.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new KeirontwSyliusRelayPointPlugin();
        yield new FriendsOfBehatSymfonyExtensionBundle();
    }

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(dirname(__DIR__) . '/config/config.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@KeirontwSyliusRelayPointPlugin/config/routes/shop.yaml');
    }
}
