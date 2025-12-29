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
use EliasHaeussler\TaskRunner;
use EliasHaeussler\Typo3VendorBundler\Exception;
use EliasHaeussler\Typo3VendorBundler\Resource;
use Symfony\Component\Console;
use Symfony\Component\Filesystem;
use Throwable;

use function sprintf;

/**
 * CanExtractDependencies.
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-3.0-or-later
 *
 * @internal
 */
trait CanExtractDependencies
{
    /**
     * @throws Exception\DependencyExtractionFailed
     * @throws Throwable
     */
    private function extractVendorLibrariesFromRootPackage(
        Composer $rootComposer,
        bool $failOnExtractionProblems = true,
    ): void {
        /** @var Resource\DependencySet $dependencySet */
        $dependencySet = $this->taskRunner->run(
            '🔎 Extracting dependencies from root package',
            function (TaskRunner\RunnerContext $context) use ($failOnExtractionProblems, $rootComposer) {
                $dependencySet = $this->dependencyExtractor->extract($rootComposer);
                $problems = [];

                foreach ($dependencySet->problems() as $problem) {
                    $problems[] = $problem;
                    $context->output->writeln(
                        sprintf('⚠️ <warning>%s</warning>', $problem),
                    );
                }

                if ($failOnExtractionProblems && [] !== $problems) {
                    throw new Exception\DependencyExtractionFailed($problems);
                }

                return $dependencySet;
            },
        );

        $this->taskRunner->run(
            '✍️ Creating <comment>composer.json</comment> file for extracted vendor libraries',
            function () use ($dependencySet, $rootComposer) {
                $composerJson = Filesystem\Path::join($this->librariesPath, 'composer.json');
                $dependencySet->dumpToFile($composerJson, $rootComposer);
            },
            Console\Output\OutputInterface::VERBOSITY_VERBOSE,
        );
    }

    private function shouldExtractVendorLibrariesFromRootPackage(bool $extract = true): bool
    {
        if (!$extract) {
            return false;
        }

        $libsComposerJson = Filesystem\Path::join($this->librariesPath, 'composer.json');

        return !$this->filesystem->exists($libsComposerJson);
    }
}
