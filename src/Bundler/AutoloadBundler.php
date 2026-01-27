<?php

declare(strict_types=1);

/*
 * This file is part of the Composer package "eliashaeussler/typo3-vendor-bundler".
 *
 * Copyright (C) 2025-2026 Elias Häußler <elias@haeussler.dev>
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
use Composer\InstalledVersions;
use Composer\Repository;
use EliasHaeussler\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Config;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Helper;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

use function array_map;
use function array_values;
use function basename;
use function dirname;
use function is_dir;
use function is_file;
use function is_int;
use function method_exists;
use function sort;
use function sprintf;

/**
 * AutoloadBundler.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class AutoloadBundler implements Bundler
{
    use CanExtractDependencies;

    private Filesystem\Filesystem $filesystem;
    private TaskRunner\TaskRunner $taskRunner;
    private Resource\DependencyExtractor $dependencyExtractor;
    private Resource\Composer $rootComposer;
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
        $this->rootComposer = Resource\Composer::create($this->rootPath);
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
            $this->extractVendorLibrariesFromRootPackage($failOnExtractionProblems);
        } elseif (!is_dir($this->librariesPath)) {
            throw new Exception\DirectoryDoesNotExist($this->librariesPath);
        }

        // Build autoload bundle from root package and vendor libraries
        $autoload = $this->parseAutoloads($targetFile, $excludeFromClassMap);

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
                    $this->filesystem->copy(
                        Filesystem\Path::join($this->rootPath, 'composer.json'),
                        $autoload->filename(),
                    );
                }

                $configSource = Resource\Composer::create($autoload->filename())->composer->getConfig()->getConfigSource();
                /* @phpstan-ignore argument.type */
                $configSource->addProperty('autoload', (object) $autoload->toArray(true));
            },
        );

        return $autoload;
    }

    /**
     * @param list<non-empty-string> $excludeFromClassMap
     */
    private function parseAutoloads(string $targetFile, array $excludeFromClassMap = []): Entity\Autoload
    {
        $libsComposer = $this->taskRunner->run(
            '📦 Installing vendor libraries',
            function (TaskRunner\RunnerContext $context) {
                $composer = Resource\Composer::create($this->librariesPath);
                $composer->install(false, $context->output);

                return $composer->composer;
            },
        );

        return $this->taskRunner->run(
            '🪄 Parsing autoloads from <comment>composer.json</comment> files',
            function (TaskRunner\RunnerContext $context) use ($libsComposer, $targetFile, $excludeFromClassMap) {
                $rootAutoloads = $this->parseAutoloadsFromPackage($this->rootComposer->composer, false);
                $libsAutoloads = $this->parseAutoloadsFromPackage($libsComposer);
                $autoload = $rootAutoloads->merge($libsAutoloads, $targetFile);

                // Drop excluded files from class map
                foreach ($excludeFromClassMap as $path) {
                    $fullPath = Filesystem\Path::join($this->librariesPath, $path);

                    if ($autoload->classMap->has($fullPath)) {
                        $autoload->classMap->remove($fullPath);

                        $context->output->writeln(
                            sprintf(' <fg=cyan>∟</> ⛔ Removed <comment>%s</comment> from class map', $path),
                            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
                        );
                    } else {
                        $context->output->writeln(
                            sprintf(' <fg=cyan>∟</> ⚠️ File <comment>%s</comment> not found in class map', $path),
                            Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE,
                        );
                    }
                }

                return $autoload;
            },
        );
    }

    /**
     * @throws Exception\CannotDetectWorkingDirectory
     * @throws Exception\DirectoryDoesNotExist
     */
    private function parseAutoloadsFromPackage(Composer $composer, bool $deep = true): Entity\Autoload
    {
        $rootPath = dirname($composer->getConfig()->getConfigSource()->getName());
        $autoloadGenerator = clone $composer->getAutoloadGenerator();
        /* @phpstan-ignore function.alreadyNarrowedType */
        $dryRun = method_exists($autoloadGenerator, 'setDryRun');

        // Enable dry-run mode (Composer >= 2.6)
        if ($dryRun) {
            $autoloadGenerator->setDryRun(true);
        }

        $autoloadGenerator->setDevMode(false);
        $autoloadGenerator->setRunScripts(false);
        $autoloadGenerator->setClassMapAuthoritative(false);
        $filename = $composer->getConfig()->getConfigSource()->getName();
        $repository = $composer->getRepositoryManager()->getLocalRepository();
        $targetDir = Filesystem\Path::join($composer->getConfig()->get('vendor-dir'), 'composer-bundled');

        // Limit local repository to root package if dependencies should be omitted
        if (!$deep) {
            $repository = new Repository\InstalledArrayRepository([
                // Cloning is important here since the package may already be added to another repository
                // and Composer prohibits adding a package to multiple repositories
                clone $composer->getPackage(),
            ]);
        }

        // Resolve PSR-4 namespaces
        $packageMap = $autoloadGenerator->buildPackageMap(
            $composer->getInstallationManager(),
            $composer->getPackage(),
            $repository->getCanonicalPackages(),
        );
        ['files' => $files, 'psr-4' => $namespaces] = $autoloadGenerator->parseAutoloads(
            $packageMap,
            $composer->getPackage(),
            true,
        );

        // Resolve class map
        $classMap = Helper\FilesystemHelper::executeInDirectory(
            $rootPath,
            static fn () => $autoloadGenerator->dump(
                $composer->getConfig(),
                $repository,
                $composer->getPackage(),
                $composer->getInstallationManager(),
                basename($targetDir),
                false,
                null,
                $composer->getLocker(),
            ),
        );

        /* @phpstan-ignore function.impossibleType */
        if (is_int($classMap)) {
            // Extract class map from file (Composer < 2.4)
            $classMapFile = Filesystem\Path::join($targetDir, 'autoload_classmap.php');
            /** @var array<class-string, string> $classMap */
            $classMap = require $classMapFile;
            $classMap = array_map(
                static fn (string $path) => Filesystem\Path::makeRelative($path, $rootPath),
                $classMap,
            );
        } else {
            // Use provided ClassMap instance (Composer >= 2.4)
            $classMap = $classMap->map;
        }

        // Make sure to remove temporary generated autoload files
        if (!$dryRun && $this->filesystem->exists($targetDir)) {
            $this->filesystem->remove($targetDir);
        }

        // Always exclude InstalledVersions class
        unset($classMap[InstalledVersions::class]);

        // Extract class map entries and sort them alphabetically
        $classMapEntries = $classMap;
        sort($classMapEntries);

        return new Entity\Autoload(
            new Entity\ClassMap($classMapEntries, $filename, $rootPath),
            new Entity\Psr4Namespaces($namespaces, $filename, $rootPath),
            new Entity\Files(array_values($files), $filename, $rootPath),
            $filename,
            $rootPath,
        );
    }
}
