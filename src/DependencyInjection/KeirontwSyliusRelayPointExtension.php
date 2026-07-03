<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\DependencyInjection;

use Keirontw\SyliusRelayPointPlugin\Geocoding\AddokProvider;
use Keirontw\SyliusRelayPointPlugin\Geocoding\GeocodingProviderInterface;
use Keirontw\SyliusRelayPointPlugin\Geocoding\GoogleMapsProvider;
use Keirontw\SyliusRelayPointPlugin\Geocoding\NominatimProvider;
use Keirontw\SyliusRelayPointPlugin\Geocoding\PhotonProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Chronopost\ChronopostProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Colissimo\ColissimoProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\ColisPrive\ColisPriveRelayProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Dhl\DhlProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Dpd\DpdProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\InPost\InPostProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\MondialRelay\MondialRelayProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Bpost\BpostProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Gls\GlsProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\PostNl\PostNlProvider;
use Keirontw\SyliusRelayPointPlugin\Provider\Packeta\PacketaProvider;
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
        $configuration = $this->getConfiguration($configs, $container);
        \assert($configuration !== null);
        $config = $this->processConfiguration($configuration, $configs);

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
        $this->collectRelayMethodCodes($container, $config['providers']);
    }

    /** @param array<string, array<string, mixed>> $providers */
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

        $colissimo = $providers['colissimo'];

        if ($colissimo['enabled'] ?? false) {
            $container->register('keirontw.relay_point.colissimo_provider', ColissimoProvider::class)
                ->addArgument($colissimo['account_number'])
                ->addArgument($colissimo['password'])
                ->addArgument($colissimo['shipping_method_codes'])
                ->addArgument(new Reference('logger'))
                ->addArgument($colissimo['filter_relay'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $inpost = $providers['inpost'];

        if ($inpost['enabled'] ?? false) {
            $container->register('keirontw.relay_point.inpost_provider', InPostProvider::class)
                ->addArgument(new Reference('http_client'))
                ->addArgument(new Reference('logger'))
                ->addArgument($inpost['shipping_method_codes'])
                ->addArgument($inpost['base_url'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $colisPrive = $providers['colis_prive'];

        if ($colisPrive['enabled'] ?? false) {
            $container->register('keirontw.relay_point.colis_prive_provider', ColisPriveRelayProvider::class)
                ->addArgument($colisPrive['login'])
                ->addArgument($colisPrive['password'])
                ->addArgument($colisPrive['shipping_method_codes'])
                ->addArgument(new Reference('logger'))
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $dpd = $providers['dpd'];

        if ($dpd['enabled'] ?? false) {
            $container->register('keirontw.relay_point.dpd_provider', DpdProvider::class)
                ->addArgument(new Reference('http_client'))
                ->addArgument(new Reference('logger'))
                ->addArgument($dpd['shipping_method_codes'])
                ->addArgument($dpd['api_key'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $dhl = $providers['dhl'];

        if ($dhl['enabled'] ?? false) {
            $container->register('keirontw.relay_point.dhl_provider', DhlProvider::class)
                ->addArgument(new Reference('http_client'))
                ->addArgument(new Reference('logger'))
                ->addArgument($dhl['shipping_method_codes'])
                ->addArgument($dhl['api_key'])
                ->addArgument($dhl['service_type'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $packeta = $providers['packeta'];

        if ($packeta['enabled'] ?? false) {
            $container->register('keirontw.relay_point.packeta_provider', PacketaProvider::class)
                ->addArgument(new Reference('http_client'))
                ->addArgument(new Reference('logger'))
                ->addArgument($packeta['shipping_method_codes'])
                ->addArgument($packeta['api_key'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $postNl = $providers['post_nl'];

        if ($postNl['enabled'] ?? false) {
            $container->register('keirontw.relay_point.post_nl_provider', PostNlProvider::class)
                ->addArgument(new Reference('http_client'))
                ->addArgument(new Reference('logger'))
                ->addArgument($postNl['shipping_method_codes'])
                ->addArgument($postNl['api_key'])
                ->addArgument($postNl['delivery_options'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $bpost = $providers['bpost'];

        if ($bpost['enabled'] ?? false) {
            $container->register('keirontw.relay_point.bpost_provider', BpostProvider::class)
                ->addArgument(new Reference('http_client'))
                ->addArgument(new Reference('logger'))
                ->addArgument($bpost['shipping_method_codes'])
                ->addArgument($bpost['api_key'])
                ->addArgument($bpost['base_url'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }

        $gls = $providers['gls'];

        if ($gls['enabled'] ?? false) {
            $container->register('keirontw.relay_point.gls_provider', GlsProvider::class)
                ->addArgument(new Reference('http_client'))
                ->addArgument(new Reference('logger'))
                ->addArgument($gls['shipping_method_codes'])
                ->addArgument($gls['username'])
                ->addArgument($gls['password'])
                ->addArgument($gls['base_url'])
                ->addTag('keirontw_sylius_relay_point.relay_point_provider')
                ->setPublic(false);
        }
    }

    /** @param array<string, array<string, mixed>> $providers */
    private function collectRelayMethodCodes(ContainerBuilder $container, array $providers): void
    {
        $codes = [];

        foreach ($providers as $providerConfig) {
            if (($providerConfig['enabled'] ?? false) && isset($providerConfig['shipping_method_codes'])) {
                foreach ($providerConfig['shipping_method_codes'] as $code) {
                    $codes[] = $code;
                }
            }
        }

        $container->setParameter('keirontw_sylius_relay_point.relay_method_codes', array_values(array_unique($codes)));
    }

    /** @param array<string, mixed> $geocoding */
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
            default => throw new \InvalidArgumentException(sprintf('Unknown geocoding provider "%s".', $provider)),
        };

        $container->setAlias(GeocodingProviderInterface::class, $serviceId)->setPublic(true);
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);

        if ($container->hasExtension('sylius_twig_hooks')) {
            $container->prependExtensionConfig('sylius_twig_hooks', [
                'hooks' => [
                    'sylius_shop.checkout.select_shipping' => [
                        'relay_point_picker' => [
                            'template' => '@KeirontwSyliusRelayPointPlugin/shop/hook/relay_picker.html.twig',
                            'priority' => 50,
                        ],
                    ],
                ],
            ]);
        }
    }

    protected function getMigrationsNamespace(): string
    {
        return 'DoctrineMigrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@KeirontwSyliusRelayPointPlugin/src/Migrations';
    }

    /** @return list<string> */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
