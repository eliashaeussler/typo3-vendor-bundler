# Bundlers

The library provides various bundlers, each of which is intended for a specific purpose.
At the moment, the following bundlers are available:

* [Autoload bundler](autoload.md) _– Bundles autoload information from vendor libraries in root `composer.json` file_
* [Dependency bundler](dependencies.md) _– Bundles dependency information of shipped vendor libraries_

All bundlers can be executed independently of each other. Corresponding CLI commands and a
detailed PHP API are available for this purpose. It is also possible to call all available
and activated bundlers at once via the command line:

```bash
composer bundle
```

> [!TIP]
> Find out how to execute a single bundler by visiting the dedicated bundler docs (see above).
