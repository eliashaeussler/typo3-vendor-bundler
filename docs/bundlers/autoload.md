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

The concrete behavior of this bundler depends on the required TYPO3 versions, as
defined in the root `composer.json` file.

### Modern: Extensions supporting TYPO3 >= 14.2 only

When using a version constraint like `"typo3/cms-core": "^14.3"`, the "modern" approach
automatically applies. It extracts all vendor libraries from the `composer.json` file
within path to vendor libraries, e.g. `Resources/Private/Libs/composer.json` and
dumps them together with the resolved vendor path to the root `composer.json` file –
along with the configured path to vendor libraries:

```json
{
    "extra": {
        "typo3/cms": {
            "Package": {
                "providesPackages": {
                    "foo/baz": "Resources/Private/Libs/vendor"
                }
            },
            "vendor-libraries": {
                "root-path": "Resources/Private/Libs"
            }
        }
    }
}
```

### Legacy: Extensions supporting TYPO3 < 14.2

As soon as the extension declares support for TYPO3 < 14.2, e.g. by using a constraint
like `"typo3/cms-core": "^13.4 || ^14.3"`, the "legacy" approach applies. It uses
Composer's native dependency management tools to **extract configured `autoload`
configurations** from both root `composer.json` file and `composer.json` file within
path to vendor libraries, e.g. `Resources/Private/Libs/composer.json`. Once class
maps, PSR-4 namespaces and autoload files are loaded, they are merged and dumped to
the root `composer.json` file. This makes all autoloaded classes and files available
to TYPO3's autoloader in classic mode.

The configured path to vendor libraries will be written as `extra` property to the
configured root `composer.json` file, along with the resolved vendor libraries with
an empty path (this avoids multiple autoload attempts by TYPO3):

```json
{
    "extra": {
        "typo3/cms": {
            "Package": {
                "providesPackages": {
                    "foo/baz": ""
                }
            },
            "vendor-libraries": {
                "root-path": "Resources/Private/Libs"
            }
        }
    }
}
```

> [!NOTE]
> Read more about the behavior of `providesPackages` in the
> [official documentation](https://docs.typo3.org/permalink/t3coreapi:ext-composer-json-property-provides-packages).

## Configuration options

The bundler's behavior can be controlled in various ways:

* By using the [`autoload`](../schema.md#autoload) section within a configuration file.
* By passing appropriate console [command options](../cli.md#bundle-autoload) to the
  `bundle-autoload` command.

## Example

### Modern approach

Given the following root `composer.json` file:

```json
{
    "name": "eliashaeussler/test-extension",
    "type": "typo3-cms-extension",
    "require": {
        "php": "^8.2",
        "eliashaeussler/cache-warmup": "^5.0",
        "eliashaeussler/sse": "^2.0",
        "typo3/cms-backend": "^14.3",
        "typo3/cms-core": "^14.3"
    },
    "autoload": {
        "psr-4": {
            "EliasHaeussler\\TestExtension\\": "Classes/"
        }
    },
    "extra": {
        "typo3/cms": {
            "extension-key": "test_extension"
        }
    }
}
```

When executing the autoload bundler, it will first use
[automatic dependency extraction](../extract.md) to extract the `eliashaeussler/cache-warmup`
and `eliashaeussler/sse` packages as vendor libraries. In the next step, the dumped
`Resources/Private/Libs/composer.json` file, which defines extracted vendor libraries,
will be used to install dependencies. Afterwards, the resolved vendor libraries will be
written into the `extra.typo3/cms.Package.providesPackages` section of the root
`composer.json` file:

```json
{
    "name": "eliashaeussler/test-extension",
    "type": "typo3-cms-extension",
    "require": {
        "php": "^8.2",
        "eliashaeussler/cache-warmup": "^5.0",
        "eliashaeussler/sse": "^2.0",
        "typo3/cms-backend": "^14.3",
        "typo3/cms-core": "^14.3"
    },
    "autoload": {
        "psr-4": {
            "EliasHaeussler\\TestExtension\\": "Classes/"
        }
    },
    "extra": {
        "typo3/cms": {
            "extension-key": "test_extension",
            "vendor-libraries": {
                "root-path": "Resources/Private/Libs"
            },
            "Package": {
                "providesPackages": {
                    "eliashaeussler/cache-warmup": "Resources/Private/Libs/vendor",
                    "eliashaeussler/sse": "Resources/Private/Libs/vendor"
                }
            }
        }
    }
}
```


### Legacy approach

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
    },
    "extra": {
        "typo3/cms": {
            "extension-key": "test_extension"
        }
    }
}
```

Once the bundler extracted and installed vendor libraries, it merged the resulting autoloads
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
            "EliasHaeussler\\TestExtension\\": ["Classes"],
            "EliasHaeussler\\CacheWarmup\\": ["Resources/Private/Libs/vendor/eliashaeussler/cache-warmup/src"],
            "EliasHaeussler\\SSE\\": ["Resources/Private/Libs/vendor/eliashaeussler/sse/src"],
            // ...
        }
    },
    "extra": {
        "typo3/cms": {
            "extension-key": "test_extension",
            "vendor-libraries": {
                "root-path": "Resources/Private/Libs"
            },
            "Package": {
                "providesPackages": {
                    "eliashaeussler/cache-warmup": "",
                    "eliashaeussler/sse": ""
                }
            }
        }
    }
}
```
