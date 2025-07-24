# Lyra Collect for Sylius

Lyra Collect for Sylius is an open source plugin that links e-commerce websites based on Sylius to Lyra Collect secure payment gateway developed by [Lyra Network](https://www.lyra.com/).

## Installation & Upgrade

### With Composer
- Require the plugin with composer using the following command:

```
composer require lyranetwork/sylius-lyranetwork-plugin dev-lyra-v2
```
- Add the following line in  __bundles.php__  file located in `[sylius-root]/config/`:

```
Lyranetwork\Lyra\LyranetworkLyraPlugin::class => ['all' => true],
```

- Add Lyra routes in  __routes.yaml__  file located in `[sylius-root]/config/`:

 ```yaml
 sylius_lyra:
    resource: "@LyranetworkLyraPlugin/Resources/config/routing.yaml"
 ```

- Add Lyra config in ___sylius.yaml__  file located in `[sylius-root]/config/packages` :

```
imports:
[...]
    - { resource: "@LyranetworkLyraPlugin/Resources/config/config.yaml" }
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
"Lyranetwork\\Lyra\\": "LyranetworkLyra/src/"
```
- Add the following line in  __bundles.php__  file located in `[sylius-root]/config/`:

```
Lyranetwork\Lyra\LyranetworkLyraPlugin::class => ['all' => true],
```

- Add Lyra routes in  __routes.yaml__  file located in `[sylius-root]/config/`:

 ```yaml
 sylius_lyra:
    resource: "@LyranetworkLyraPlugin/Resources/config/routing.yaml"
 ```

- Add Lyra config in ___sylius.yaml__  file located in `[sylius-root]/config/packages` :

```
imports:
[...]
    - { resource: "@LyranetworkLyraPlugin/Resources/config/config.yaml" }
```

- Dump the autoload cache using the following command:

```
composer dump-autoload
```

- Open command line in Sylius root directory, and run the following commands to extract the translations for the plugin:

```
php bin/console translation:extract en LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract fr LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract es LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract de LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract pt LyranetworkLyraPlugin --dump-messages
php bin/console translation:extract br LyranetworkLyraPlugin --dump-messages
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
- Choose `Payment by Lyra Collect` to add and configure it.
- You can now enter your Lyra Collect credentials and configure your payment method. 
- Don't forget to give your payment method a code, to set the name in the language sections at the bottom and to save by clicking the `Create` button.

## Uninstallation

### With composer
```
composer remove lyranetwork/sylius-lyranetwork-plugin
```

### With module zip file
- Delete LyranetworkLyra folder in your Sylius root folder
- Remove in file `sylius/composer.json`, in autoload psr-4 the line:

```
"Lyranetwork\\Lyra\\": "LyranetworkLyra/src/"
```

### Remove and revert changes
- Remove the following line in  __bundles.php__  file located in `[sylius-root]/config/`:

```
Lyranetwork\Lyra\LyranetworkLyraPlugin::class => ['all' => true],
```

- Remove Lyra routes in  __routes.yaml__  file located in `[sylius-root]/config/`

```yaml
 sylius_lyra:
    resource: "@LyranetworkLyraPlugin/Resources/config/routing.yaml"
```

- Remove Lyra config in ___sylius.yaml__  file located in `[sylius-root]/config/packages` :

```
imports:
[...]
    - { resource: "@LyranetworkLyraPlugin/Resources/config/config.yaml" }
```

- Open command line in Sylius root directory, and run the following commands:

```
composer dump-autoload
php bin/console cache:clear
```
## License

Each Lyra Collect payment module source file included in this distribution is licensed under the The MIT License (MIT).

Please see LICENSE.txt for the full text of the MIT license. It is also available through the world-wide-web at this URL: https://opensource.org/licenses/mit-license.html.