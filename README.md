# Lyra Collect for Sylius

Lyra Collect for Sylius is an open source plugin that links e-commerce websites based on Sylius to Lyra Collect secure payment gateway developed by [Lyra Network](https://www.lyra.com/).

## Installation & Upgrade

- Unzip module in your Sylius root folder.
- Add the following line in  __bundles.php__  file located in `sylius/config/`:

```
Lyranetwork\Lyra\LyranetworkLyraPlugin::class => ['all' => true],
```
- Add in file `sylius/composer.json`, in autoload psr-4 the line:

```
"Lyranetwork\\Lyra\\": "LyranetworkLyra/src/"
```
- Add Lyra routes in `config/routes.yaml`

   ```yaml
   sylius_lyra:
      resource: "@LyranetworkLyraPlugin/Resources/config/routing.yaml"
   ```

- Open command line in Sylius root directory, and run the following commands:

```
composer dump-autoload

php bin/console translation:extract en LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract fr LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract es LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract de LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract pt LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract br LyranetworkLyraPlugin --dump-messages

cp -R LyranetworkLyra/Resources/views/bundles/* templates/bundles/

php bin/console cache:clear
```
The plugin should be now available in the list of payment methods that you can create. 

## Configuration
In the Sylius administration interface:
- Go to `Configuration > Payment methods`.
- Click on `Create` button on the right top of the page to display the list of available payment methods.
- Choose `Payment by Lyra Collect` to add and configure it.
- You can now enter your Lyra Collect credentials and configure your payment method. 
- Don't forget to give your payment method a code, to set the name in the language sections at the bottom and to save by clicking the `Create` button.

## Uninstallation

- Delete LyranetworkLyra folder in your root Sylius folder
- Remove the following line in  __bundles.php__  file located in `sylius/config/`:

```
Lyranetwork\Lyra\LyranetworkLyraPlugin::class => ['all' => true],
```
- Remove in file `sylius/composer.json`, in autoload psr-4 the line:

```
"Lyranetwork\\Lyra\\": "LyranetworkLyra/src/"
```
- Remove Lyra routes in `config/routes.yaml`

```yaml
 sylius_lyra:
    resource: "@LyranetworkLyraPlugin/Resources/config/routing.yaml"
```
- Remove all added template files in `templates/bundles/`

```
SyliusAdminBundle\PaymentMethod\_form.html.twig
SyliusAdminBundle\PaymentMethod\_lyraMessage.html.twig
SyliusShopBundle\Checkout\SelectPayment\_choice.html.twig
SyliusShopBundle\Checkout\SelectPayment\_lyraSmartform.html.twig
SyliusUiBundle\Form\theme.html.twig
```
- Open command line in Sylius root directory, and run the following commands:

```
composer dump-autoload
php bin/console cache:clear
```
## License

Each Lyra Collect payment module source file included in this distribution is licensed under the The MIT License (MIT).

Please see LICENSE.txt for the full text of the MIT license. It is also available through the world-wide-web at this URL: https://opensource.org/licenses/mit-license.html.