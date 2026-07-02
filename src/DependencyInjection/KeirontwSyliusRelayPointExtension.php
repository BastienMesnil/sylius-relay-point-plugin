<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\DependencyInjection;

use Keirontw\SyliusRelayPointPlugin\Geocoding\AddokProvider;
use Keirontw\SyliusRelayPointPlugin\Geocoding\GeocodingProviderInterface;
use Keirontw\SyliusRelayPointPlugin\Geocoding\GoogleMapsProvider;
use Keirontw\SyliusRelayPointPlugin\Geocoding\NominatimProvider;
use Keirontw\SyliusRelayPointPlugin\Geocoding\PhotonProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Chronopost\ChronopostProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\MondialRelay\MondialRelayProvider;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class KeirontwSyliusRelayPointExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $geocoding = $config['geocoding'];

        $container->setParameter('keirontw_sylius_relay_point.geocoding.addok.url', $geocoding['addok']['url']);
        $container->setParameter('keirontw_sylius_relay_point.geocoding.nominatim.url', $geocoding['nominatim']['url']);
        $container->setParameter('keirontw_sylius_relay_point.geocoding.nominatim.secret', $geocoding['nominatim']['secret']);
        $container->setParameter('keirontw_sylius_relay_point.geocoding.nominatim.user_agent', $geocoding['nominatim']['user_agent']);
        $container->setParameter('keirontw_sylius_relay_point.geocoding.nominatim.contact_email', $geocoding['nominatim']['contact_email']);
        $container->setParameter('keirontw_sylius_relay_point.geocoding.google_maps.api_key', $geocoding['google_maps']['api_key'] ?? '');
        $container->setParameter('keirontw_sylius_relay_point.geocoding.photon.url', $geocoding['photon']['url'] ?? '');
        $container->setParameter('keirontw_sylius_relay_point.geocoding.photon.lang', $geocoding['photon']['lang']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.xml');

        $this->wireGeocodingProvider($container, $geocoding['provider'], $geocoding);
        $this->wireCarrierProviders($container, $config['providers']);
    }

    private function wireCarrierProviders(ContainerBuilder $container, array $providers): void
    {
        $mondialRelay = $providers['mondial_relay'];

        if ($mondialRelay['enabled'] ?? false) {
            $container->register(MondialRelayProvider::class, MondialRelayProvider::class)
                ->addArgument($mondialRelay['account'])
                ->addArgument($mondialRelay['password'])
                ->addArgument($mondialRelay['shipping_method_codes'])
                ->addArgument(new Reference('logger'))
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $chronopost = $providers['chronopost'];

        if ($chronopost['enabled'] ?? false) {
            $container->register('keirontw.relay_point.chronopost_provider', ChronopostProvider::class)
                ->addArgument($chronopost['account'])
                ->addArgument($chronopost['password'])
                ->addArgument($chronopost['shipping_method_codes'])
                ->addArgument('chronopost')
                ->addArgument(new Reference('logger'))
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $shop2shop = $providers['shop2shop'];

        if ($shop2shop['enabled'] ?? false) {
            $container->register('keirontw.relay_point.shop2shop_provider', ChronopostProvider::class)
                ->addArgument($shop2shop['account'])
                ->addArgument($shop2shop['password'])
                ->addArgument($shop2shop['shipping_method_codes'])
                ->addArgument('shop2shop')
                ->addArgument(new Reference('logger'))
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }
    }

    private function wireGeocodingProvider(ContainerBuilder $container, string $provider, array $geocoding): void
    {
        if ($provider === 'custom') {
            return;
        }

        if ($provider === 'google_maps' && empty($geocoding['google_maps']['api_key'])) {
            throw new \InvalidArgumentException('keirontw_sylius_relay_point.geocoding.google_maps.api_key is required when provider is set to "google_maps".');
        }

        if ($provider === 'photon' && empty($geocoding['photon']['url'])) {
            throw new \InvalidArgumentException('keirontw_sylius_relay_point.geocoding.photon.url is required when provider is set to "photon".');
        }

        $serviceId = match ($provider) {
            'addok' => AddokProvider::class,
            'nominatim' => NominatimProvider::class,
            'google_maps' => GoogleMapsProvider::class,
            'photon' => PhotonProvider::class,
        };

        $container->setAlias(GeocodingProviderInterface::class, $serviceId)->setPublic(true);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'DoctrineMigrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@KeirontwSyliusRelayPointPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
