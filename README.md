<div align="center">

# TYPO3 Vendor Bundler

[![Coverage](https://img.shields.io/coverallsCoverage/github/eliashaeussler/typo3-vendor-bundler?logo=coveralls)](https://coveralls.io/github/eliashaeussler/typo3-vendor-bundler)
[![CGL](https://img.shields.io/github/actions/workflow/status/eliashaeussler/typo3-vendor-bundler/cgl.yaml?label=cgl&logo=github)](https://github.com/eliashaeussler/typo3-vendor-bundler/actions/workflows/cgl.yaml)
[![Tests](https://img.shields.io/github/actions/workflow/status/eliashaeussler/typo3-vendor-bundler/tests.yaml?label=tests&logo=github)](https://github.com/eliashaeussler/typo3-vendor-bundler/actions/workflows/tests.yaml)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/eliashaeussler/typo3-vendor-bundler/php?logo=php)](https://packagist.org/packages/eliashaeussler/typo3-vendor-bundler)

</div>

A Composer plugin to bundle vendor libraries of TYPO3 extensions for use
in [classic mode](https://docs.typo3.org/permalink/t3coreapi:classic-directory-structure).
It allows to easily prepare dependencies, which are not part of TYPO3's
bundled dependencies, in order to make TYPO3 extensions fully usable in
classic mode installations.

## 🚀 Features

* [**Autoload bundler**](docs/bundlers/autoload.md): Bundles autoload information
  from vendor libraries in root `composer.json` file. This enables deep integration
  of vendor libraries directly into TYPO3, e.g. for use with dependency injection.
* [**Dependency bundler**](docs/bundlers/dependencies.md): Bundles dependency
  information of shipped vendor libraries. Uses the standardized
  [CycloneDX](https://cyclonedx.org/) format to generate a
  [Software Bill of Materials (SBOM)](https://en.wikipedia.org/wiki/Software_supply_chain).
* [**Automatic dependency extraction**](docs/extract.md): Allows to automatically
  extract used vendor libraries from the root `composer.json` file of an extension.
* [**Various configuration options**](docs/config-file.md): Provides an extensive
  configuration system, which allows to customize several parts of the bundling
  system.
* **Composer integration**: Built as Composer plugin, the package provides a smooth
  integration in your Composer workflow. Bundlers can be executed directly by using
  dedicated Composer commands, e.g. `composer bundle` to execute all available bundlers.

## 🔥 Installation

[![Packagist](https://img.shields.io/packagist/v/eliashaeussler/typo3-vendor-bundler?label=version&logo=packagist)](https://packagist.org/packages/eliashaeussler/typo3-vendor-bundler)
[![Packagist Downloads](https://img.shields.io/packagist/dt/eliashaeussler/typo3-vendor-bundler?color=brightgreen)](https://packagist.org/packages/eliashaeussler/typo3-vendor-bundler)

```bash
composer require --dev eliashaeussler/typo3-vendor-bundler
```

## ⚡ Quickstart

Add a `typo3-vendor-bundler.yaml` config file:

```yaml
# typo3-vendor-bundler.yaml

autoload:
  target:
    file: 'composer.json'
    overwrite: true
  backupSources: false
  excludeFromClassMap:
    - 'vendor/composer/InstalledVersions.php'

dependencies:
  sbom:
    file: 'sbom.json'
    version: '1.7'
    includeDev: false
    overwrite: true

dependencyExtraction:
  enabled: true
  failOnProblems: true

pathToVendorLibraries: 'Resources/Private/Libs'
```

Execute the main bundler:

```bash
composer bundle
```

You can also execute a single bundler. Read more about available
[console commands](docs/cli.md).

> [!TIP]
> You can use the [`composer validate-bundler-config`](docs/cli.md#validate-bundler-config) command
> to validate your config file.

## 📝 Documentation

* Features
  * [Autoload bundler](docs/bundlers/autoload.md)
  * [Dependencies bundler](docs/bundlers/dependencies.md)
  * [Automatic dependency extraction](docs/extract.md)
* Usage
  * [Console commands](docs/cli.md)
  * [Continuous Integration](docs/ci.md)
  * [PHP API](docs/api.md)
* Configuration
  * [Config file](docs/config-file.md)
  * [Schema](docs/schema.md)

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 3.0 (or later)](LICENSE).
