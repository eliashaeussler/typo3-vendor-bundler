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

use CycloneDX\Core;
use EliasHaeussler\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

use function is_dir;

/**
 * DependencyBundler.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 */
final readonly class DependencyBundler implements Bundler
{
    use CanExtractDependencies;
    use CanModifyExtraSection;

    private Resource\BomGenerator $bomGenerator;
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
        $this->bomGenerator = new Resource\BomGenerator($this->rootPath);
        $this->filesystem = new Filesystem\Filesystem();
        $this->taskRunner = new TaskRunner\TaskRunner($this->output);
        $this->dependencyExtractor = new Resource\DependencyExtractor();
        $this->rootComposer = Resource\Composer::create($this->rootPath);
        $this->librariesPath = Filesystem\Path::makeAbsolute($librariesPath, $this->rootPath);
    }

    /**
     * @throws Exception\CannotDumpBomFile
     * @throws Exception\CannotInstallComposerDependencies
     * @throws Exception\FileAlreadyExists
     * @throws Throwable
     */
    public function bundle(
        string $filename = 'sbom.json',
        Core\Spec\Version $version = Core\Spec\Version::v1dot7,
        bool $extractDependencies = true,
        bool $failOnExtractionProblems = true,
        bool $includeDevDependencies = true,
        bool $overwrite = false,
        bool $backupSources = false,
    ): Entity\Dependencies {
        $format = Resource\BomFormat::fromFile($filename);

        // Validate file format
        if (!$format->supports($version)) {
            throw new Exception\BomFormatIsNotSupported($format, $version);
        }

        // Extract vendor libraries from root package if necessary
        if ($this->shouldExtractVendorLibrariesFromRootPackage($extractDependencies)) {
            $this->extractVendorLibrariesFromRootPackage($failOnExtractionProblems);
        } elseif (!is_dir($this->librariesPath)) {
            throw new Exception\DirectoryDoesNotExist($this->librariesPath);
        }

        // Resolve filename
        if (Filesystem\Path::isRelative($filename)) {
            $filename = Filesystem\Path::makeAbsolute($filename, $this->librariesPath);
        }

        // Throw exception if SBOM file already exists
        if (!$overwrite && $this->filesystem->exists($filename)) {
            throw new Exception\FileAlreadyExists($filename);
        }

        // Make sure Composer dependencies are installed
        $composer = $this->taskRunner->run(
            '📦 Installing vendor libraries',
            function (TaskRunner\RunnerContext $context) use ($includeDevDependencies) {
                $composer = Resource\Composer::create($this->librariesPath);
                $composer->install($includeDevDependencies, $context->output);

                return $composer->composer;
            },
        );

        // Generate SBOM
        $sbomFile = Filesystem\Path::makeRelative($filename, $this->rootPath);
        $bom = $this->taskRunner->run(
            '🧩 Generating Software Bill of Materials',
            fn () => $this->bomGenerator->generate($composer, $includeDevDependencies),
        );

        // Serialize generated SBOM
        $this->serializeBom($version, $bom, $format, $filename);

        if (!$this->extraSectionIsPrepared() || $this->extraSectionNeedsUpdate('sbom-file', $sbomFile)) {
            // Create composer.json backup
            if (true === $backupSources) {
                $this->taskRunner->run(
                    '🦖 Backing up source files',
                    function () {
                        $composerJson = $this->rootComposer->composer->getConfig()->getConfigSource()->getName();

                        $this->filesystem->copy($composerJson, $composerJson.'.bak');
                    },
                );
            }

            // Write metadata to composer.json
            $this->taskRunner->run(
                '✍️ Writing dependency metadata to <comment>composer.json</comment> file',
                function () use ($sbomFile) {
                    $this->prepareExtraSection();
                    $this->modifyExtraSection('sbom-file', $sbomFile);
                },
            );
        }

        return new Entity\Dependencies($filename, $this->rootPath);
    }

    private function serializeBom(
        Core\Spec\Version $version,
        Core\Models\Bom $bom,
        Resource\BomFormat $format,
        string $filename,
    ): void {
        // Serialize SBOM
        $serialized = $this->taskRunner->run(
            '🌱 Serializing generated SBOM',
            static fn () => $format->createSerializer($version)->serialize($bom),
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );

        // Validate serialized SBOM
        $this->taskRunner->run(
            '🐛 Validating serialized SBOM',
            function () use ($format, $serialized, $version) {
                $validator = $format->createValidator($version);
                $error = $validator->validateString($serialized);

                if (null !== $error) {
                    throw new Exception\SerializedBomIsInvalid($error->getMessage());
                }
            },
        );

        // Dump serialized SBOM
        $this->taskRunner->run(
            sprintf('🎊 Dumping SBOM v%s file', $version->value),
            function () use ($filename, $serialized) {
                $this->filesystem->dumpFile($filename, $serialized);

                if (!$this->filesystem->exists($filename)) {
                    throw new Exception\CannotDumpBomFile();
                }
            },
        );
    }
}
