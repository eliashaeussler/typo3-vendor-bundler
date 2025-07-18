# Schema

The config file must follow a given schema:

```yaml
autoload:
  dropComposerAutoload: true
  target:
    file: 'composer.json'
    manifest: 'composer'
    overwrite: false
  backupSources: false
  excludeFromClassMap:
    - 'vendor/composer/InstalledVersions.php'

# Path to composer.json where vendor libraries are managed
pathToVendorLibraries: 'Resources/Private/Libs'

# Relative (to config file) or absolute path to project root
rootPath: ../
```

> [!TIP]
> Have a look at the shipped [JSON schema](../res/typo3-vendor-bundler.schema.json).

## Autoload

| Property                        | Type    | Required | Description                                                                                |
|---------------------------------|---------|----------|--------------------------------------------------------------------------------------------|
| `autoload`                      | Object  | –        | Set of configuration options to respect when bundling autoload configuration.              |
| `autoload.dropComposerAutoload` | Boolean | –        | Define whether to drop `autoload` section in `composer.json`. Defaults to `true`.          |
| `autoload.target`               | Object  | –        | Set of configuration options related to the bundle target.                                 |
| `autoload.target.file`          | String  | –        | File where to bundle autoload configuration. Defaults to `composer.json`.                  |
| `autoload.target.manifest`      | String  | –        | Manifest which decides how to dump bundled autoload configuration. Defaults to `composer`. |
| `autoload.target.overwrite`     | Boolean | –        | Define whether to overwrite the target file, if it already exists. Defaults to `false`.    |
| `autoload.backupSources`        | Boolean | –        | Define whether to backup source files. Defaults to `false`.                                |
| `autoload.excludeFromClassMap`  | Array   | –        | List of files to exclude from vendor libraries class map.                                  |

## Path to vendor libraries

| Property                | Type    | Required | Description                                                                                                            |
|-------------------------|---------|----------|------------------------------------------------------------------------------------------------------------------------|
| `pathToVendorLibraries` | String  | –        | Absolute or relative path to `composer.json` where vendor libraries are managed. Defaults to `Resources/Private/Libs`. |

## Root path

| Property   | Type   | Required | Description                                                                                                                                                                                                                                         |
|------------|--------|----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `rootPath` | String | –        | Relative or absolute path to project root. This path will be used to calculate paths to configured files if they are configured as relative paths. If the root path is configured as relative path, it is calculated based on the config file path. |
