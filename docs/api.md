# PHP API

## Bundle autoload configuration

```php
use EliasHaeussler\Typo3VendorBundler;
use Symfony\Component\Console;

// Define package root path (this is normally the extension root path)
// and libraries path (path were bundled vendor libraries are stored)
$rootPath = dirname(__DIR__);
$librariesPath = 'Resources/Private/Libs';

// Bundle autoload configuration
$autoloadBundler = new Typo3VendorBundler\Bundler\AutoloadBundler(
    $rootPath,
    $librariesPath,
    new Console\Output\ConsoleOutput(),
);
$autoloadBundle = $autoloadBundler->bundle();

// Display results
echo 'Autoload configuration was bundled and dumped to '.$autoloadBundle->filename();
```

## Bundle dependency information

```php
use EliasHaeussler\Typo3VendorBundler;
use Symfony\Component\Console;

// Define package root path (this is normally the extension root path)
// and libraries path (path were bundled vendor libraries are stored)
$rootPath = dirname(__DIR__);
$librariesPath = 'Resources/Private/Libs';

// Bundle dependency information
$dependencyBundler = new Typo3VendorBundler\Bundler\DependencyBundler(
    $rootPath,
    $librariesPath,
    new Console\Output\ConsoleOutput(),
);
$dependenciesBundle = $dependencyBundler->bundle();

// Display results
echo 'Dependency information was bundled and dumped to '.$dependenciesBundle->sbomFile();
```

## Extract dependencies from `composer.json`

```php
use Composer\Factory;
use Composer\IO;
use EliasHaeussler\Typo3VendorBundler;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;

// Define package root path (this is normally the extension root path),
// root composer.json and vendor libraries composer.json filenames
$rootPath = dirname(__DIR__);
$rootComposerJson = $rootPath.'/composer.json';
$libsComposerJson = $rootPath.'/Resources/Private/Libs/composer.json';

// Create composer instance from composer.json
$composer = Factory::create(new IO\NullIO(), $rootComposerJson);

// Extract depencies from composer.json
$dependencyExtractor = new Typo3VendorBundler\Resource\DependencyExtractor();
$dependencySet = $dependencyExtractor->extract($composer);

// Dump dependencies to libs composer.json
$filesystem = new Filesystem\Filesystem();
$dependencySet->dumpToFile($libsComposerJson, $composer);

// Display results
echo 'Dependencies were extracted and dumped to '.$libsComposerJson;
```

> [!TIP]
> Read more at [Automatic dependency extraction](extract.md).
