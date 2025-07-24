<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Lyra\Twig\Extensions;

use Lyranetwork\Lyra\Sdk\Tools as LyraTools;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ConfigurationProvider extends AbstractExtension
{

    public function __construct(){}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('lyra_get_config_info', [$this, 'getConfigurationInformation'], ['is_safe' => ['html']]),
        ];
    }

    public function getConfigurationInformation(): array
    {
        $docsUrls = [];
        foreach (LyraApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[LyraTools::$doc_languages[$lang]] = $docUri . 'sylius/sitemap.html';
        }

        return [
            'lyraDocUrls' => $docsUrls,
            'lyraSupport' => LyraApi::formatSupportEmails(LyraTools::getDefault('SUPPORT_EMAIL')),
            'lyraPluginVersion' => LyraTools::getDefault('PLUGIN_VERSION'),
            'lyraGatewayVersion' => LyraTools::getDefault('GATEWAY_VERSION')
        ];
    }
}