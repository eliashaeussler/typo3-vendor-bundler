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
use EliasHaeussler\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Config;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console;
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
    use CanExtractDependencies;

    private Filesystem\Filesystem $filesystem;
    private TaskRunner\TaskRunner $taskRunner;
    private Resource\DependencyExtractor $dependencyExtractor;
    private Composer $rootComposer;
    private string $librariesPath;

    /**
     * @throws Exception\DeclarationFileIsInvalid
     */
    public function __construct(
        private string $rootPath,
        string $librariesPath,
        private Console\Output\OutputInterface $output,
    ) {
        $this->filesystem = new Filesystem\Filesystem();
        $this->taskRunner = new TaskRunner\TaskRunner($this->output);
        $this->dependencyExtractor = new Resource\DependencyExtractor();
        $this->rootComposer = $this->buildRootComposerInstance();
        $this->librariesPath = Filesystem\Path::makeAbsolute($librariesPath, $this->rootPath);
    }

    /**
     * @param list<non-empty-string> $excludeFromClassMap
     *
     * @throws Exception\DirectoryDoesNotExist
     * @throws Exception\FileAlreadyExists
     * @throws Throwable
     */
    public function bundle(
        Config\AutoloadTarget $target = new Config\AutoloadTarget(),
        bool $extractDependencies = true,
        bool $failOnExtractionProblems = true,
        bool $backupSources = false,
        array $excludeFromClassMap = [],
    ): Entity\Autoload {
        $targetFile = Filesystem\Path::makeAbsolute($target->file(), $this->rootPath);

        // Extract vendor libraries from root package if necessary
        if ($this->shouldExtractVendorLibrariesFromRootPackage($extractDependencies)) {
            $this->extractVendorLibrariesFromRootPackage($this->rootComposer, $failOnExtractionProblems);
        } elseif (!is_dir($this->librariesPath)) {
            throw new Exception\DirectoryDoesNotExist($this->librariesPath);
        }

        // Build class maps
        $classMaps = [
            $this->loadRootComposerClassMap(),
            $this->loadVendorComposerClassMap($excludeFromClassMap),
        ];

        // Create class map and PSR-4 namespaces
        $classMap = $this->mergeClassMaps($classMaps, $targetFile);
        $psr4Namespaces = $this->loadRootComposerPsr4Namespaces();
        $autoload = new Entity\Autoload($classMap, $psr4Namespaces, $targetFile, $this->rootPath);

        // Throw exception if target file already exists
        if (true !== $target->overwrite() && $this->filesystem->exists($targetFile)) {
            throw new Exception\FileAlreadyExists($targetFile);
        }

        // Create composer.json backup
        if (true === $backupSources) {
            $this->taskRunner->run(
                '🦖 Backing up source files',
                function () use ($targetFile) {
                    $composerJson = Filesystem\Path::join($this->rootPath, 'composer.json');

                    if ($targetFile === $composerJson) {
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
                $configSource->addProperty('autoload', (object) $autoload->toArray(true));
            },
        );

        return $autoload;
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
            function (TaskRunner\RunnerContext $context) {
                $output = $context->output;
                $io = new IO\BufferIO('', $output->getVerbosity(), $output->getFormatter());
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

                if (Console\Command\Command::SUCCESS !== $installResult) {
                    $output->writeln($io->getOutput());

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
        );

        // Drop excluded files from class map
        if ([] !== $excludeFromClassMap) {
            foreach ($excludeFromClassMap as $path) {
                $fullPath = Filesystem\Path::join($this->librariesPath, $path);
                $classMap = $this->taskRunner->run(
                    sprintf('⛔ Removing "%s" from class map', $path),
                    function (TaskRunner\RunnerContext $context) use ($classMap, $fullPath) {
                        if (!$classMap->has($fullPath)) {
                            $context->markAsFailed();

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
        );
    }

    /**
     * @return array{
     *     classmap: list<string>,
     *     psr-4: array<string, string|string[]>,
     * }
     */
    private function fetchAutoloadFromComposerManifest(): array
    {
        $autoload = $this->rootComposer->getPackage()->getAutoload();

        if (!array_key_exists('classmap', $autoload)) {
            $autoload['classmap'] = [];
        }
        if (!array_key_exists('psr-4', $autoload)) {
            $autoload['psr-4'] = [];
        }

        return $autoload;
    }

    /**
     * @throws Exception\DeclarationFileIsInvalid
     */
    private function buildRootComposerInstance(): Composer
    {
        $configFile = Filesystem\Path::join($this->rootPath, 'composer.json');

        try {
            return Factory::create(new IO\NullIO(), $configFile);
        } catch (Throwable $exception) {
            throw new Exception\DeclarationFileIsInvalid($configFile, previous: $exception);
        }
    }
}
