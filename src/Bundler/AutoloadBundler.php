<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "eliashaeussler/typo3-vendor-bundler".
 *
 * Copyright (C) 2025 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace EliasHaeussler\Typo3VendorBundler\Bundler;

use Composer\Factory;
use Composer\Installer;
use Composer\IO;
use EliasHaeussler\Typo3VendorBundler\Config;
use EliasHaeussler\Typo3VendorBundler\Console;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console as SymfonyConsole;
use Symfony\Component\Filesystem;
use Throwable;

use function array_key_exists;
use function array_shift;
use function array_values;
use function count;
use function file_exists;
use function is_array;
use function is_dir;
use function is_file;
use function reset;
use function sprintf;
use function var_export;

/**
 * AutoloadBundler.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @phpstan-type ExtEmConf array{
 *     autoload: array{
 *         classmap: list<string>,
 *         psr-4: array<string, string>,
 *     },
 * }
 */
final readonly class AutoloadBundler implements Bundler
{
    private Resource\ExtEmConfParser $extEmConfParser;
    private Filesystem\Filesystem $filesystem;
    private Console\Output\TaskRunner $taskRunner;
    private string $librariesPath;

    /**
     * @throws Exception\DirectoryDoesNotExist
     */
    public function __construct(
        private string $rootPath,
        string $librariesPath,
        private SymfonyConsole\Output\OutputInterface $output,
    ) {
        $this->extEmConfParser = new Resource\ExtEmConfParser();
        $this->filesystem = new Filesystem\Filesystem();
        $this->taskRunner = new Console\Output\TaskRunner($this->output);
        $this->librariesPath = Filesystem\Path::makeAbsolute($librariesPath, $this->rootPath);

        if (!is_dir($this->librariesPath)) {
            throw new Exception\DirectoryDoesNotExist($this->librariesPath);
        }
    }

    /**
     * @param list<non-empty-string> $excludeFromClassMap
     *
     * @throws Exception\FileAlreadyExists
     * @throws Throwable
     */
    public function bundle(
        Config\AutoloadTarget $target = new Config\AutoloadTarget(),
        bool $dropComposerAutoload = true,
        bool $backupSources = false,
        array $excludeFromClassMap = [],
    ): Entity\Autoload {
        $config = new Config\AutoloadConfig(
            $dropComposerAutoload,
            new Config\AutoloadTarget(
                Filesystem\Path::makeAbsolute($target->file(), $this->rootPath),
                $target->manifest(),
                $target->overwrite(),
            ),
            $backupSources,
            $excludeFromClassMap,
        );

        return match ($config->target()->manifest()) {
            Entity\Manifest::Composer => $this->bundleComposerManifest($config),
            Entity\Manifest::ExtEmConf => $this->bundleExtEmConfManifest($config),
        };
    }

    /**
     * @throws Exception\FileAlreadyExists
     * @throws Throwable
     */
    private function bundleComposerManifest(Config\AutoloadConfig $config): Entity\Autoload
    {
        // Build class maps
        $classMaps = [
            $this->loadRootComposerClassMap(),
            $this->loadVendorComposerClassMap($config->excludeFromClassMap()),
        ];

        // Create class map and PSR-4 namespaces
        $classMap = $this->mergeClassMaps($classMaps, $config->target()->file());
        $psr4Namespaces = $this->loadRootComposerPsr4Namespaces();
        $autoload = new Entity\Autoload($classMap, $psr4Namespaces, $config->target()->file(), $this->rootPath);

        // Throw exception if target file already exists
        if (true !== $config->target()->overwrite() && $this->filesystem->exists($config->target()->file())) {
            throw new Exception\FileAlreadyExists($config->target()->file());
        }

        // Create composer.json backup
        if (true === $config->backupSources()) {
            $this->taskRunner->run(
                '🦖 Backing up source files',
                function () use ($config) {
                    $composerJson = Filesystem\Path::join($this->rootPath, 'composer.json');

                    if ($config->target()->file() === $composerJson) {
                        $this->filesystem->copy($composerJson, $composerJson.'.bak');
                    }
                },
            );
        }

        // Create modified composer.json file contents
        $this->taskRunner->run(
            '🎊 Dumping merged autoload configuration',
            function () use ($autoload) {
                if (!is_file($autoload->filename())) {
                    $this->filesystem->dumpFile($autoload->filename(), '{}');
                }

                $composer = Factory::create(new IO\NullIO(), $autoload->filename());
                $configSource = $composer->getConfig()->getConfigSource();
                /* @phpstan-ignore argument.type */
                $configSource->addProperty('autoload', $autoload->toArray(true));
            },
        );

        return $autoload;
    }

    /**
     * @throws Exception\FileAlreadyExists
     * @throws Throwable
     */
    private function bundleExtEmConfManifest(Config\AutoloadConfig $config): Entity\Autoload
    {
        $declarationFile = Filesystem\Path::join($this->rootPath, 'ext_emconf.php');

        // Parse ext_emconf.php file
        $extEmConf = $this->taskRunner->run(
            '🔍 Parsing ext_emconf.php file',
            fn () => $this->parseExtEmConf($declarationFile),
            SymfonyConsole\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Load class maps
        $classMaps = [
            $this->loadRootComposerClassMap(),
            $this->loadVendorComposerClassMap($config->excludeFromClassMap()),
            $this->loadExtEmConfClassMap($declarationFile, $extEmConf),
        ];

        // Create class map and PSR-4 namespaces
        $classMap = $this->mergeClassMaps($classMaps, $config->target()->file());
        $psr4Namespaces = $this->mergePsr4Namespaces($declarationFile, $extEmConf, $config->target()->file());
        $autoload = new Entity\Autoload($classMap, $psr4Namespaces, $config->target()->file(), $this->rootPath);
        $extEmConf['autoload'] = $autoload->toArray(true);

        // Throw exception if target file already exists
        if (true !== $config->target()->overwrite() && $this->filesystem->exists($config->target()->file())) {
            throw new Exception\FileAlreadyExists($config->target()->file());
        }

        // Create ext_emconf.php backup
        if (true === $config->backupSources()) {
            $this->taskRunner->run(
                '🦖 Backing up source files',
                function () use ($config, $declarationFile) {
                    if ($config->target()->file() === $declarationFile) {
                        $this->filesystem->copy($declarationFile, $declarationFile.'.bak');
                    }

                    if (true === $config->dropComposerAutoload()) {
                        $composerJson = Filesystem\Path::join($this->rootPath, 'composer.json');

                        $this->filesystem->copy($composerJson, $composerJson.'.bak');
                    }
                },
            );
        }

        // Create modified ext_emconf.php file contents
        $this->taskRunner->run(
            '🎊 Dumping merged autoload configuration',
            function () use ($config, $extEmConf) {
                $extEmConfArray = var_export($extEmConf, true);
                $contents = <<<PHP
<?php

\$EM_CONF[\$_EXTKEY] = $extEmConfArray;
PHP;

                $this->filesystem->dumpFile($config->target()->file(), $contents);
            },
        );

        // Remove autoload section from root composer.json
        if (true === $config->dropComposerAutoload()) {
            $this->taskRunner->run(
                '✂️ Removing autoload section from composer.json',
                function () {
                    $composer = Factory::create(
                        new IO\NullIO(),
                        Filesystem\Path::join($this->rootPath, 'composer.json'),
                    );
                    $configSource = $composer->getConfig()->getConfigSource();
                    $configSource->removeProperty('autoload');
                },
            );
        }

        return $autoload;
    }

    /**
     * @param ExtEmConf $extEmConf
     *
     * @throws Throwable
     */
    private function loadExtEmConfClassMap(string $declarationFile, array $extEmConf): Entity\ClassMap
    {
        return $this->taskRunner->run(
            '🍄 Loading class map from ext_emconf.php',
            fn () => new Entity\ClassMap($extEmConf['autoload']['classmap'], $declarationFile, $this->rootPath),
            SymfonyConsole\Output\OutputInterface::VERBOSITY_VERBOSE,
        );
    }

    /**
     * @throws Throwable
     */
    private function loadRootComposerClassMap(): Entity\ClassMap
    {
        return $this->taskRunner->run(
            '🌱 Loading class map from root package',
            fn () => new Entity\ClassMap(
                $this->fetchAutoloadFromComposerManifest()['classmap'],
                Filesystem\Path::join($this->rootPath, 'composer.json'),
                $this->rootPath,
            ),
            SymfonyConsole\Output\OutputInterface::VERBOSITY_VERBOSE,
        );
    }

    /**
     * @param list<non-empty-string> $excludeFromClassMap
     *
     * @throws Throwable
     */
    private function loadVendorComposerClassMap(array $excludeFromClassMap = []): Entity\ClassMap
    {
        $classMap = $this->taskRunner->run(
            '🌱 Building class map from vendor libraries',
            function () {
                $io = new IO\BufferIO(verbosity: $this->output->getVerbosity());
                $composer = Factory::create(
                    $io,
                    Filesystem\Path::join($this->librariesPath, 'composer.json'),
                );

                $vendorDir = $composer->getConfig()->get('vendor-dir');
                $classMapFile = Filesystem\Path::join($vendorDir, 'composer', 'autoload_classmap.php');

                $installResult = Installer::create($io, $composer)
                    ->setClassMapAuthoritative(true)
                    ->setOptimizeAutoloader(true)
                    ->run();

                if (SymfonyConsole\Command\Command::SUCCESS !== $installResult) {
                    $this->output->writeln($io->getOutput(), SymfonyConsole\Output\OutputInterface::OUTPUT_RAW);

                    throw new Exception\CannotInstallComposerDependencies($this->librariesPath);
                }

                if (!file_exists($classMapFile)) {
                    throw new Exception\FileDoesNotExist($classMapFile);
                }

                $classMap = include $classMapFile;

                // Throw exception if configured class map is invalid
                if (!is_array($classMap)) {
                    throw new Exception\DeclarationFileIsInvalid($classMapFile);
                }

                /** @var list<string> $classMap */
                $classMap = array_values($classMap);

                return new Entity\ClassMap($classMap, $classMapFile, $this->rootPath);
            },
            SymfonyConsole\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Drop excluded files from class map
        if ([] !== $excludeFromClassMap) {
            foreach ($excludeFromClassMap as $path) {
                $fullPath = Filesystem\Path::join($this->librariesPath, $path);
                $classMap = $this->taskRunner->run(
                    sprintf('⛔ Removing "%s" from class map', $path),
                    function (bool &$successful) use ($classMap, $fullPath) {
                        if (!$classMap->has($fullPath)) {
                            $successful = false;

                            return $classMap;
                        }

                        return $classMap->remove($fullPath);
                    },
                    SymfonyConsole\Output\OutputInterface::VERBOSITY_VERBOSE,
                );
            }
        }

        return $classMap;
    }

    /**
     * @param non-empty-list<Entity\ClassMap> $classMaps
     *
     * @throws Throwable
     */
    private function mergeClassMaps(array $classMaps, string $targetFile): Entity\ClassMap
    {
        return $this->taskRunner->run(
            '♨️ Merging class maps',
            function () use ($classMaps, $targetFile) {
                $mergedClassMap = array_shift($classMaps);

                foreach ($classMaps as $classMap) {
                    $mergedClassMap = $mergedClassMap->merge($classMap, $targetFile);
                }

                return $mergedClassMap;
            },
        );
    }

    /**
     * @param ExtEmConf $extEmConf
     *
     * @throws Throwable
     */
    private function mergePsr4Namespaces(string $declarationFile, array $extEmConf, string $targetFile): Entity\Psr4Namespaces
    {
        // Load PSR-4 namespaces from ext_emconf.php
        $extEmConfNamespaces = $this->taskRunner->run(
            '🍄 Loading PSR-4 namespaces from ext_emconf.php',
            fn () => new Entity\Psr4Namespaces($extEmConf['autoload']['psr-4'], $declarationFile, $this->rootPath),
            SymfonyConsole\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Load PSR-4 namespaces from root package
        $rootNamespaces = $this->loadRootComposerPsr4Namespaces();

        // Merge composer PSR-4 namespaces with PSR-4 namespaces from ext_emconf.php
        return $this->taskRunner->run(
            '♨️ Merging PSR-4 namespaces',
            fn () => $rootNamespaces->merge($extEmConfNamespaces, $targetFile),
        );
    }

    private function loadRootComposerPsr4Namespaces(): Entity\Psr4Namespaces
    {
        return $this->taskRunner->run(
            '🌱 Loading PSR-4 namespaces from root package',
            function () {
                $filename = Filesystem\Path::join($this->rootPath, 'composer.json');
                $namespaces = $this->fetchAutoloadFromComposerManifest()['psr-4'];

                foreach ($namespaces as $namespace => $path) {
                    if (!is_array($path)) {
                        continue;
                    }

                    // Flatten array if there's only one path
                    if (1 === count($path)) {
                        $namespaces[$namespace] = reset($path);

                        continue;
                    }

                    // Throw exception for multiple paths
                    throw new Exception\DeclarationFileIsInvalid($filename, '[autoload][psr-4]['.$namespace.']');
                }

                /* @phpstan-ignore argument.type */
                return new Entity\Psr4Namespaces($namespaces, $filename, $this->rootPath);
            },
            SymfonyConsole\Output\OutputInterface::VERBOSITY_VERBOSE,
        );
    }

    /**
     * @return array{
     *     classmap: list<string>,
     *     psr-4: array<string, string|string[]>,
     * }
     *
     * @throws Exception\DeclarationFileIsInvalid
     */
    private function fetchAutoloadFromComposerManifest(): array
    {
        $configFile = Filesystem\Path::join($this->rootPath, 'composer.json');

        try {
            $composer = Factory::create(new IO\NullIO(), $configFile);
        } catch (Throwable) {
            throw new Exception\DeclarationFileIsInvalid($configFile);
        }

        $autoload = $composer->getPackage()->getAutoload();

        if (!array_key_exists('classmap', $autoload)) {
            $autoload['classmap'] = [];
        }
        if (!array_key_exists('psr-4', $autoload)) {
            $autoload['psr-4'] = [];
        }

        return $autoload;
    }

    /**
     * @return ExtEmConf
     *
     * @throws Exception\DeclarationFileIsInvalid
     * @throws Exception\FileDoesNotExist
     */
    private function parseExtEmConf(string $declarationFile): array
    {
        $extEmConf = $this->extEmConfParser->parse($declarationFile);

        // Make sure autoload section is set
        if (!array_key_exists('autoload', $extEmConf)) {
            $extEmConf['autoload'] = [];
        }

        // Throw exception if configured autoload section is invalid
        if (!is_array($extEmConf['autoload'])) {
            throw new Exception\DeclarationFileIsInvalid($declarationFile, '[autoload]');
        }

        // Make sure required sections are set
        if (!array_key_exists('classmap', $extEmConf['autoload'])) {
            $extEmConf['autoload']['classmap'] = [];
        }
        if (!array_key_exists('psr-4', $extEmConf['autoload'])) {
            $extEmConf['autoload']['psr-4'] = [];
        }

        // Throw exception if configured class map is invalid
        if (!is_array($extEmConf['autoload']['classmap'])) {
            throw new Exception\DeclarationFileIsInvalid($declarationFile, '[autoload][classmap]');
        }

        // Throw exception if configured PSR-4 namespace roots are invalid
        if (!is_array($extEmConf['autoload']['psr-4'])) {
            throw new Exception\DeclarationFileIsInvalid($declarationFile, '[autoload][psr-4]');
        }

        /* @phpstan-ignore return.type */
        return $extEmConf;
    }
}
