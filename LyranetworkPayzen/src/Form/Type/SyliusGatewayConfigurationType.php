<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Sylius. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);

namespace Lyranetwork\Payzen\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;

use Lyranetwork\Payzen\Sdk\Tools as PayzenTools;
use Lyranetwork\Payzen\Repository\PaymentMethodRepositoryInterface;

final class SyliusGatewayConfigurationType extends AbstractType
{
    private $PREFIX = 'sylius_payzen_plugin.';
    public static $REST_FIELDS = 'payzen_rest_api_';
    public static $ADVANCED_FIELDS = 'payzen_advanced_options_';
    public static $PAYMENT_OPTIONS = 'payzen_payment_options_';

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        RouterInterface $router,
        RequestStack $requestStack
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $config = [];
        $methodId = $this->requestStack->getCurrentRequest()->get('id');
        if ($methodId) {
            $paymentMethod = $this->paymentMethodRepository->find($methodId);
            if ($paymentMethod) {
                $gatewayConfig = $paymentMethod->getGatewayConfig();
                if ($gatewayConfig) {
                    $config = $gatewayConfig->getConfig();
                }
            }
        }

        $builder
        ->add(self::$REST_FIELDS . 'site_id', TextType::class, [
            'label' => $this->PREFIX . 'ui.payzen_site_id.label',
            'data' => $config[self::$REST_FIELDS . 'site_id'] ?? PayzenTools::getDefault('SITE_ID'),
            'help' => $this->PREFIX . 'ui.payzen_site_id.helptext',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'mode', ChoiceType::class, [
            'label' => $this->PREFIX . 'ui.payzen_mode.label',
            'choices' => [
                $this->PREFIX . 'config.test' => 'TEST',
                $this->PREFIX . 'config.production' => 'PRODUCTION'
            ],
            'help' => $this->PREFIX . 'ui.payzen_mode.helptext',
            'data' => $config[self::$REST_FIELDS . 'mode'] ?? 'TEST',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'rest_check_url', TextType::class, [
            'label' => $this->PREFIX . 'ui.payzen_rest_check_url.label',
            'disabled' => true,
            'help' => $this->PREFIX . 'ui.payzen_rest_check_url.helptext',
            'required' => false,
            'data' => $this->router->generate('payzen_rest_ipn', [], UrlGenerator::ABSOLUTE_URL)
        ])
        ->add(self::$REST_FIELDS . 'private_test_key', PasswordType::class, [
            'label' => $this->PREFIX . 'ui.payzen_private_test_key.label',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'private_prod_key', PasswordType::class, [
            'label' => $this->PREFIX . 'ui.payzen_private_prod_key.label',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'rest_server_url', TextType::class, [
            'label' => $this->PREFIX . 'ui.payzen_rest_server_url.label',
            'data' => PayzenTools::getDefault('REST_URL'),
            'required' => false,
            'disabled' => true
        ])
        ->add(self::$REST_FIELDS . 'public_test_key', TextType::class, [
            'label' => $this->PREFIX . 'ui.payzen_public_test_key.label',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'public_prod_key', TextType::class, [
            'label' => $this->PREFIX . 'ui.payzen_public_prod_key.label',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'hmac_test_key', PasswordType::class, [
            'label' => $this->PREFIX . 'ui.payzen_hmac_test_key.label',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'hmac_prod_key', PasswordType::class, [
            'label' => $this->PREFIX . 'ui.payzen_hmac_prod_key.label',
            'required' => false
        ])
        ->add(self::$REST_FIELDS . 'js_client_url', TextType::class, [
            'label' => $this->PREFIX . 'ui.payzen_js_client_url.label',
            'data' => PayzenTools::getDefault('STATIC_URL'),
            'required' => false,
            'disabled' => true
        ])
        ->add(self::$ADVANCED_FIELDS . 'card_data_entry_mode', ChoiceType::class, [
            'label' => $this->PREFIX . 'ui.payzen_card_data_entry_mode.label',
            'choices' => [
                $this->PREFIX . 'config.smartform.mode_smartform' => 'MODE_SMARTFORM',
                $this->PREFIX . 'config.smartform.mode_smartform_ext_with_logos' => 'MODE_SMARTFORM_EXT_WITH_LOGOS',
                $this->PREFIX . 'config.smartform.mode_smartform_ext_without_logos' => 'MODE_SMARTFORM_EXT_WITHOUT_LOGOS'
            ],
            'help' => $this->PREFIX . 'ui.payzen_card_data_entry_mode.helptext',
            'data' => $config[self::$ADVANCED_FIELDS . 'card_data_entry_mode'] ?? 'MODE_SMARTFORM',
            'required' => false
        ])
        ->add(self::$ADVANCED_FIELDS . 'rest_popin_mode', CheckboxType::class, [
            'label' => $this->PREFIX . 'ui.payzen_rest_popin_mode.label',
            'help' => $this->PREFIX . 'ui.payzen_rest_popin_mode.helptext',
            'required' => false
        ])
        ->add(self::$ADVANCED_FIELDS . 'rest_theme', ChoiceType::class, [
            'label' => $this->PREFIX . 'ui.payzen_rest_theme.label',
            'choices' => [
                $this->PREFIX . 'config.theme.neon' => 'NEON',
                $this->PREFIX . 'config.theme.classic' => 'CLASSIC'
            ],
            'help' => $this->PREFIX . 'ui.payzen_rest_theme.helptext',
            'data' => $config[self::$ADVANCED_FIELDS . 'rest_theme'] ?? 'NEON',
            'required' => false
        ])
        ->add(self::$ADVANCED_FIELDS . 'rest_compact_mode', CheckboxType::class, [
            'label' => $this->PREFIX . 'ui.payzen_rest_compact_mode.label',
            'help' => $this->PREFIX . 'ui.payzen_rest_compact_mode.helptext',
            'required' => false
        ])
        ->add(self::$ADVANCED_FIELDS . 'rest_attempts', NumberType::class, [
            'label' => $this->PREFIX . 'ui.payzen_rest_attempts.label',
            'help' => $this->PREFIX . 'ui.payzen_rest_attempts.helptext',
            'required' => false
        ])
        ->add(self::$ADVANCED_FIELDS . 'oneclick_payment', CheckboxType::class, [
            'label' => $this->PREFIX . 'ui.payzen_oneclick_payment.label',
            'help' => $this->PREFIX . 'ui.payzen_oneclick_payment.helptext',
            'required' => false
        ])
        ->add(self::$PAYMENT_OPTIONS . 'capture_delay', NumberType::class, [
            'label' => $this->PREFIX . 'ui.payzen_capture_delay.label',
            'help' => $this->PREFIX . 'ui.payzen_capture_delay.helptext',
            'required' => false
        ])
        ->add(self::$PAYMENT_OPTIONS . 'validation_mode', ChoiceType::class, [
            'label' => $this->PREFIX . 'ui.payzen_validation_mode.label',
            'choices' => [
                $this->PREFIX . 'config.validation.backoffice' => '',
                $this->PREFIX . 'config.validation.automatic' => '0',
                $this->PREFIX . 'config.validation.manual' => '1'
            ],
            'help' => $this->PREFIX . 'ui.payzen_validation_mode.helptext',
            'data' => $config[self::$PAYMENT_OPTIONS . 'validation_mode'] ?? '',
            'required' => false
        ]);
    }
}