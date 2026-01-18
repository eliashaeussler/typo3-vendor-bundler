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
use Composer\Factory;
use Composer\IO;
use EliasHaeussler\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Config;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

use function array_values;
use function is_dir;
use function is_file;
use function ksort;
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
    use CanInstallDependencies;

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
        $this->rootComposer = $this->buildComposerInstance($this->rootPath);
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
     * @param list<non-empty-string> $excludeFromClassMap
     */
    private function parseAutoloads(string $targetFile, array $excludeFromClassMap = []): Entity\Autoload
    {
        $libsComposer = $this->installVendorLibraries();

        [$rootClassMap, $rootPsr4Namespaces, $libsClassMap, $libsPsr4Namespaces] = $this->taskRunner->run(
            '🪄 Parsing autoloads',
            function () use ($libsComposer) {
                [$rootClassMap, $rootPsr4Namespaces] = $this->parseAutoloadsFromPackage($this->rootComposer, $this->rootPath);
                [$libsClassMap, $libsPsr4Namespaces] = $this->parseAutoloadsFromPackage($libsComposer, $this->librariesPath);

                return [$rootClassMap, $rootPsr4Namespaces, $libsClassMap, $libsPsr4Namespaces];
            },
        );

        $classMap = $this->taskRunner->run(
            '♨️ Merging class maps',
            function (TaskRunner\RunnerContext $context) use ($excludeFromClassMap, $libsClassMap, $rootClassMap, $targetFile) {
                $classMap = $rootClassMap->merge($libsClassMap, $targetFile);

                // Drop excluded files from class map
                foreach ($excludeFromClassMap as $path) {
                    $fullPath = Filesystem\Path::join($this->librariesPath, $path);

                    if ($classMap->has($fullPath)) {
                        $classMap = $classMap->remove($fullPath);

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

                return $classMap;
            },
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        $psr4Namespaces = $this->taskRunner->run(
            '♨️ Merging PSR-4 namespaces',
            static fn () => $rootPsr4Namespaces->merge($libsPsr4Namespaces, $targetFile),
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        return new Entity\Autoload($classMap, $psr4Namespaces, $targetFile, $this->rootPath);
    }

    /**
     * @return array{Entity\ClassMap, Entity\Psr4Namespaces}
     */
    private function parseAutoloadsFromPackage(Composer $composer, string $rootPath): array
    {
        $filename = $composer->getConfig()->getConfigSource()->getName();
        $autoloadGenerator = $composer->getAutoloadGenerator();
        $packageMap = $autoloadGenerator->buildPackageMap(
            $composer->getInstallationManager(),
            $composer->getPackage(),
            $composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages(),
        );

        ['psr-4' => $psr4, 'classmap' => $classMap] = $autoloadGenerator->parseAutoloads(
            $packageMap,
            $composer->getPackage(),
            true,
        );

        ksort($classMap);

        return [
            new Entity\ClassMap(array_values($classMap), $filename, $rootPath),
            new Entity\Psr4Namespaces($psr4, $filename, $rootPath),
        ];
    }
}
