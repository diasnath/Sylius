<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Lyra\DependencyInjection;

use Lyranetwork\Lyra\DependencyInjection\Configuration;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;

final class LyranetworkLyraExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../Resources/config'));

        $loader->load('services.xml');
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ConfigurationInterface
    {
        return new Configuration();
    }
}
