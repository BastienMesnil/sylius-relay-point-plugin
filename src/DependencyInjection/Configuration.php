<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('keirontw_sylius_relay_point');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('geocoding')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('provider')
                            ->values(['addok', 'nominatim', 'google_maps', 'photon', 'custom'])
                            ->defaultValue('addok')
                            ->info('addok: French BAN, free, no key needed (recommended for France). nominatim: self-hosted OSM, international. google_maps: commercial worldwide. photon: self-hosted OSM, lightweight. custom: bring your own GeocodingProviderInterface service alias.')
                        ->end()
                        ->arrayNode('addok')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('url')
                                    ->defaultValue('https://api-adresse.data.gouv.fr/search/')
                                    ->info('Public French government endpoint (no key needed) or your self-hosted Addok instance.')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('nominatim')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('url')
                                    ->defaultValue('https://nominatim.openstreetmap.org/search')
                                    ->info('URL of your self-hosted Nominatim instance. The public nominatim.openstreetmap.org forbids SaaS-style usage.')
                                ->end()
                                ->scalarNode('secret')->defaultNull()->end()
                                ->scalarNode('user_agent')->defaultValue('SyliusRelayPointPlugin')->end()
                                ->scalarNode('contact_email')->defaultNull()->end()
                            ->end()
                        ->end()
                        ->arrayNode('google_maps')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('api_key')
                                    ->defaultNull()
                                    ->info('Google Maps Geocoding API key. Required when provider is set to google_maps.')
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('photon')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('url')
                                    ->defaultNull()
                                    ->info('URL of your self-hosted Photon instance. Required when provider is set to photon.')
                                ->end()
                                ->scalarNode('lang')->defaultNull()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('providers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('mondial_relay')
                            ->canBeEnabled()
                            ->children()
                                ->scalarNode('account')
                                    ->isRequired()
                                    ->info('Mondial Relay Enseigne code (MONDIAL_RELAY_ACCOUNT).')
                                ->end()
                                ->scalarNode('password')
                                    ->isRequired()
                                    ->info('Mondial Relay private key (MONDIAL_RELAY_PASSWORD).')
                                ->end()
                                ->arrayNode('shipping_method_codes')
                                    ->scalarPrototype()->end()
                                    ->defaultValue([])
                                    ->info('Sylius shipping method codes this provider handles (e.g. mondial_relay_france, mondial_relay_belgium).')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
