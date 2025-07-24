<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Payzen\Twig\Extensions;

use Lyranetwork\Payzen\Sdk\Tools as PayzenTools;
use Lyranetwork\Payzen\Sdk\Form\Api as PayzenApi;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ConfigurationProvider extends AbstractExtension
{

    public function __construct(){}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('payzen_get_config_info', [$this, 'getConfigurationInformation'], ['is_safe' => ['html']]),
        ];
    }

    public function getConfigurationInformation(): array
    {
        $docsUrls = [];
        foreach (PayzenApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[PayzenTools::$doc_languages[$lang]] = $docUri . 'sylius/sitemap.html';
        }

        return [
            'payzenDocUrls' => $docsUrls,
            'payzenSupport' => PayzenApi::formatSupportEmails(PayzenTools::getDefault('SUPPORT_EMAIL')),
            'payzenPluginVersion' => PayzenTools::getDefault('PLUGIN_VERSION'),
            'payzenGatewayVersion' => PayzenTools::getDefault('GATEWAY_VERSION')
        ];
    }
}