# PHP API

## Bundle autoload configuration

```php
use EliasHaeussler\Typo3VendorBundler;

// Define package root path and libraries path
$rootPath = dirname(__DIR__);
$librariesPath = 'Resources/Private/Libs';

// Bundle autoload configuration
$autoloadBundler = new Typo3VendorBundler\Bundler\AutoloadBundler(
    $rootPath,
    $librariesPath,
    new \Symfony\Component\Console\Output\ConsoleOutput(),
);
$autoloadBundle = $autoloadBundler->bundle(
    Typo3VendorBundler\Config\AutoloadTarget::composer(),
);

// Display results
echo 'Autoload configuration was bundled and dumped to '.$autoloadBundle->filename();
```

## Bundle dependency information

```php
use EliasHaeussler\Typo3VendorBundler;

// Define package root path and libraries path
$rootPath = dirname(__DIR__);
$librariesPath = 'Resources/Private/Libs';

// Bundle dependency information
$dependencyBundler = new Typo3VendorBundler\Bundler\DependencyBundler(
    $rootPath,
    $librariesPath,
    new \Symfony\Component\Console\Output\ConsoleOutput(),
);
$dependenciesBundle = $dependencyBundler->bundle();

// Display results
echo 'Dependency information was bundled and dumped to '.$dependenciesBundle->sbomFile();
```
