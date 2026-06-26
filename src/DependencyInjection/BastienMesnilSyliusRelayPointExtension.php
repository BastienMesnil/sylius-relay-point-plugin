<?php

declare(strict_types=1);

namespace BastienMesnil\SyliusRelayPointPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

final class BastienMesnilSyliusRelayPointExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration($configs, $container), $configs);

        $container->setParameter('bastien_mesnil_sylius_relay_point.nominatim.url', $config['nominatim']['url']);
        $container->setParameter('bastien_mesnil_sylius_relay_point.nominatim.secret', $config['nominatim']['secret']);
        $container->setParameter('bastien_mesnil_sylius_relay_point.nominatim.user_agent', $config['nominatim']['user_agent']);
        $container->setParameter('bastien_mesnil_sylius_relay_point.nominatim.contact_email', $config['nominatim']['contact_email']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');
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
        return '@BastienMesnilSyliusRelayPointPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
