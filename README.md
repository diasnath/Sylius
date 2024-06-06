# PayZen for Sylius

PayZen for Sylius is an open source plugin that links e-commerce websites based on Sylius to PayZen secure payment gateway developed by [Lyra Network](https://www.lyra.com/).

## Installation & Upgrade

### With Composer
- Require the plugin with composer
```
composer require lyranetwork\sylius-lyranetwork-plugin dev-payzen
```
### With module zip file
- Unzip module in your Sylius root folder.
- Add in file `sylius/composer.json`, in autoload psr-4 the line:
```
"Lyranetwork\\Payzen\\": "LyranetworkPayzen/src/"
```

### Add module to bundles
- Add the following line in  __bundles.php__  file located in `sylius/config/`:
```
Lyranetwork\Payzen\LyranetworkPayzenPlugin::class => ['all' => true],
```

- Add Payzen routes in `config/routes.yaml`

 ```yaml
 sylius_payzen:
    resource: "@LyranetworkPayzenPlugin/Resources/config/routing.yaml"
 ```

- Open command line in Sylius root directory, and run the following commands to extract the translations for the plugin:

```
composer dump-autoload

php bin/console translation:extract en LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract fr LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract es LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract de LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract pt LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract br LyranetworkPayzenPlugin --dump-messages
```
**Careful**

- Add the overrode templates. If you have already overrode one of the following files, you need to merge it with ours. You will find them in LyranetworkPayzen/Resources/views/bundles/ directories.

```
SyliusAdminBundle\PaymentMethod\_form.html.twig
SyliusAdminBundle\OrderShow\_payment.html.twig
SyliusShopBundle\Checkout\SelectPayment\_choice.html.twig
SyliusUiBundle\Form\theme.html.twig
```

- If not, just copy them with the following command if you used the zip method to install:
```
cp -R LyranetworkPayzen/Resources/views/bundles/* templates/bundles/
```
- Or this one if you used composer :
```
cp -R vendor/lyranetwork/sylius-lyranetwork-plugin/LyranetworkPayzen/Resources/views/bundles/* templates/bundles/
```

- Empty the cache with the following command:
```
php bin/console cache:clear
```

The plugin should be now available in the list of payment methods that you can create.

## Configuration
In the Sylius administration interface:
- Go to `Configuration > Payment methods`.
- Click on `Create` button on the right top of the page to display the list of available payment methods.
- Choose `Payment by PayZen` to add and configure it.
- You can now enter your PayZen credentials and configure your payment method. 
- Don't forget to give your payment method a code, to set the name in the language sections at the bottom and to save by clicking the `Create` button.

## Uninstallation

### With composer
```
composer remove lyranetwork/sylius-lyranetwork-plugin
```

### With module zip file
- Delete LyranetworkPayzen folder in your root Sylius folder
- Remove in file `sylius/composer.json`, in autoload psr-4 the line:
```
"Lyranetwork\\Payzen\\": "LyranetworkPayzen/src/"
```

### Remove and revert changes
- Remove the following line in  __bundles.php__  file located in `sylius/config/`:
```
Lyranetwork\Payzen\LyranetworkPayzenPlugin::class => ['all' => true],
```

- Remove Payzen routes in `config/routes.yaml`
```yaml
 sylius_payzen:
    resource: "@LyranetworkPayzenPlugin/Resources/config/routing.yaml"
```

- Remove or unmerge all added template files in `templates/bundles/`
```
SyliusAdminBundle\PaymentMethod\_form.html.twig
SyliusAdminBundle\OrderShow\_payment.html.twig
SyliusShopBundle\Checkout\SelectPayment\_choice.html.twig
SyliusUiBundle\Form\theme.html.twig
```

- Open command line in Sylius root directory, and run the following commands:
```
composer dump-autoload
php bin/console cache:clear
```
## License

Each PayZen payment module source file included in this distribution is licensed under the The MIT License (MIT).

Please see LICENSE.txt for the full text of the MIT license. It is also available through the world-wide-web at this URL: https://opensource.org/licenses/mit-license.html.