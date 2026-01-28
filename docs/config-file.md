# Config file

> [!TIP]
> Check out the [schema](schema.md) to learn about all available
> config options.

When using the console commands, you can use a config file to
define several bundler options.

## Formats

The following file formats are supported currently:

* `json`
* `php`
* `yaml`, `yml`

### Configuration in PHP file

When using PHP files to provide configuration, make sure to:

1. either return an instance of [`Typo3VendorBundlerConfig`](../src/Config/Typo3VendorBundlerConfig.php)
2. or return a closure which returns an instance of
   [`Typo3VendorBundlerConfig`](../src/Config/Typo3VendorBundlerConfig.php).

Example:

```php
<?php

declare(strict_types=1);

use EliasHaeussler\Typo3VendorBundler;

return new Typo3VendorBundler\Config\Typo3VendorBundlerConfig(
    autoload: new Typo3VendorBundler\Config\AutoloadConfig(
        backupSources: true,
    ),
    dependencies: new Typo3VendorBundler\Config\DependenciesConfig(
        sbom: new Typo3VendorBundler\Config\Sbom(
            includeDev: false,
        ),
        backupSources: true,
    ),
    dependencyExtraction: new Typo3VendorBundler\Config\DependencyExtractionConfig(
        enabled: true,
    ),
    pathToVendorLibraries: 'Build/Libraries',
);
```

## Auto-detection

If no config file is explicitly configured, the config reader
tries to auto-detect its location. The following order is taken
into account during auto-detection:

1. `typo3-vendor-bundler.php`
2. `typo3-vendor-bundler.json`
3. `typo3-vendor-bundler.yaml`
4. `typo3-vendor-bundler.yml`
