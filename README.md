# PayZen for Sylius

PayZen for Sylius is an open source plugin that links e-commerce websites based on Sylius to PayZen secure payment gateway developed by [Lyra Network](https://www.lyra.com/).

## Installation & Upgrade

### With Composer
- Require the plugin with composer using the following command:

```
composer require lyranetwork/sylius-lyranetwork-plugin dev-payzen-v2
```
- Add the following line in  __bundles.php__  file located in `[sylius-root]/config/`:

```
Lyranetwork\Payzen\LyranetworkPayzenPlugin::class => ['all' => true],
```

- Add Payzen routes in  __routes.yaml__  file located in `[sylius-root]/config/`:

 ```yaml
 sylius_payzen:
    resource: "@LyranetworkPayzenPlugin/Resources/config/routing.yaml"
 ```

- Add Payzen config in ___sylius.yaml__  file located in `[sylius-root]/config/packages` :

```
imports:
[...]
    - { resource: "@LyranetworkPayzenPlugin/Resources/config/config.yaml" }
```

- Dump the autoload cache using the following command:

```
composer dump-autoload
```

- Empty the cache with the following command:

```
php bin/console cache:clear
```

The plugin should be now available in the list of payment methods that you can create.

### With plugin zip file
- Unzip module in your Sylius root folder.
- Add in file `[sylius-root]/composer.json`, in autoload psr-4 the following line:

```
"Lyranetwork\\Payzen\\": "LyranetworkPayzen/src/"
```
- Add the following line in  __bundles.php__  file located in `[sylius-root]/config/`:

```
Lyranetwork\Payzen\LyranetworkPayzenPlugin::class => ['all' => true],
```

- Add Payzen routes in  __routes.yaml__  file located in `[sylius-root]/config/`:

 ```yaml
 sylius_payzen:
    resource: "@LyranetworkPayzenPlugin/Resources/config/routing.yaml"
 ```

- Add Payzen config in ___sylius.yaml__  file located in `[sylius-root]/config/packages` :

```
imports:
[...]
    - { resource: "@LyranetworkPayzenPlugin/Resources/config/config.yaml" }
```

- Dump the autoload cache using the following command:

```
composer dump-autoload
```

- Open command line in Sylius root directory, and run the following commands to extract the translations for the plugin:

```
php bin/console translation:extract en LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract fr LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract es LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract de LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract pt LyranetworkPayzenPlugin --dump-messages
php bin/console translation:extract br LyranetworkPayzenPlugin --dump-messages
```

- Empty the cache with the following command:

```
php bin/console cache:clear
```

The plugin should be now available in the list of payment methods that you can create.

## Configuration
In the Sylius administration interface:
- Go to `Configuration > Payment methods`.
- Click on `Create` button on the top right of the page to display the list of available payment methods.
- Choose `Payment by PayZen` to add and configure it.
- You can now enter your PayZen credentials and configure your payment method. 
- Don't forget to give your payment method a code, to set the name in the language sections at the bottom and to save by clicking the `Create` button.

## Uninstallation

### With composer
```
composer remove lyranetwork/sylius-lyranetwork-plugin
```

### With module zip file
- Delete LyranetworkPayzen folder in your Sylius root folder
- Remove in file `sylius/composer.json`, in autoload psr-4 the line:

```
"Lyranetwork\\Payzen\\": "LyranetworkPayzen/src/"
```

### Remove and revert changes
- Remove the following line in  __bundles.php__  file located in `[sylius-root]/config/`:

```
Lyranetwork\Payzen\LyranetworkPayzenPlugin::class => ['all' => true],
```

- Remove Payzen routes in  __routes.yaml__  file located in `[sylius-root]/config/`

```yaml
 sylius_payzen:
    resource: "@LyranetworkPayzenPlugin/Resources/config/routing.yaml"
```

- Remove Payzen config in ___sylius.yaml__  file located in `[sylius-root]/config/packages` :

```
imports:
[...]
    - { resource: "@LyranetworkPayzenPlugin/Resources/config/config.yaml" }
```

- Open command line in Sylius root directory, and run the following commands:

```
composer dump-autoload
php bin/console cache:clear
```
## License

Each PayZen payment module source file included in this distribution is licensed under the The MIT License (MIT).

Please see LICENSE.txt for the full text of the MIT license. It is also available through the world-wide-web at this URL: https://opensource.org/licenses/mit-license.html.