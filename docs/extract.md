# Automatic dependency extraction

When vendor libraries are being bundled for usage in classic mode installations,
there are several ways to define those dependencies:

1. **Manually** using a dedicated, separate `composer.json` file, located at the
   configured path to vendor libraries.
2. **Automatically** by extracting relevant dependencies from the root
   `composer.json` file.

The following guide explains how to configure automatic dependency extraction.

> [!IMPORTANT]
> Automatic dependency extraction is still considered **experimental**. Please
> report any issues you might encounter.

## Prerequisites

* Make sure all currently used vendor libraries are listed in the root `composer.json`
  file of your extension.
* Add all required TYPO3 framework packages (core extensions) to the `require` section
  of the root `composer.json` file, e.g. `typo3/cms-core`, `typo3/cms-backend` etc.
* Make sure to use package version constraints that satisfy all currently supported
  TYPO3 verisons.
* If vendor libraries are not available via Packagist, make sure to add appropriate
  [`repositories`](https://getcomposer.org/doc/04-schema.md#repositories) sections
  to the root `composer.json` file.

## Usage

The automatic dependency extraction feature is a shared component, which can either
be used as part of a bundler, or standalone by using the [PHP API](api.md#extract-dependencies-from-composerjson).

### Bundlers

The following bundlers currently have built-in support for automatic dependency
extraction:

* [Autoload bundler](bundlers/autoload.md)
* [Dependency bundler](bundlers/dependencies.md)

When using these bundlers, automatic dependency extraction is done by default, if no
separate `composer.json` file  exists in the path to vendor libraries.

To explicitly enable automatic dependency extraction, add the following configuration
to your `typo3-vendor-bundler.yaml` [configuration file](config-file.md):

```yaml
dependencyExtraction:
  enabled: true
```

You can also use the command option `--extract` for all supported commands, e.g.
`composer bundle-autoload --extract` or `composer bundle-dependencies --extract`.

> [!TIP]
> Read more about available [configuration options](schema.md#dependency-extraction)
> and [command options](cli.md).

### Standalone

Check out the example in [PHP API](api.md#extract-dependencies-from-composerjson) to learn
how to use the provided PHP API for automatic dependency extraction.

## How it works

The automatic dependency extraction feature uses Composer's native dependency
management tools to perform the following steps:

1. Extract direct dependencies from the root `composer.json` file.
2. Filter out platform requirements, TYPO3 framework packages, and other TYPO3
   extensions.
3. Handle remaining direct dependencies as vendor libraries and mark them as
   `require` requirements in the resulting `composer.json` file.
4. Collect transitive dependencies of required TYPO3 framework packages and mark
   them as `provide` requirements in the resulting `composer.json` file, if they
   are also required by any vendor library.
5. Find best matching version for collected vendor libraries and apply it as
   version constraint to the resulting `composer.json` file.

## Error Handling

During dependency extraction, problems may occur when dealing with package
resolving. Once extraction is done, these problems can be looked up. Bundlers
can also be configured to fail on extraction errors.

Add the following configuration to your `typo3-vendor-bundler.yaml` configuration
file, if bundling should fail on extraction errors:

```yaml
dependencyExtraction:
  failOnProblems: true
```

You can also use the command option `--fail` for all supported commands, e.g.
`composer bundle-autoload --fail` or `composer bundle-dependencies --fail`.

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
    }
}
```

The dependency extractor will generate the following `composer.json` file:

```json
{
    "name": "eliashaeussler/test-extension-libs",
    "require": {
        "eliashaeussler/cache-warmup": "5.0.2",
        "eliashaeussler/sse": "2.0.0"
    },
    "provide": {
        "guzzlehttp/guzzle": "*",
        "guzzlehttp/promises": "*",
        "guzzlehttp/psr7": "*",
        "psr/container": "*",
        "psr/event-dispatcher": "*",
        "psr/http-client": "*",
        "psr/http-factory": "*",
        "psr/http-message": "*",
        "psr/log": "*",
        "ralouphie/getallheaders": "*",
        "symfony/console": "*",
        "symfony/deprecation-contracts": "*",
        "symfony/event-dispatcher": "*",
        "symfony/event-dispatcher-contracts": "*",
        "symfony/filesystem": "*",
        "symfony/options-resolver": "*",
        "symfony/polyfill-ctype": "*",
        "symfony/polyfill-intl-grapheme": "*",
        "symfony/polyfill-intl-normalizer": "*",
        "symfony/polyfill-mbstring": "*",
        "symfony/service-contracts": "*",
        "symfony/string": "*",
        "symfony/yaml": "*"
    },
    "config": {
        "allow-plugins": false,
        "lock": false
    }
}
```

In the above example, the `eliashaeussler/cache-warmup` and `eliashaeussler/sse`
libraries were discovered as vendor libraries to be bundled, and are therefore
added to the `require` section of the resulting `composer.json` file. All
dependencies listed in the `provide` section are transitive dependencies of the
vendor libraries, but don't need to be bundled, because they are also transitive
dependencies of any required TYPO3 framework package.
