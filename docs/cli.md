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

Bundles autoloader for vendor libraries in `composer.json` file of
the extension.

```bash
composer bundle-autoload \
    [<libs-dir>] \
    [-c|--config CONFIG] \
    [-t|--target-file TARGET-FILE] \
    [-b|--[no-]backup-sources] \
    [-o|--[no-]overwrite] \
    [-x|--[no-]extract] \
    [--[no-]fail]
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

### `t|--target-file`

File where to bundle final autoload configuration. This is usually the root
`composer.json` file of the extension. You can also use a different file, especially for
debugging and testing purposes.

> [!NOTE]
> If omitted, the `autoload.target.file` option from the config file will be used instead.

### `-b|--[no-]backup-sources`

Define whether to backup source files (normally the root `composer.json` file of the extension).
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

### `-x|--[no-]extract`

Auto-detect and extract vendor libraries from root `composer.json`.

> [!NOTE]
> If omitted, the `autoload.dependencyExtraction.enabled` option from the config file will be
> used instead.

### `--[no-]fail`

Fail execution if dependency extraction finishes with problems.

> [!NOTE]
> If omitted, the `autoload.dependencyExtraction.failOnProblems` option from the config file
> will be used instead.

## [`bundle-dependencies`](../src/Command/BundleDependenciesCommand.php)

Bundles dependency information of vendor libraries as a serialized
[`Software Bill of Materials (SBOM)`](https://en.wikipedia.org/wiki/Software_supply_chain)
file. Uses [CycloneDX](https://cyclonedx.org/) as standardized format.

```bash
composer bundle-dependencies \
    [<libs-dir>] \
    [-c|--config CONFIG] \
    [-f|--sbom-file SBOM-FILE] \
    [-b|--sbom-version SBOM-VERSION] \
    [--[no-]dev] \
    [-o|--[no-]overwrite] \
    [-x|--[no-]extract] \
    [--[no-]fail]
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

### `f|--sbom-file`

File where to dump serialized SBOM. This can be a JSON or XML file like `sbom.json`
or `sbom.xml`.

> [!NOTE]
> If omitted, the `dependencies.sbom.file` option from the config file will be used instead.

### `-b|--sbom-version`

The CycloneDX BOM version to use. Must be an a supported version number, which
is available in the provided [`Version` enum](https://github.com/CycloneDX/cyclonedx-php-library/blob/master/src/Core/Spec/Version.php).

> [!NOTE]
> If omitted, the `dependencies.sbom.version` option from the config file will be used
> instead.

### `--[no-]dev`

Define whether to include development dependencies in the generated SBOM.

> [!NOTE]
> If omitted, the `dependencies.sbom.includeDev` option from the config file will be used instead.

### `-o|--[no-]overwrite`

Force overwriting the given SBOM file if it already exists.

> [!NOTE]
> If omitted, the `dependencies.sbom.overwrite` option from the config file will be used instead.
> If `false` is configured, you will be asked whether the target file should be overwritten.

### `-x|--[no-]extract`

Auto-detect and extract vendor libraries from root `composer.json`.

> [!NOTE]
> If omitted, the `autoload.dependencyExtraction.enabled` option from the config file will be
> used instead.

### `--[no-]fail`

Fail execution if dependency extraction finishes with problems.

> [!NOTE]
> If omitted, the `autoload.dependencyExtraction.failOnProblems` option from the config file
> will be used instead.

## [`validate-bundler-config`](../src/Command/ValidateBundlerConfigCommand.php)

Checks if the given bundler configuration is valid.

```bash
composer validate-bundler-config [-c|--config CONFIG]
```

Pass the following options to the console command:

### `-c|--config`

Path to [config file](config-file), defaults to auto-detection in current
working directory.
