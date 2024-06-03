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

namespace Lyranetwork\Lyra\ContextProvider;

use Sylius\Bundle\UiBundle\ContextProvider\ContextProviderInterface;
use Sylius\Bundle\UiBundle\Registry\TemplateBlock;
use Sylius\Component\Customer\Context\CustomerContextInterface;

use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Sdk\Tools as LyraTools;

final class ConfigurationContextProvider implements ContextProviderInterface
{
    public function __construct()
    {
    }

    public function provide(array $templateContext, TemplateBlock $templateBlock): array
    {
        $docsUrls = [];
        foreach (LyraApi::getOnlineDocUri() as $lang => $docUri) {
            $docsUrls[LyraTools::$doc_languages[$lang]] = $docUri . 'sylius/sitemap.html';
        }

        $templateContext['lyraDocUrls'] = $docsUrls;
        $templateContext['lyraSupport'] = LyraTools::getDefault('SUPPORT_EMAIL');
        $templateContext['lyraPluginVersion'] = LyraTools::getDefault('PLUGIN_VERSION');
        $templateContext['lyraGatewayVersion'] = LyraTools::getDefault('GATEWAY_VERSION');

        return $templateContext;
    }

    public function supports(TemplateBlock $templateBlock): bool
    {
        return $templateBlock->getEventName() === "sylius.admin.create" || $templateBlock->getEventName() === "sylius.admin.update";
    }
}