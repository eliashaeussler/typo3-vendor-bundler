# Autoload bundler

🖥️ Console command: [`composer bundle-autoload`](../cli.md#bundle-autoload)\
🧩 Implementation: [`AutoloadBundler`](../../src/Bundler/AutoloadBundler.php)

## Description

Bundles autoload information from vendor libraries in root `composer.json` file.
This enables deep integration of vendor libraries directly into TYPO3, e.g. for use
with dependency injection.

## How it works

> [!NOTE]
> This bundler supports [automatic dependency extraction](../extract.md).

Uses Composer's native dependency management tools to **extract configured `autoload`
configurations** from both root `composer.json` file and `composer.json` file within
path to vendor libraries, e.g. `Resources/Private/Libs/composer.json`. In addition,
**class maps** from both Composer manifests are extracted by reading the appropriate
`vendor/composer/autoload_classmap.php` files.

Once class maps and PSR-4 root namespaces are loaded, they are merged and dumped to
the root `composer.json` file. This makes all autoloaded class available to TYPO3's
autoloader in classic mode.

## Configuration options

The bundler's behavior can be controlled in various ways:

* By using the [`autoload`](../schema.md#autoload) section within a configuration file.
* By passing appropriate console [command options](../cli.md#bundle-autoload) to the
  `bundle-autoload` command.

## Example

Given the following root `composer.json` file:

```json
{
    "name": "eliashaeussler/test-extension",
    "type": "typo3-cms-extension",
    "require": {
        "php": "^8.2",
        "eliashaeussler/cache-warmup": "^5.0",
        "eliashaeussler/sse": "^2.0",
        "typo3/cms-backend": "^13.4 || ^14.3",
        "typo3/cms-core": "^13.4 || ^14.3"
    },
    "autoload": {
        "psr-4": {
            "EliasHaeussler\\TestExtension\\": "Classes/"
        }
    }
}
```

When executing the autoload bundler, it will first use
[automatic dependency extraction](../extract.md) to extract the `eliashaeussler/cache-warmup`
and `eliashaeussler/sse` packages as vendor libraries. In the next step, the dumped
`Resources/Private/Libs/composer.json` file, which defines extracted vendor libraries,
will be used to install dependencies. Afterwards, the resulting classmap will be merged
into the `autoload` section of the root `composer.json` file:

```json
{
    "name": "eliashaeussler/test-extension",
    "type": "typo3-cms-extension",
    "require": {
        "php": "^8.2",
        "eliashaeussler/cache-warmup": "^5.0",
        "eliashaeussler/sse": "^2.0",
        "typo3/cms-backend": "^13.4 || ^14.3",
        "typo3/cms-core": "^13.4 || ^14.3"
    },
    "autoload": {
        "psr-4": {
            "EliasHaeussler\\TestExtension\\": "Classes/"
        },
        "classmap": [
            "Resources/Private/Libs/vendor/composer/InstalledVersions.php",
            "Resources/Private/Libs/vendor/eliashaeussler/cache-warmup/src/CacheWarmer.php",
            "Resources/Private/Libs/vendor/eliashaeussler/cache-warmup/src/Command/CacheWarmupCommand.php",
            "Resources/Private/Libs/vendor/eliashaeussler/sse/src/Event/Event.php",
            // ...
        ]
    }
}
```
