<?php

declare(strict_types=1);

namespace Keirontw\SyliusRelayPointPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('keirontw_sylius_relay_point');
        $rootNode = $treeBuilder->getRootNode();
        \assert($rootNode instanceof ArrayNodeDefinition);

        $rootNode->children()
            ->booleanNode('apply_relay_point_to_order')->defaultTrue()
        ;

        $rootNode->append($this->buildGeocodingNode());
        $rootNode->append($this->buildProvidersNode());

        return $treeBuilder;
    }

    private function buildGeocodingNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('geocoding');
        $node->addDefaultsIfNotSet();

        $node->children()
            ->enumNode('provider')
                ->values(['addok', 'nominatim', 'google_maps', 'photon', 'custom'])
                ->defaultValue('addok')
        ;

        $addok = new ArrayNodeDefinition('addok');
        $addok->addDefaultsIfNotSet();
        $addok->children()->scalarNode('url')->defaultValue('https://api-adresse.data.gouv.fr/search/');
        $node->append($addok);

        $nominatim = new ArrayNodeDefinition('nominatim');
        $nominatim->addDefaultsIfNotSet();
        $nominatim->children()->scalarNode('url')->defaultValue('https://nominatim.openstreetmap.org/search');
        $nominatim->children()->scalarNode('secret')->defaultNull();
        $nominatim->children()->scalarNode('user_agent')->defaultValue('SyliusRelayPointPlugin');
        $nominatim->children()->scalarNode('contact_email')->defaultNull();
        $node->append($nominatim);

        $googleMaps = new ArrayNodeDefinition('google_maps');
        $googleMaps->addDefaultsIfNotSet();
        $googleMaps->children()->scalarNode('api_key')->defaultNull();
        $node->append($googleMaps);

        $photon = new ArrayNodeDefinition('photon');
        $photon->addDefaultsIfNotSet();
        $photon->children()->scalarNode('url')->defaultNull();
        $photon->children()->scalarNode('lang')->defaultNull();
        $node->append($photon);

        return $node;
    }

    private function buildProvidersNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('providers');
        $node->addDefaultsIfNotSet();

        foreach ($this->carrierNodes() as $carrierNode) {
            $node->append($carrierNode);
        }

        return $node;
    }

    /** @return ArrayNodeDefinition[] */
    private function carrierNodes(): array
    {
        return [
            $this->buildMondialRelayNode(),
            $this->buildChronopostNode(),
            $this->buildShop2ShopNode(),
            $this->buildColissimoNode(),
            $this->buildInPostNode(),
            $this->buildColisPriveNode(),
            $this->buildDpdNode(),
            $this->buildDhlNode(),
            $this->buildPacketaNode(),
            $this->buildPostNlNode(),
            $this->buildBpostNode(),
            $this->buildGlsNode(),
        ];
    }

    private function addShippingMethodCodes(ArrayNodeDefinition $node): void
    {
        $codesNode = $node->children()->arrayNode('shipping_method_codes');
        $codesNode->scalarPrototype();
        $codesNode->defaultValue([]);
    }

    private function buildMondialRelayNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('mondial_relay');
        $node->canBeEnabled();
        $node->children()->scalarNode('account')->isRequired();
        $node->children()->scalarNode('password')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildChronopostNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('chronopost');
        $node->canBeEnabled();
        $node->children()->scalarNode('account')->isRequired();
        $node->children()->scalarNode('password')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildShop2ShopNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('shop2shop');
        $node->canBeEnabled();
        $node->children()->scalarNode('account')->isRequired();
        $node->children()->scalarNode('password')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildColissimoNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('colissimo');
        $node->canBeEnabled();
        $node->children()->scalarNode('account_number')->isRequired();
        $node->children()->scalarNode('password')->isRequired();
        $node->children()->enumNode('filter_relay')->values(['A', 'P', 'C'])->defaultValue('A');
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildInPostNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('inpost');
        $node->canBeEnabled();
        $node->children()->scalarNode('base_url')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildColisPriveNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('colis_prive');
        $node->canBeEnabled();
        $node->children()->scalarNode('login')->isRequired();
        $node->children()->scalarNode('password')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildDpdNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('dpd');
        $node->canBeEnabled();
        $node->children()->scalarNode('api_key')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildDhlNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('dhl');
        $node->canBeEnabled();
        $node->children()->scalarNode('api_key')->isRequired();
        $node->children()->enumNode('service_type')
            ->values(['parcel:pick-up', 'parcel:drop-off-easy'])
            ->defaultValue('parcel:pick-up')
        ;
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildPacketaNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('packeta');
        $node->canBeEnabled();
        $node->children()->scalarNode('api_key')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildPostNlNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('post_nl');
        $node->canBeEnabled();
        $node->children()->scalarNode('api_key')->isRequired();
        $node->children()->enumNode('delivery_options')->values(['PG', 'PA', 'PG_EX'])->defaultValue('PG');
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildBpostNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('bpost');
        $node->canBeEnabled();
        $node->children()->scalarNode('api_key')->isRequired();
        $node->children()->scalarNode('base_url')->isRequired();
        $this->addShippingMethodCodes($node);

        return $node;
    }

    private function buildGlsNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('gls');
        $node->canBeEnabled();
        $node->children()->scalarNode('username')->isRequired();
        $node->children()->scalarNode('password')->isRequired();
        $node->children()->scalarNode('base_url')
            ->defaultValue('https://shipit.gls-group.eu/backend/rs/parcelshop')
        ;
        $this->addShippingMethodCodes($node);

        return $node;
    }
}
