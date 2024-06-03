<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Lyra\Sdk;

use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;

class Tools
{
    private static $GATEWAY_CODE = 'Lyra';
    private static $GATEWAY_NAME = 'Lyra Collect';
    private static $BACKOFFICE_NAME = 'Lyra Expert';
    private static $GATEWAY_URL = 'https://secure.lyra.com/vads-payment/';
    private static $SITE_ID = '12345678';
    private static $KEY_TEST = '1111111111111111';
    private static $KEY_PROD = '2222222222222222';
    private static $CTX_MODE = 'TEST';
    private static $SIGN_ALGO = 'SHA-256';
    private static $LANGUAGE = 'en';

    private static $CMS_IDENTIFIER = 'Sylius_1.12.x';
    private static $SUPPORT_EMAIL = 'support-ecommerce@lyra-collect.com';
    private static $PLUGIN_VERSION = '1.0.0';
    private static $GATEWAY_VERSION = 'V2';
    private static $REST_URL = 'https://api.lyra.com/api-payment/';
    private static $STATIC_URL = 'https://static.lyra.com/static/';

    public static $pluginFeatures = [
        'qualif' => false,
        'prodfaq' => false,
        'shatwo' => true,
        'smartform' => true
    ];

    public static $smartformModes = [
        'MODE_SMARTFORM',
        'MODE_SMARTFORM_EXT_WITH_LOGOS',
        'MODE_SMARTFORM_EXT_WITHOUT_LOGOS'
    ];

    public static $doc_languages = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español',
        'de' => 'Deutsch',
        'br' => 'Português',
        'pt' => 'Português'
        // Complete when other languages are managed.
    ];

    public static function getDefault($name)
    {
        if (! is_string($name)) {
            return '';
        }

        if (! isset(self::$$name)) {
            return '';
        }

        return self::$$name;
    }

    public static function getContrib()
    {
        return self::getDefault('CMS_IDENTIFIER') . '_' . self::getDefault('PLUGIN_VERSION') . '/' . constant('Sylius\Bundle\CoreBundle\SyliusCoreBundle::VERSION') . '/' . LyraApi::shortPhpVersion();
    }
}