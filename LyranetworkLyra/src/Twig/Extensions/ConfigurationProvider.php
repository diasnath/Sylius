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

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ConfigurationProvider extends AbstractExtension
{
    private TranslatorInterface $translator;

    private RequestStack $requestStack;

    public function __construct(
        TranslatorInterface $translator,
        RequestStack $requestStack
    )
    {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
    }

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
            $docsUrls[LyraTools::$doc_languages[$lang]] = $docUri . 'sylius2/sitemap.html';
        }

        return [
            'lyraDocUrls' => $docsUrls,
            'lyraSupport' => LyraApi::formatSupportEmails(LyraTools::getDefault('SUPPORT_EMAIL'), $this->translator->trans('sylius_lyra_plugin.ui.lyra_click_here', locale: $this->requestStack->getCurrentRequest()->get('admin_locale'))),
            'lyraPluginVersion' => LyraTools::getDefault('PLUGIN_VERSION'),
        ];
    }
}