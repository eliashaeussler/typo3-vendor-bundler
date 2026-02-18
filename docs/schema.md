# Schema

The config file must follow a given schema:

```yaml
autoload:
  enabled: true
  backup: false
  excludeFromClassMap:
    - 'vendor/composer/InstalledVersions.php'

dependencies:
  enabled: true
  sbom:
    file: 'sbom.json'
    version: '1.7'
    includeDev: false
    overwrite: true
  backup: false

dependencyExtraction:
  enabled: true
  failOnProblems: true

# Path to composer.json where vendor libraries are managed
pathToVendorLibraries: 'Resources/Private/Libs'

# Relative (to config file) or absolute path to project root
rootPath: ../
```

> [!TIP]
> Have a look at the shipped [JSON schema](../res/typo3-vendor-bundler.schema.json).

## Core options

| Property                                         | Type   | Default value               | Description                                                                                                                                                                                                                                         |
|--------------------------------------------------|--------|-----------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [`autoload`](#autoload)                          | Object | –                           | Set of configuration options to respect when bundling autoload configuration.                                                                                                                                                                       |
| [`dependencies`](#dependencies)                  | Object | –                           | Set of configuration options to respect when bundling dependency information.                                                                                                                                                                       |
| [`dependencyExtraction`](#dependency-extraction) | Object | –                           | Set of options used to configure automatic dependency extraction of vendor libraries from root `composer.json`.                                                                                                                                     |
| `pathToVendorLibraries`                          | String | `Resources/Private/Libs`    | Absolute or relative path to `composer.json` where vendor libraries are managed.                                                                                                                                                                    |
| `rootPath`                                       | String | *current working directory* | Relative or absolute path to project root. This path will be used to calculate paths to configured files if they are configured as relative paths. If the root path is configured as relative path, it is calculated based on the config file path. |

## Autoload

> [!TIP]
> Read more about [autoload bundling](bundlers/autoload.md).

| Property                       | Type    | Default value | Description                                                                                    |
|--------------------------------|---------|---------------|------------------------------------------------------------------------------------------------|
| `autoload.enabled`             | Boolean | `true`        | Define whether autoload bundling is enabled when executing [`composer bundle`](cli.md#bundle). |
| `autoload.backup`              | Boolean | `false`       | Define whether to backup the root `composer.json` file.                                        |
| `autoload.excludeFromClassMap` | Array   | –             | List of files to exclude from vendor libraries class map.                                      |

## Dependencies

> [!TIP]
> Read more about [dependency bundling](bundlers/dependencies.md).

| Property                       | Type    | Default value           | Description                                                                                                                         |
|--------------------------------|---------|-------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `dependencies.enabled`         | Boolean | `true`                  | Define whether dependency bundling is enabled when executing [`composer bundle`](cli.md#bundle).                                    |
| `dependencies.sbom`            | Object  | –                       | Set of configuration options used to define SBOM file generation.                                                                   |
| `dependencies.sbom.file`       | String  | `sbom.json`             | File where to write the serialized SBOM. Can be a JSON or XML file. Relative paths are resolved based on the vendor libraries path. |
| `dependencies.sbom.version`    | String  | `1.6` <!-- cdx-spec --> | CycloneDX BOM version to use.                                                                                                       |
| `dependencies.sbom.includeDev` | Boolean | `true`                  | Define whether to include development dependencies in the serialized SBOM.                                                          |
| `dependencies.sbom.overwrite`  | Boolean | `false`                 | Define whether to overwrite the SBOM file, if it already exists.                                                                    |
| `dependencies.backup`          | Boolean | `false`                 | Define whether to backup the root `composer.json` file.                                                                             |

## Dependency extraction

> [!TIP]
> Read more about [automatic dependency extraction](extract.md).

| Property                              | Type    | Default value | Description                                                        |
|---------------------------------------|---------|---------------|--------------------------------------------------------------------|
| `dependencyExtraction.enabled`        | Boolean | `true`        | Define whether automatic dependency extraction is enabled.         |
| `dependencyExtraction.failOnProblems` | Boolean | `false`       | Define whether extraction should fail if problems are encountered. |
