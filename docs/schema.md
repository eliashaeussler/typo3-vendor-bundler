# Schema

The config file must follow a given schema:

```yaml
autoload:
  dropComposerAutoload: true
  targetFile: 'ext_emconf.php'
  backupSources: false

# Path to composer.json where vendor libraries are managed
pathToVendorLibraries: 'Resources/Private/Libs'

# Relative (to config file) or absolute path to project root
rootPath: ../
```

> [!TIP]
> Have a look at the shipped [JSON schema](../res/typo3-vendor-bundler.schema.json).

## Autoload

| Property                        | Type    | Required | Description                                                                       |
|---------------------------------|---------|----------|-----------------------------------------------------------------------------------|
| `autoload`                      | Object  | –        | Set of configuration options to respect when bundling autoload configuration.     |
| `autoload.dropComposerAutoload` | Boolean | –        | Define whether to drop `autoload` section in `composer.json`. Defaults to `true`. |
| `autoload.targetFile`           | String  | –        | File where to bundle autoload configuration. Defaults to `ext_emconf.php`.        |
| `autoload.backupSources`        | Boolean | –        | Define whether to backup source files. Defaults to `false`.                       |

## Path to vendor libraries

| Property                | Type    | Required | Description                                                                                                            |
|-------------------------|---------|----------|------------------------------------------------------------------------------------------------------------------------|
| `pathToVendorLibraries` | String  | –        | Absolute or relative path to `composer.json` where vendor libraries are managed. Defaults to `Resources/Private/Libs`. |

## Root path

| Property   | Type   | Required | Description                                                                                                                                                                                                                                         |
|------------|--------|----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `rootPath` | String | –        | Relative or absolute path to project root. This path will be used to calculate paths to configured files if they are configured as relative paths. If the root path is configured as relative path, it is calculated based on the config file path. |
