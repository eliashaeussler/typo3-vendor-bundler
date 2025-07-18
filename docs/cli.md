# Console commands

## [`bundle`](../src/Command/BundleCommand.php)

Executes all available bundlers in a single run.

```bash
composer bundle [-c|--config CONFIG]
```

Pass the following options to the console command:

### `-c|--config`

Path to [config file](config-file), defaults to auto-detection in current
working directory.

## [`bundle-autoload`](../src/Command/BundleAutoloadCommand.php)

Bundles autoloader for vendor libraries in `composer.json` or `ext_emconf.php`.

```bash
composer bundle-autoload \
    [<libs-dir>] \
    [-c|--config CONFIG] \
    [-a|--[no-]drop-composer-autoload] \
    [-t|--target-file TARGET-FILE] \
    [-m|--target-manifest TARGET-MANIFEST] \
    [-b|--[no-]backup-sources] \
    [-o|--[no-]overwrite]
```

Pass the following options to the console command:

### `<libs-dir>`

Absolute or relative path to `composer.json` where vendor libraries are managed.
This is usually a separate `composer.json` file which requires and prepares all
vendor libraries for use in classic mode.

> [!NOTE]
> If omitted, the `pathToVendorLibraries` option from the config file will be
> used instead. If no config file is available, the command will fail.

### `-c|--config`

Path to [config file](config-file), defaults to auto-detection in current
working directory.

### `-a|--[no-]drop-composer-autoload`

Define whether to drop the `autoload` section in `composer.json`. When enabled,
the section will be removed in order to let `ext_emconf.php` manage all autoload
parameters.

> [!IMPORTANT]
> This option is not available when using the `composer` target manifest. When
> using the `extEmConf` target manifest, you should always drop the `autoload`
> section from `composer.json`. Otherwise, TYPO3 won't read configured `autoload`
> configuration from `ext_emconf.php` in classic mode.

> [!NOTE]
> If omitted, the `autoload.dropComposerAutoload` option from the config file
> will be used instead.

### `t|--target-file`

File where to bundle final autoload configuration. This is usually the `composer.json`
file when using the `composer` target manifest or `ext_emconf.php` file when using
the `extEmConf` target manifest. You can also use a different file, especially for
debugging and testing purposes.

> [!NOTE]
> If omitted, the `autoload.target.file` option from the config file will be used instead.

### `-m|--target-manifest`

The manifest which decides how to dump bundled autoload configuration. Can be `composer`
(default) or `extEmConf`.

> [!NOTE]
> If omitted, the `autoload.target.manifest` option from the config file will be used
> instead.

### `-b|--[no-]backup-sources`

Define whether to backup source files (normally `composer.json` and `ext_emconf.php`).
When enabled, original contents of source files, which are to be modified, will be backed
up in a separate file. If no contents would be modified, no backup files will be
generated.

> [!NOTE]
> If omitted, the `autoload.backupSources` option from the config file will be used instead.

### `-o|--[no-]overwrite`

Force overwriting the given target file if it already exists.

> [!NOTE]
> If omitted, the `autoload.target.overwrite` option from the config file will be used instead.
> If `false` is configured, you will be asked whether the target file should be overwritten.
