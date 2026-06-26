<?php

declare(strict_types=1);

namespace BastienMesnil\SyliusRelayPointPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * @psalm-suppress UnusedVariable
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('bastien_mesnil_sylius_relay_point');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('nominatim')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('url')
                            ->defaultValue('https://nominatim.openstreetmap.org/search')
                            ->info('URL of your self-hosted Nominatim instance. Do not use the public nominatim.openstreetmap.org instance in production: its usage policy forbids SaaS-style usage.')
                        ->end()
                        ->scalarNode('secret')->defaultNull()->end()
                        ->scalarNode('user_agent')->defaultValue('SyliusRelayPointPlugin')->end()
                        ->scalarNode('contact_email')->defaultNull()->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
