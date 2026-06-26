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

        return $treeBuilder;
    }
}
