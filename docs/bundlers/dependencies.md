# Dependency bundler

🖥️ Console command: [`composer bundle-dependencies`](../cli.md#bundle-dependencies)\
🧩 Implementation: [`DependencyBundler`](../../src/Bundler/DependencyBundler.php)

## Description

Bundles dependency information of shipped vendor libraries. Uses the standardized
[CycloneDX](https://cyclonedx.org/) format to generate a
[Software Bill of Materials (SBOM)](https://en.wikipedia.org/wiki/Software_supply_chain).

## How it works

> [!NOTE]
> This bundler supports [automatic dependency extraction](../extract.md).

Extracts shipped vendor libraries from `composer.json` file within path to vendor
libraries (e.g. `Resources/Private/Libs/composer.json`) and builds a dedicated SBOM
file in CycloneDX format. The SBOM file is dumped in JSON or XML format and is by
default located next to the original `composer.json` file within the path to vendor
libraries, e.g. `Resources/Private/Libs/sbom.json`.

The configured path to vendor libraries and the relative path to the generated SBOM
file will be written as `extra` properties to the root `composer.json` file like follows:

```json
{
    "extra": {
        "typo3/cms": {
            "vendor-libraries": {
                "root-path": "Resources/Private/Libs",
                "sbom-file": "Resources/Private/Libs/sbom.json"
            }
        }
    }
}
```

## Configuration options

The bundler's behavior can be controlled in various ways:

* By using the [`dependencies`](../schema.md#dependencies) section within a
  configuration file.
* By passing appropriate console [command options](../cli.md#bundle-dependencies) to
  the `bundle-dependencies` command.

## Example

Given the following `Resources/Private/Libs/composer.json` file:

```json
{
    "name": "eliashaeussler/test-extension-libs",
    "require": {
        "eliashaeussler/cache-warmup": "5.0.2",
        "eliashaeussler/sse": "2.0.0"
    }
}
```

When executing the dependency bundler, it will look up package information for all
defined vendor libraries and dump it to a serialized `sbom.json` (or `sbom.xml`) file:

```json
{
    "$schema": "http://cyclonedx.org/schema/bom-1.7.schema.json",
    "bomFormat": "CycloneDX",
    "specVersion": "1.7",
    "version": 1,
    "metadata": {
        "component": {
            "bom-ref": "eliashaeussler/test-extension-1.0.0.0",
            "type": "library",
            "name": "test-extension",
            "version": "1.0.0",
            "group": "eliashaeussler",
            "purl": "pkg:composer/eliashaeussler/test-extension@1.0.0",
            "properties": [
                {
                    "name": "cdx:composer:package:type",
                    "value": "typo3-cms-extension"
                }
            ]
        }
    },
    "components": [
        {
            "bom-ref": "cuyz/valinor-2.3.1.0",
            "type": "library",
            "name": "valinor",
            "version": "2.3.1",
            "group": "cuyz",
            "description": "Library that helps to map any input into a strongly-typed value object structure.",
            "author": "Romain Canon",
            "licenses": [
                {
                    "license": {
                        "id": "MIT",
                        "acknowledgement": "declared"
                    }
                }
            ],
            "purl": "pkg:composer/cuyz/valinor@2.3.1",
            "externalReferences": [
                {
                    "type": "distribution",
                    "url": "https://api.github.com/repos/CuyZ/Valinor/zipball/212835b2efb89becd9881f4836e9b0b32ea105bf",
                    "comment": "212835b2efb89becd9881f4836e9b0b32ea105bf"
                },
                {
                    "type": "vcs",
                    "url": "https://github.com/CuyZ/Valinor.git",
                    "comment": "212835b2efb89becd9881f4836e9b0b32ea105bf"
                },
                {
                    "type": "website",
                    "url": "https://github.com/CuyZ/Valinor"
                }
            ],
            "properties": [
                {
                    "name": "cdx:composer:package:distReference",
                    "value": "212835b2efb89becd9881f4836e9b0b32ea105bf"
                },
                {
                    "name": "cdx:composer:package:sourceReference",
                    "value": "212835b2efb89becd9881f4836e9b0b32ea105bf"
                },
                {
                    "name": "cdx:composer:package:type",
                    "value": "library"
                }
            ]
        },
        {
            "bom-ref": "eliashaeussler/cache-warmup-5.0.2.0",
            "type": "library",
            "name": "cache-warmup",
            "version": "5.0.2",
            "group": "eliashaeussler",
            "description": "Composer package to warm up website caches, based on a given XML sitemap",
            "author": "Elias Häußler",
            "licenses": [
                {
                    "license": {
                        "id": "GPL-3.0-or-later",
                        "acknowledgement": "declared"
                    }
                }
            ],
            "purl": "pkg:composer/eliashaeussler/cache-warmup@5.0.2",
            "externalReferences": [
                {
                    "type": "distribution",
                    "url": "https://api.github.com/repos/eliashaeussler/cache-warmup/zipball/1959aba4cd935ed6fd0e8a39c164045c988f85b6",
                    "comment": "1959aba4cd935ed6fd0e8a39c164045c988f85b6"
                },
                {
                    "type": "vcs",
                    "url": "https://github.com/eliashaeussler/cache-warmup.git",
                    "comment": "1959aba4cd935ed6fd0e8a39c164045c988f85b6"
                },
                {
                    "type": "website",
                    "url": "https://cache-warmup.dev/"
                }
            ],
            "properties": [
                {
                    "name": "cdx:composer:package:distReference",
                    "value": "1959aba4cd935ed6fd0e8a39c164045c988f85b6"
                },
                {
                    "name": "cdx:composer:package:sourceReference",
                    "value": "1959aba4cd935ed6fd0e8a39c164045c988f85b6"
                },
                {
                    "name": "cdx:composer:package:type",
                    "value": "library"
                }
            ]
        },
        {
            "bom-ref": "eliashaeussler/sse-2.0.0.0",
            "type": "library",
            "name": "sse",
            "version": "2.0.0",
            "group": "eliashaeussler",
            "description": "PHP implementation of server-sent events using event streams",
            "author": "Elias Häußler",
            "licenses": [
                {
                    "license": {
                        "id": "GPL-3.0-or-later",
                        "acknowledgement": "declared"
                    }
                }
            ],
            "purl": "pkg:composer/eliashaeussler/sse@2.0.0",
            "externalReferences": [
                {
                    "type": "distribution",
                    "url": "https://api.github.com/repos/eliashaeussler/sse/zipball/8921af4fb112f3fd2ec37856c9feb982af41199e",
                    "comment": "8921af4fb112f3fd2ec37856c9feb982af41199e"
                },
                {
                    "type": "vcs",
                    "url": "https://github.com/eliashaeussler/sse.git",
                    "comment": "8921af4fb112f3fd2ec37856c9feb982af41199e"
                }
            ],
            "properties": [
                {
                    "name": "cdx:composer:package:distReference",
                    "value": "8921af4fb112f3fd2ec37856c9feb982af41199e"
                },
                {
                    "name": "cdx:composer:package:sourceReference",
                    "value": "8921af4fb112f3fd2ec37856c9feb982af41199e"
                },
                {
                    "name": "cdx:composer:package:type",
                    "value": "library"
                }
            ]
        },
        // ...
    ],
    "dependencies": [
        {
            "ref": "cuyz/valinor-2.3.1.0"
        },
        {
            "ref": "eliashaeussler/cache-warmup-5.0.2.0",
            "dependsOn": [
                "cuyz/valinor-2.3.1.0"
            ]
        },
        {
            "ref": "eliashaeussler/sse-2.0.0.0",
            "dependsOn": [
                "php-http/discovery-1.20.0.0"
            ]
        },
        {
            "ref": "php-http/discovery-1.20.0.0"
        },
        {
            "ref": "eliashaeussler/test-extension-1.0.0.0"
        }
    ]
}
```
