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

use Composer\Composer;
use Composer\Factory;
use Composer\Installer;
use Composer\IO;
use EliasHaeussler\Typo3VendorBundler\Console\Output\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

use function array_key_exists;
use function array_values;
use function count;
use function is_array;
use function is_dir;
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
    private TaskRunner $taskRunner;
    private string $librariesPath;

    /**
     * @throws Exception\DirectoryDoesNotExist
     */
    public function __construct(
        private string $rootPath,
        string $librariesPath,
        private Console\Output\OutputInterface $output,
    ) {
        $this->extEmConfParser = new Resource\ExtEmConfParser();
        $this->filesystem = new Filesystem\Filesystem();
        $this->taskRunner = new TaskRunner($this->output);
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
        string $targetFile = 'ext_emconf.php',
        bool $dropComposerAutoload = true,
        bool $backupSources = false,
        bool $overwriteExistingTargetFile = false,
        array $excludeFromClassMap = [],
    ): Entity\Autoload {
        $targetFile = Filesystem\Path::makeAbsolute($targetFile, $this->rootPath);
        $declarationFile = Filesystem\Path::join($this->rootPath, 'ext_emconf.php');

        // Parse ext_emconf.php file
        $extEmConf = $this->taskRunner->run(
            '🔍 Parsing ext_emconf.php file',
            fn () => $this->parseExtEmConf($declarationFile),
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Create class map and PSR-4 namespaces
        $classMap = $this->mergeClassMaps($declarationFile, $extEmConf, $targetFile, $excludeFromClassMap);
        $psr4Namespaces = $this->mergePsr4Namespaces($declarationFile, $extEmConf, $targetFile);
        $autoload = new Entity\Autoload($classMap, $psr4Namespaces, $targetFile, $this->rootPath);

        // Throw exception if target file already exists
        if (!$overwriteExistingTargetFile && $this->filesystem->exists($targetFile)) {
            throw new Exception\FileAlreadyExists($targetFile);
        }

        // Create ext_emconf.php backup
        if ($backupSources) {
            $this->taskRunner->run(
                '🦖 Backing up source files',
                function () use ($declarationFile, $dropComposerAutoload, $targetFile) {
                    if ($targetFile === $declarationFile) {
                        $this->filesystem->copy($declarationFile, $declarationFile.'.bak');
                    }

                    if ($dropComposerAutoload) {
                        $composerJson = Filesystem\Path::join($this->rootPath, 'composer.json');

                        $this->filesystem->copy($composerJson, $composerJson.'.bak');
                    }
                },
            );
        }

        // Create modified ext_emconf.php file contents
        $this->taskRunner->run(
            '🎊 Dumping merged autoload configuration',
            function () use ($extEmConf, $targetFile) {
                $extEmConfArray = var_export($extEmConf, true);
                $contents = <<<PHP
<?php

\$EM_CONF[\$_EXTKEY] = $extEmConfArray;
PHP;

                $this->filesystem->dumpFile($targetFile, $contents);
            },
        );

        // Remove autoload section from root composer.json
        if ($dropComposerAutoload) {
            $this->taskRunner->run(
                '✂️ Removing autoload section from composer.json',
                function () {
                    $composer = $this->createComposer($this->rootPath);
                    $configSource = $composer->getConfig()->getConfigSource();
                    $configSource->removeProperty('autoload');
                },
            );
        }

        return $autoload;
    }

    /**
     * @param ExtEmConf              $extEmConf
     * @param list<non-empty-string> $excludeFromClassMap
     *
     * @throws Throwable
     */
    private function mergeClassMaps(
        string $declarationFile,
        array &$extEmConf,
        string $targetFile,
        array $excludeFromClassMap = [],
    ): Entity\ClassMap {
        // Load class map from ext_emconf.php
        $extEmConfClassMap = $this->taskRunner->run(
            '🍄 Loading class map from ext_emconf.php',
            fn () => new Entity\ClassMap($extEmConf['autoload']['classmap'], $declarationFile, $this->rootPath),
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Build class map from vendor libraries
        $libsClassMap = $this->buildComposerClassMap($excludeFromClassMap);

        // Load class map from root package
        $rootClassMap = $this->taskRunner->run(
            '🌱 Loading class map from root package',
            fn () => new Entity\ClassMap(
                $this->fetchAutoloadFromComposerManifest()['classmap'],
                Filesystem\Path::join($this->rootPath, 'composer.json'),
                $this->rootPath,
            ),
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Merge composer class map with class map from ext_emconf.php
        return $this->taskRunner->run(
            '♨️ Merging class maps',
            function () use (&$extEmConf, $extEmConfClassMap, $libsClassMap, $rootClassMap, $targetFile) {
                $mergedClassMap = $rootClassMap
                    ->merge($libsClassMap, $targetFile)
                    ->merge($extEmConfClassMap, $targetFile)
                ;
                $extEmConf['autoload']['classmap'] = $mergedClassMap->toArray(true);

                return $mergedClassMap;
            },
        );
    }

    /**
     * @param ExtEmConf $extEmConf
     *
     * @throws Throwable
     */
    private function mergePsr4Namespaces(string $declarationFile, array &$extEmConf, string $targetFile): Entity\Psr4Namespaces
    {
        // Load PSR-4 namespaces from ext_emconf.php
        $extEmConfNamespaces = $this->taskRunner->run(
            '🍄 Loading PSR-4 namespaces from ext_emconf.php',
            fn () => new Entity\Psr4Namespaces($extEmConf['autoload']['psr-4'], $declarationFile, $this->rootPath),
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Load PSR-4 namespaces from root package
        $rootNamespaces = $this->taskRunner->run(
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
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Merge composer PSR-4 namespaces with PSR-4 namespaces from ext_emconf.php
        return $this->taskRunner->run(
            '♨️ Merging PSR-4 namespaces',
            function () use (&$extEmConf, $extEmConfNamespaces, $rootNamespaces, $targetFile) {
                $mergedNamespaces = $rootNamespaces->merge($extEmConfNamespaces, $targetFile);
                $extEmConf['autoload']['psr-4'] = $mergedNamespaces->toArray(true);

                return $mergedNamespaces;
            },
        );
    }

    /**
     * @param list<non-empty-string> $excludeFromClassMap
     *
     * @throws Throwable
     */
    private function buildComposerClassMap(array $excludeFromClassMap = []): Entity\ClassMap
    {
        $classMap = $this->taskRunner->run(
            '🌱 Building class map from vendor libraries',
            function () {
                $io = new IO\BufferIO(verbosity: $this->output->getVerbosity());
                $composer = $this->createComposer($this->librariesPath, $io);

                $vendorDir = $composer->getConfig()->get('vendor-dir');
                $classMapFile = Filesystem\Path::join($vendorDir, 'composer', 'autoload_classmap.php');

                $installResult = Installer::create($io, $composer)
                    ->setClassMapAuthoritative(true)
                    ->setOptimizeAutoloader(true)
                    ->run();

                if (Console\Command\Command::SUCCESS !== $installResult) {
                    $this->output->writeln($io->getOutput(), Console\Output\OutputInterface::OUTPUT_RAW);

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
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
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
                    Console\Output\OutputInterface::VERBOSITY_VERBOSE,
                );
            }
        }

        return $classMap;
    }

    /**
     * @return array{
     *     classmap: list<string>,
     *     psr-4: array<string, string|string[]>,
     * }
     */
    private function fetchAutoloadFromComposerManifest(): array
    {
        $configFile = Filesystem\Path::join($this->rootPath, 'composer.json');
        $composer = Factory::create(new IO\NullIO(), $configFile);
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

    private function createComposer(string $path, IO\IOInterface $io = new IO\NullIO()): Composer
    {
        $configFile = Filesystem\Path::join($path, 'composer.json');

        return Factory::create($io, $configFile);
    }
}
